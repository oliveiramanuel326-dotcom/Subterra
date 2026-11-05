package subterra.algoritmos;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.*;
import java.util.Locale;

/**
 * Moderação automática (slide 7). Verificações por ordem; a primeira que falha decide.
 *   1. Comprimento mínimo           -> "apenas texto longo e médio"
 *   2. Lista opcional, configurada por moderadores -> termos exactos
 *   3. Spam: links em excesso, caracteres repetidos, flood de maiúsculas
 *   4. Duplicado: hash SHA-256 do texto normalizado num HashMap (copiar-colar em massa)
 * Se passar tudo, devolve APROVADO; posts aprovados mas rasos são tratados pelo RankingFeed.
 */
public class Moderacao {

    public enum Resultado { APROVADO, CURTO_DEMAIS, PROIBIDO, SPAM, DUPLICADO }

    public static final int MIN_PALAVRAS = 25;
    private static final int MAX_LINKS = 2;
    private static final int MAX_HASHES = 10_000;
    private static final long RETENCAO_DUPLICADOS_MS = 24 * 60 * 60 * 1000L;
    private final Set<String> proibidos;
    private final LinkedHashMap<String, Long> hashes = new LinkedHashMap<>();
    private int chamadas;

    public Moderacao() {
        this(Collections.emptySet());
    }

    public Moderacao(Collection<String> termosProibidos) {
        proibidos = new HashSet<>();
        for (String termo : termosProibidos) {
            String normalizado = termo.toLowerCase(Locale.ROOT).trim();
            if (!normalizado.isEmpty()) proibidos.add(normalizado);
        }
    }

    public synchronized Resultado avaliar(String texto) {
        if (texto == null || texto.isBlank()) return Resultado.CURTO_DEMAIS;
        List<String> tokens = DensidadeSemantica.tokenizar(texto);

        if (tokens.size() < MIN_PALAVRAS) return Resultado.CURTO_DEMAIS;

        for (String t : tokens) if (proibidos.contains(t)) return Resultado.PROIBIDO;

        int links = texto.toLowerCase(Locale.ROOT)
                .split("https?://", -1).length - 1;
        if (links > MAX_LINKS) return Resultado.SPAM;
        if (texto.matches("(?s).*(.)\\1{7,}.*")) return Resultado.SPAM;   // 8+ caracteres iguais seguidos

        if (++chamadas % 128 == 0) limparHashes(System.currentTimeMillis());
        String h = sha256(String.join(" ", tokens));
        if (hashes.containsKey(h)) return Resultado.DUPLICADO;
        hashes.put(h, System.currentTimeMillis());
        if (hashes.size() > MAX_HASHES) {
            hashes.remove(hashes.keySet().iterator().next());
        }

        return Resultado.APROVADO;
    }

    private void limparHashes(long agora) {
        hashes.entrySet().removeIf(
                entrada -> agora - entrada.getValue() > RETENCAO_DUPLICADOS_MS
        );
    }

    static String sha256(String s) {
        try {
            byte[] d = MessageDigest.getInstance("SHA-256").digest(s.getBytes(StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            for (byte b : d) sb.append(String.format("%02x", b));
            return sb.toString();
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }
}
