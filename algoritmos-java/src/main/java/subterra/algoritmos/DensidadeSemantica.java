package subterra.algoritmos;

import java.util.*;
import java.util.Locale;

/**
 * Densidade semântica de um texto (0..1) e penalização de polarização superficial.
 *
 * densidade = 0.30*comprimento + 0.30*diversidadeLexical + 0.25*conteudo + 0.15*estrutura
 *             - penalizacaoPolarizacao
 *
 *   comprimento        : min(palavras / 120, 1)            (texto longo/médio pesa mais)
 *   diversidadeLexical : palavras distintas / total        (type-token ratio)
 *   conteudo           : 1 - proporção de stopwords        (palavras com significado)
 *   estrutura          : min(palavras médias por frase / 20, 1)
 *   polarização        : !!!, MAIÚSCULAS, #hashtags, emojis, frases de sinalização de virtude
 */
public class DensidadeSemantica {

    private static final Set<String> STOPWORDS = new HashSet<>(Arrays.asList(
            "a", "o", "as", "os", "um", "uma", "de", "do", "da", "dos", "das", "e", "é",
            "em", "no", "na", "nos", "nas", "que", "se", "por", "para", "pra", "com",
            "não", "mas", "ou", "eu", "tu", "ele", "ela", "isso", "isto", "só", "já",
            "muito", "mais", "me", "te", "lhe", "ao", "à", "seu", "sua", "meu", "minha",
            "kkk", "kkkk", "lol", "tipo", "né"));

    private static final String[] SINALIZACAO_VIRTUDE = {
            "gratidão", "good vibes", "abençoado", "abençoada", "só positividade",
            "foco força fé", "#blessed", "segue para mais", "partilha se concordas"};

    public static List<String> tokenizar(String texto) {
        List<String> tokens = new ArrayList<>();
        for (String t : texto.toLowerCase(Locale.ROOT).split("[^\\p{L}\\p{N}#]+"))
            if (!t.isEmpty()) tokens.add(t);
        return tokens;
    }

    public static double calcular(String texto) {
        List<String> tokens = tokenizar(texto);
        int n = tokens.size();
        if (n == 0) return 0;

        double comprimento = Math.min(n / 120.0, 1.0);

        double diversidade = new HashSet<>(tokens).size() / (double) n;

        int stop = 0;
        for (String t : tokens) if (STOPWORDS.contains(t)) stop++;
        double conteudo = 1.0 - stop / (double) n;

        int frases = Math.max(1, texto.split("[.!?]+\\s*").length);
        double estrutura = Math.min((n / (double) frases) / 20.0, 1.0);

        double d = 0.30 * comprimento + 0.30 * diversidade + 0.25 * conteudo + 0.15 * estrutura;
        d -= penalizacaoPolarizacao(texto);
        return Math.max(0, Math.min(1, d));
    }

    /** 0..0.5 — quanto mais "grito", hashtag e virtue signalling, maior a penalização. */
    public static double penalizacaoPolarizacao(String texto) {
        double p = 0;
        int exclam = texto.length() - texto.replace("!", "").length();
        p += Math.min(exclam * 0.03, 0.15);

        int letras = 0, maiusculas = 0;
        for (char c : texto.toCharArray())
            if (Character.isLetter(c)) { letras++; if (Character.isUpperCase(c)) maiusculas++; }
        if (letras > 0 && maiusculas / (double) letras > 0.5) p += 0.10;

        int hashtags = texto.split("#", -1).length - 1;
        p += Math.min(hashtags * 0.03, 0.10);

        int emojis = (int) texto.codePoints().filter(cp -> cp >= 0x1F300).count();
        p += Math.min(emojis * 0.02, 0.05);

        String lower = texto.toLowerCase();
        for (String s : SINALIZACAO_VIRTUDE)
            if (lower.contains(s)) { p += 0.10; break; }

        return Math.min(p, 0.5);
    }

    /** Similaridade de Jaccard entre os vocabulários (sem stopwords) de dois textos. */
    public static double similaridade(String a, String b) {
        Set<String> sa = new HashSet<>(tokenizar(a)), sb = new HashSet<>(tokenizar(b));
        sa.removeAll(STOPWORDS);
        sb.removeAll(STOPWORDS);
        if (sa.isEmpty() || sb.isEmpty()) return 0;
        Set<String> inter = new HashSet<>(sa);
        inter.retainAll(sb);
        Set<String> uniao = new HashSet<>(sa);
        uniao.addAll(sb);
        return inter.size() / (double) uniao.size();
    }
}
