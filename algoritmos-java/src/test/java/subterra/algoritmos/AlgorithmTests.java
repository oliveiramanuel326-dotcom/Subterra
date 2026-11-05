package subterra.algoritmos;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.Set;

public final class AlgorithmTests {
    private static final long AGORA = 1_800_000_000L;

    public static void main(String[] args) {
        testDensidade();
        testModeracao();
        testMaxFlow();
        testRanking();
        testComunidades();
        testRateLimiter();
        testPasswordHash();
        System.out.println("All Subterra algorithm tests passed.");
    }

    private static void testDensidade() {
        double d = DensidadeSemantica.calcular(
                "A consciencia muda quando a noite revela perguntas que o silencio guardava."
        );
        assert d >= 0 && d <= 1;
        assert DensidadeSemantica.calcular("") == 0;
        assert DensidadeSemantica.similaridade("pedra noite", "pedra noite") == 1;
    }

    private static void testModeracao() {
        String text = "A noite revela perguntas sobre a vida e o silencio que guardamos "
                + "quando tentamos compreender nossos medos e nossas escolhas.";
        Moderacao mod = new Moderacao();
        assert mod.avaliar(text) == Moderacao.Resultado.CURTO_DEMAIS;

        text = "A noite revela perguntas sobre a vida e o silencio que guardamos "
                + "quando tentamos compreender nossos medos e nossas escolhas enquanto "
                + "procuramos algum sentido para continuar vivendo.";
        assert DensidadeSemantica.tokenizar(text).size() >= Moderacao.MIN_PALAVRAS;
        assert mod.avaliar(text) == Moderacao.Resultado.APROVADO;
        assert mod.avaliar(text) == Moderacao.Resultado.DUPLICADO;
        assert new Moderacao(Set.of("proibido")).avaliar(
                text + " palavra proibido"
        ) == Moderacao.Resultado.PROIBIDO;
    }

    private static void testMaxFlow() {
        Grafo graph = new Grafo();
        graph.adicionarAresta(0, 1, 3);
        graph.adicionarAresta(0, 2, 2);
        graph.adicionarAresta(1, 2, 1);
        graph.adicionarAresta(1, 3, 2);
        graph.adicionarAresta(2, 3, 3);
        double edmondsKarp = MaxFlow.edmondsKarp(graph, 0, 3);
        double dinic = MaxFlow.dinic(graph, 0, 3);
        assert edmondsKarp == 5;
        assert dinic == edmondsKarp;
        assert MaxFlow.ladoS(graph, 0).contains(0);
    }

    private static void testRanking() {
        List<Post> posts = new ArrayList<>();
        Post deep = new Post(1, "ANONIMO", "A noite revela perguntas e silencios.", AGORA);
        deep.setDensidade(0.9);
        Post shallow = new Post(2, "ANONIMO", "ok", AGORA);
        shallow.setDensidade(0.1);
        posts.add(shallow);
        posts.add(deep);

        List<Post> ranked = RankingFeed.feed(posts, AGORA);
        assert ranked.get(0).getId() == 1;
        assert RankingFeed.topK(posts, 1, AGORA).size() == 1;
        assert RankingFeed.topK(posts, 0, AGORA).isEmpty();
    }

    private static void testComunidades() {
        Comunidades graph = new Comunidades();
        graph.adicionarInteracao("a", "b", 3);
        graph.adicionarInteracao("b", "c", 2);
        Map<String, String> labels = graph.labelPropagation(42);
        assert labels.keySet().equals(Set.of("a", "b", "c"));
        assert labels.get("a").equals(labels.get("b"));
        assert labels.get("b").equals(labels.get("c"));
    }

    private static void testRateLimiter() {
        RateLimiter limiter = new RateLimiter(2, 1);
        assert limiter.permitir("1", 0);
        assert limiter.permitir("1", 0);
        assert !limiter.permitir("1", 0);
        assert limiter.permitir("1", 1_000_000_000L);
        assert !limiter.permitir("1", 0);
        assert limiter.permitir("2", 0);
        expectFailure(() -> new RateLimiter(0, 1));
    }

    private static void testPasswordHash() {
        byte[] salt = new byte[16];
        java.util.Arrays.fill(salt, (byte) 7);
        String hash = Anonimato.hashSenha("password de teste", salt);
        assert Anonimato.verificarSenha("password de teste", hash);
        assert !Anonimato.verificarSenha("password errado", hash);
        assert !Anonimato.verificarSenha("password de teste", "invalid");
    }

    private static void expectFailure(Runnable action) {
        try {
            action.run();
            throw new AssertionError("Expected IllegalArgumentException.");
        } catch (IllegalArgumentException expected) {
            // Expected validation failure.
        }
    }
}
