package subterra.algoritmos;

import java.util.*;

/**
 * Alternativa ao Min-Cut referida no slide 5: COMMUNITY DETECTION por Label Propagation.
 *
 * Grafo de utilizadores: aresta u-v com peso = nº de interações (respostas, upvotes de profundidade).
 * 1. Cada utilizador começa com o seu próprio rótulo.
 * 2. Em ordem aleatória, cada utilizador adota o rótulo com MAIOR PESO SOMADO entre os vizinhos
 *    (empate -> menor rótulo, para ser determinístico).
 * 3. Repete até nenhum rótulo mudar (ou MAX_ITER).
 * Depois, cada comunidade é classificada como introspectiva se a densidade média
 * dos posts dos seus membros >= LIMIAR.
 */
public class Comunidades {

    private static final int MAX_ITER = 100;
    public static final double LIMIAR = 0.55;

    private final Map<String, Map<String, Double>> adj = new HashMap<>();

    public void adicionarInteracao(String u, String v, double peso) {
        adj.computeIfAbsent(u, k -> new HashMap<>()).merge(v, peso, Double::sum);
        adj.computeIfAbsent(v, k -> new HashMap<>()).merge(u, peso, Double::sum);
    }

    public Map<String, String> labelPropagation(long semente) {
        Map<String, String> rotulo = new HashMap<>();
        for (String u : adj.keySet()) rotulo.put(u, u);

        List<String> ordem = new ArrayList<>(adj.keySet());
        Random rnd = new Random(semente);
        for (int it = 0; it < MAX_ITER; it++) {
            Collections.shuffle(ordem, rnd);
            boolean mudou = false;
            for (String u : ordem) {
                Map<String, Double> soma = new HashMap<>();
                for (Map.Entry<String, Double> e : adj.get(u).entrySet())
                    soma.merge(rotulo.get(e.getKey()), e.getValue(), Double::sum);
                if (soma.isEmpty()) continue;

                String melhor = null;
                double melhorPeso = -1;
                for (Map.Entry<String, Double> e : soma.entrySet())
                    if (e.getValue() > melhorPeso
                            || (e.getValue() == melhorPeso && e.getKey().compareTo(melhor) < 0)) {
                        melhor = e.getKey();
                        melhorPeso = e.getValue();
                    }
                if (!melhor.equals(rotulo.get(u))) {
                    rotulo.put(u, melhor);
                    mudou = true;
                }
            }
            if (!mudou) break;
        }
        return rotulo;
    }

    /** Comunidade -> densidade média dos posts dos membros. */
    public static Map<String, Double> densidadePorComunidade(Map<String, String> rotulo, List<Post> posts) {
        Map<String, double[]> acc = new HashMap<>();    // [soma, contagem]
        for (Post p : posts) {
            String c = rotulo.get(p.getAutor());
            if (c == null) continue;
            double[] a = acc.computeIfAbsent(c, k -> new double[2]);
            a[0] += p.getDensidade();
            a[1]++;
        }
        Map<String, Double> res = new TreeMap<>();
        for (Map.Entry<String, double[]> e : acc.entrySet())
            res.put(e.getKey(), e.getValue()[0] / e.getValue()[1]);
        return res;
    }
}
