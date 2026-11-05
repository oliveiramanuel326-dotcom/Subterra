package subterra.algoritmos;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.Base64;
import java.util.*;
import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.PBEKeySpec;

/**
 * Anonimato total (slides 2 e 4).
 *  - gerarUsername(): adjetivo + substantivo (vocabulário existencialista) + 4 dígitos,
 *                     a partir de SecureRandom; reintenta se já existir.
 *  - avatar():        identicon 5x5 simétrico derivado do SHA-256 do username.
 *                     Determinístico, não usa foto nem dado real.
 *  - hashSenha():     PBKDF2 com SHA-256, sal aleatório e comparação constante.
 * Nenhum nome, email ou IP é associado ao username.
 */
public class Anonimato {

    private static final String[] ADJ = {"silencioso", "subterraneo", "absurdo", "insone",
            "lucido", "errante", "ironico", "sombrio", "estrangeiro", "inquieto"};
    private static final String[] SUBST = {"rato", "inseto", "sisifo", "escrivao",
            "andarilho", "corvo", "eco", "abismo", "porao", "vulto"};

    private final SecureRandom rnd = new SecureRandom();
    private final Set<String> usados = new HashSet<>();
    private static final int ITERACOES = 210_000;

    public synchronized String gerarUsername() {
        String u;
        do {
            u = ADJ[rnd.nextInt(ADJ.length)] + "_" + SUBST[rnd.nextInt(SUBST.length)]
                    + "_" + String.format("%04d", rnd.nextInt(10000));
        } while (!usados.add(u));
        return u;
    }

    /** Identicon 5x5: colunas 0-2 vêm dos bits do hash, colunas 3-4 espelham 1-0. */
    public static String avatar(String username) {
        byte[] h = sha256(username);
        boolean[][] m = new boolean[5][5];
        int bit = 0;
        for (int lin = 0; lin < 5; lin++)
            for (int col = 0; col < 3; col++, bit++) {
                boolean on = ((h[bit / 8] >> (bit % 8)) & 1) == 1;
                m[lin][col] = on;
                m[lin][4 - col] = on;
            }
        StringBuilder sb = new StringBuilder();
        for (boolean[] linha : m) {
            for (boolean c : linha) sb.append(c ? "██" : "  ");
            sb.append('\n');
        }
        return sb.toString();
    }

    public static String hashSenha(String senha, byte[] sal) {
        if (senha == null || sal == null || sal.length < 16) {
            throw new IllegalArgumentException("A password e um sal aleatorio de 16 bytes sao obrigatorios.");
        }

        byte[] hash = derivarSenha(senha, sal, ITERACOES);
        return "pbkdf2-sha256$" + ITERACOES + "$"
                + Base64.getEncoder().withoutPadding().encodeToString(sal) + "$"
                + Base64.getEncoder().withoutPadding().encodeToString(hash);
    }

    public static boolean verificarSenha(String senha, String armazenada) {
        if (senha == null || armazenada == null) return false;

        String[] partes = armazenada.split("\\$", -1);
        if (partes.length != 4 || !partes[0].equals("pbkdf2-sha256")) return false;

        try {
            int iteracoes = Integer.parseInt(partes[1]);
            if (iteracoes < 100_000 || iteracoes > 1_000_000) return false;

            byte[] sal = Base64.getDecoder().decode(partes[2]);
            byte[] esperado = Base64.getDecoder().decode(partes[3]);
            if (sal.length < 16 || esperado.length != 32) return false;

            return MessageDigest.isEqual(
                    esperado,
                    derivarSenha(senha, sal, iteracoes)
            );
        } catch (IllegalArgumentException ex) {
            return false;
        }
    }

    private static byte[] derivarSenha(String senha, byte[] sal, int iteracoes) {
        PBEKeySpec spec = new PBEKeySpec(senha.toCharArray(), sal, iteracoes, 256);
        try {
            return SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256")
                    .generateSecret(spec)
                    .getEncoded();
        } catch (Exception ex) {
            throw new IllegalStateException("Nao foi possivel derivar a password.", ex);
        } finally {
            spec.clearPassword();
        }
    }

    private static byte[] sha256(String s) { return sha256(s.getBytes(StandardCharsets.UTF_8)); }

    private static byte[] sha256(byte[] b) {
        try {
            return MessageDigest.getInstance("SHA-256").digest(b);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }
}
