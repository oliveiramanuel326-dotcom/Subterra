package subterra.algoritmos;

import java.util.*;

/**
 * Separa posts em dois clusters — SUPERFÍCIE vs SUBTERRÂNEO — por MIN-CUT (graph cut).
 *
 * Construção do grafo:
 *   S = âncora "superficial",  T = âncora "introspectiva"
 *   S -> p  capacidade = 1 - profundidade(p)      (custo de pôr p no lado introspectivo)
 *   p -> T  capacidade = profundidade(p)          (custo de pôr p no lado superficial)
 *   p <-> q capacidade = LAMBDA * similaridade(p,q)  (custo de separar posts parecidos)
 *
 *   profundidade(p) = 0.8*densidade + 0.2*min(upvotesProfundidade/10, 1)
 *
 * O corte mínimo minimiza a soma destes custos: cada post fica do lado mais coerente
 * com a sua profundidade E com a dos posts semelhantes. Posts no lado T = subterrâneos.
 */
public class ClassificadorSubterraneo {

    public static final int S = -1;
    public static final int T = -2;
    private static final double LAMBDA = 0.5;
    private static final double SIM_MINIMA = 0.05;

    public enum Metodo { EDMONDS_KARP, DINIC }

    public static double profundidade(Post p) {
        return 0.8 * p.getDensidade() + 0.2 * Math.min(p.getUpvotesProfundidade() / 10.0, 1.0);
    }

    public static Grafo construirGrafo(List<Post> posts) {
        Grafo g = new Grafo();
        g.adicionarVertice(S);
        g.adicionarVertice(T);
        for (Post p : posts) {
            double prof = profundidade(p);
            g.adicionarAresta(S, p.getId(), 1.0 - prof);
            g.adicionarAresta(p.getId(), T, prof);
        }
        for (int i = 0; i < posts.size(); i++)
            for (int j = i + 1; j < posts.size(); j++) {
                double sim = DensidadeSemantica.similaridade(posts.get(i).getTexto(), posts.get(j).getTexto());
                if (sim >= SIM_MINIMA)
                    g.adicionarArestaNaoDirigida(posts.get(i).getId(), posts.get(j).getId(), LAMBDA * sim);
            }
        return g;
    }

    /** Marca cada post como introspectivo/superficial. Devolve o valor do corte mínimo. */
    public static double classificar(List<Post> posts, Metodo metodo) {
        Grafo g = construirGrafo(posts);
        double corte = (metodo == Metodo.DINIC) ? MaxFlow.dinic(g, S, T) : MaxFlow.edmondsKarp(g, S, T);
        Set<Integer> ladoS = MaxFlow.ladoS(g, S);
        for (Post p : posts) p.setIntrospectivo(!ladoS.contains(p.getId()));
        return corte;
    }
}
