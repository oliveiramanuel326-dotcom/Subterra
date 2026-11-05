package subterra.algoritmos;

import java.util.*;

/**
 * Max-Flow / Min-Cut. Duas implementações que dão o MESMO valor de fluxo máximo:
 *   1) Edmonds-Karp  : caminhos aumentantes por BFS              O(V * E^2)
 *   2) Dinic         : grafo de níveis (BFS) + blocking flow (DFS) O(V^2 * E)
 *
 * Min-Cut: após o fluxo máximo, os vértices alcançáveis a partir de S no grafo
 * residual formam o lado S do corte; os restantes formam o lado T.
 * (Teorema Max-Flow = Min-Cut.)
 */
public class MaxFlow {

    private static final double EPS = 1e-9;

    // ---------------------------------------------------------------- Edmonds-Karp
    public static double edmondsKarp(Grafo g, int s, int t) {
        g.limparFluxo();
        double total = 0;
        while (true) {
            // BFS: guarda a aresta usada para chegar a cada vértice
            Map<Integer, Grafo.Aresta> pai = new HashMap<>();
            Map<Integer, Integer> origem = new HashMap<>();
            LinkedList<Integer> fila = new LinkedList<>();
            fila.add(s);
            origem.put(s, s);
            while (!fila.isEmpty() && !origem.containsKey(t)) {
                int u = fila.poll();
                for (Grafo.Aresta a : g.vizinhos(u)) {
                    if (a.residual() > EPS && !origem.containsKey(a.destino)) {
                        origem.put(a.destino, u);
                        pai.put(a.destino, a);
                        fila.add(a.destino);
                    }
                }
            }
            if (!origem.containsKey(t)) break;          // não há caminho aumentante

            // gargalo do caminho
            double gargalo = Double.MAX_VALUE;
            for (int v = t; v != s; v = origem.get(v))
                gargalo = Math.min(gargalo, pai.get(v).residual());

            // aumentar fluxo
            for (int v = t; v != s; v = origem.get(v)) {
                Grafo.Aresta a = pai.get(v);
                a.fluxo += gargalo;
                a.reversa.fluxo -= gargalo;
            }
            total += gargalo;
        }
        return total;
    }

    // ---------------------------------------------------------------- Dinic
    public static double dinic(Grafo g, int s, int t) {
        g.limparFluxo();
        double total = 0;
        Map<Integer, Integer> nivel;
        while ((nivel = niveis(g, s)).containsKey(t)) {
            Map<Integer, Integer> ponteiro = new HashMap<>();
            double f;
            while ((f = dfsDinic(g, s, t, Double.MAX_VALUE, nivel, ponteiro)) > EPS)
                total += f;
        }
        return total;
    }

    private static Map<Integer, Integer> niveis(Grafo g, int s) {
        Map<Integer, Integer> nivel = new HashMap<>();
        LinkedList<Integer> fila = new LinkedList<>();
        nivel.put(s, 0);
        fila.add(s);
        while (!fila.isEmpty()) {
            int u = fila.poll();
            for (Grafo.Aresta a : g.vizinhos(u))
                if (a.residual() > EPS && !nivel.containsKey(a.destino)) {
                    nivel.put(a.destino, nivel.get(u) + 1);
                    fila.add(a.destino);
                }
        }
        return nivel;
    }

    private static double dfsDinic(Grafo g, int u, int t, double f,
                                   Map<Integer, Integer> nivel, Map<Integer, Integer> ptr) {
        if (u == t) return f;
        List<Grafo.Aresta> lista = g.vizinhos(u);
        for (int i = ptr.getOrDefault(u, 0); i < lista.size(); i++, ptr.put(u, i)) {
            Grafo.Aresta a = lista.get(i);
            Integer nv = nivel.get(a.destino);
            if (a.residual() > EPS && nv != null && nv == nivel.get(u) + 1) {
                double d = dfsDinic(g, a.destino, t, Math.min(f, a.residual()), nivel, ptr);
                if (d > EPS) {
                    a.fluxo += d;
                    a.reversa.fluxo -= d;
                    return d;
                }
            }
        }
        ptr.put(u, lista.size());
        return 0;
    }

    // ---------------------------------------------------------------- Min-Cut
    /** Chamar DEPOIS de edmondsKarp/dinic. Devolve o lado S do corte mínimo. */
    public static Set<Integer> ladoS(Grafo g, int s) {
        Set<Integer> visitados = new HashSet<>();
        LinkedList<Integer> fila = new LinkedList<>();
        fila.add(s);
        visitados.add(s);
        while (!fila.isEmpty()) {
            int u = fila.poll();
            for (Grafo.Aresta a : g.vizinhos(u))
                if (a.residual() > EPS && visitados.add(a.destino))
                    fila.add(a.destino);
        }
        return visitados;
    }
}
