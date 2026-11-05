package subterra.algoritmos;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;
import java.io.IOException;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.TreeMap;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class SubterraAlgorithmService {
    private static final int MAX_BODY_BYTES = 1_000_000;
    private static final int MAX_POSTS = 50;
    private static final int MAX_TEXT_LENGTH = 5_000;
    private static final Pattern POST_FIELD =
            Pattern.compile("posts\\[(\\d+)]\\[(id|text|created_at|upvotes)]");

    private final Moderacao moderacao = new Moderacao(termosProibidos());
    private final RateLimiter rateLimiter = new RateLimiter(6, 0.1);

    private SubterraAlgorithmService() {}

    public static void main(String[] args) throws IOException {
        int port = parsePort(System.getenv("SUBTERRA_ALGORITHM_PORT"));
        HttpServer server = HttpServer.create(
                new InetSocketAddress(InetAddress.getByName("127.0.0.1"), port),
                32
        );
        SubterraAlgorithmService service = new SubterraAlgorithmService();
        server.createContext("/", service::handle);
        ExecutorService executor = Executors.newFixedThreadPool(8);
        server.setExecutor(executor);
        server.start();
        Runtime.getRuntime().addShutdownHook(new Thread(() -> {
            server.stop(1);
            executor.shutdown();
        }));
        System.out.println("Subterra algorithms listening on 127.0.0.1:" + port);
    }

    private static int parsePort(String configured) {
        if (configured == null || configured.isBlank()) return 8787;
        try {
            int port = Integer.parseInt(configured);
            if (port < 1 || port > 65_535) throw new NumberFormatException();
            return port;
        } catch (NumberFormatException ex) {
            throw new IllegalArgumentException("SUBTERRA_ALGORITHM_PORT invalida.");
        }
    }

    private static List<String> termosProibidos() {
        String configured = System.getenv("SUBTERRA_BLOCKED_TERMS");
        if (configured == null || configured.isBlank()) return List.of();
        return Arrays.stream(configured.split(","))
                .map(String::trim)
                .filter(term -> !term.isEmpty())
                .toList();
    }

    private void handle(HttpExchange exchange) throws IOException {
        try (exchange) {
            try {
                String path = exchange.getRequestURI().getPath();
                if (path.equals("/health") && exchange.getRequestMethod().equals("GET")) {
                    responder(exchange, 200, "{\"ok\":true}");
                    return;
                }
                if (!exchange.getRequestMethod().equals("POST")) {
                    exchange.getResponseHeaders().set("Allow", "GET, POST");
                    responder(exchange, 405, erro("Metodo nao permitido."));
                    return;
                }

                Map<String, String> fields = lerFormulario(exchange);
                switch (path) {
                    case "/api/rank" -> responder(exchange, 200, classificar(fields));
                    case "/api/moderate" -> responder(exchange, 200, moderar(fields));
                    case "/api/rate-limit" -> responder(exchange, 200, limitar(fields));
                    default -> responder(exchange, 404, erro("Rota nao encontrada."));
                }
            } catch (IllegalArgumentException ex) {
                responder(exchange, 400, erro(ex.getMessage()));
            } catch (Exception ex) {
                ex.printStackTrace(System.err);
                responder(exchange, 500, erro("Erro interno do servico de algoritmos."));
            }
        }
    }

    private static Map<String, String> lerFormulario(HttpExchange exchange)
            throws IOException {
        String contentType = exchange.getRequestHeaders().getFirst("Content-Type");
        if (contentType == null
                || !contentType.toLowerCase(Locale.ROOT)
                        .startsWith("application/x-www-form-urlencoded")) {
            throw new IllegalArgumentException("Content-Type deve ser application/x-www-form-urlencoded.");
        }

        byte[] body = exchange.getRequestBody().readNBytes(MAX_BODY_BYTES + 1);
        if (body.length > MAX_BODY_BYTES) {
            throw new IllegalArgumentException("Pedido demasiado grande.");
        }

        Map<String, String> fields = new LinkedHashMap<>();
        String encoded = new String(body, StandardCharsets.UTF_8);
        if (encoded.isEmpty()) return fields;

        for (String pair : encoded.split("&")) {
            String[] parts = pair.split("=", 2);
            String key = URLDecoder.decode(parts[0], StandardCharsets.UTF_8);
            String value = URLDecoder.decode(
                    parts.length == 2 ? parts[1] : "",
                    StandardCharsets.UTF_8
            );
            if (fields.putIfAbsent(key, value) != null) {
                throw new IllegalArgumentException("Campo duplicado no pedido.");
            }
        }
        return fields;
    }

    private static String classificar(Map<String, String> fields) {
        TreeMap<Integer, Map<String, String>> linhas = new TreeMap<>();
        for (Map.Entry<String, String> field : fields.entrySet()) {
            Matcher matcher = POST_FIELD.matcher(field.getKey());
            if (!matcher.matches()) {
                throw new IllegalArgumentException("Campo de publicacao invalido.");
            }
            int index = parseInt(matcher.group(1), "Indice de publicacao invalido.");
            linhas.computeIfAbsent(index, ignored -> new LinkedHashMap<>())
                    .put(matcher.group(2), field.getValue());
        }
        if (linhas.isEmpty() || linhas.size() > MAX_POSTS) {
            throw new IllegalArgumentException("O pedido deve conter entre 1 e 50 publicacoes.");
        }

        List<Post> posts = new ArrayList<>(linhas.size());
        java.util.Set<Integer> ids = new java.util.HashSet<>();
        int esperado = 0;
        for (Map.Entry<Integer, Map<String, String>> linha : linhas.entrySet()) {
            Map<String, String> dados = linha.getValue();
            if (linha.getKey() != esperado++
                    || !dados.keySet().containsAll(
                            List.of("id", "text", "created_at", "upvotes"))) {
                throw new IllegalArgumentException("Dados de publicacao incompletos.");
            }

            int id = parseInt(dados.get("id"), "Identificador de publicacao invalido.");
            if (id < 1 || !ids.add(id)) {
                throw new IllegalArgumentException("Identificador de publicacao invalido ou repetido.");
            }
            long criadoEm = parseLong(dados.get("created_at"), "Data de publicacao invalida.");
            int votos = parseInt(dados.get("upvotes"), "Votos invalidos.");
            String texto = dados.get("text");
            if (texto.length() > MAX_TEXT_LENGTH || criadoEm < 0 || votos < 0) {
                throw new IllegalArgumentException("Dados de publicacao fora dos limites.");
            }

            Post post = new Post(id, "ANONIMO", texto, criadoEm);
            post.setUpvotesProfundidade(votos);
            post.setDensidade(DensidadeSemantica.calcular(texto));
            posts.add(post);
        }

        ClassificadorSubterraneo.classificar(
                posts,
                ClassificadorSubterraneo.Metodo.DINIC
        );
        List<Post> ordenados = RankingFeed.feed(posts, System.currentTimeMillis() / 1000);
        StringBuilder json = new StringBuilder("{\"ok\":true,\"posts\":[");
        for (int i = 0; i < ordenados.size(); i++) {
            Post post = ordenados.get(i);
            if (i > 0) json.append(',');
            json.append("{\"id\":").append(post.getId())
                    .append(",\"density\":").append(post.getDensidade())
                    .append(",\"introspective\":").append(post.isIntrospectivo())
                    .append(",\"score\":").append(post.getScore()).append('}');
        }
        return json.append("]}").toString();
    }

    private String moderar(Map<String, String> fields) {
        String text = fields.get("text");
        if (text == null || text.length() > MAX_TEXT_LENGTH) {
            throw new IllegalArgumentException("Texto ausente ou demasiado longo.");
        }
        Moderacao.Resultado resultado = moderacao.avaliar(text);
        return "{\"ok\":true,\"result\":\"" + resultado.name()
                + "\",\"density\":" + DensidadeSemantica.calcular(text) + "}";
    }

    private String limitar(Map<String, String> fields) {
        String userId = fields.get("user_id");
        if (userId == null || parseLong(userId, "Identificador de utilizador invalido.") < 1) {
            throw new IllegalArgumentException("Identificador de utilizador invalido.");
        }
        boolean allowed = rateLimiter.permitir(userId);
        return "{\"ok\":true,\"allowed\":" + allowed + "}";
    }

    private static int parseInt(String value, String message) {
        try {
            return Integer.parseInt(value);
        } catch (NumberFormatException ex) {
            throw new IllegalArgumentException(message);
        }
    }

    private static long parseLong(String value, String message) {
        try {
            return Long.parseLong(value);
        } catch (NumberFormatException ex) {
            throw new IllegalArgumentException(message);
        }
    }

    private static String erro(String message) {
        return "{\"ok\":false,\"erro\":\"" + escaparJson(message) + "\"}";
    }

    private static String escaparJson(String text) {
        return text.replace("\\", "\\\\")
                .replace("\"", "\\\"")
                .replace("\n", "\\n")
                .replace("\r", "\\r");
    }

    private static void responder(HttpExchange exchange, int status, String body)
            throws IOException {
        byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().set("Content-Type", "application/json; charset=utf-8");
        exchange.getResponseHeaders().set("Cache-Control", "no-store");
        exchange.getResponseHeaders().set("X-Content-Type-Options", "nosniff");
        exchange.sendResponseHeaders(status, bytes.length);
        exchange.getResponseBody().write(bytes);
    }
}
