package subterra.algoritmos;

import java.util.*;

/**
 * Grafo dirigido com capacidades, em LISTA DE ADJACÊNCIA (HashMap<vértice, lista de arestas>).
 * Cada aresta guarda a sua aresta reversa, para o grafo residual do Max-Flow.
 */
public class Grafo {

    public static class Aresta {
        final int destino;
        double capacidade;
        double fluxo;
        Aresta reversa;

        Aresta(int destino, double capacidade) {
            this.destino = destino;
            this.capacidade = capacidade;
        }

        double residual() { return capacidade - fluxo; }
    }

    private final Map<Integer, List<Aresta>> adj = new HashMap<>();

    public void adicionarVertice(int v) {
        adj.putIfAbsent(v, new ArrayList<>());
    }

    /** Aresta dirigida u -> v com capacidade c (cria a reversa com capacidade 0). */
    public void adicionarAresta(int u, int v, double c) {
        validarCapacidade(c);
        adicionarVertice(u);
        adicionarVertice(v);
        Aresta ida = new Aresta(v, c);
        Aresta volta = new Aresta(u, 0);
        ida.reversa = volta;
        volta.reversa = ida;
        adj.get(u).add(ida);
        adj.get(v).add(volta);
    }

    /** Aresta não dirigida (afinidade simétrica entre dois posts/utilizadores). */
    public void adicionarArestaNaoDirigida(int u, int v, double c) {
        validarCapacidade(c);
        adicionarVertice(u);
        adicionarVertice(v);
        Aresta a = new Aresta(v, c);
        Aresta b = new Aresta(u, c);
        a.reversa = b;
        b.reversa = a;
        adj.get(u).add(a);
        adj.get(v).add(b);
    }

    public List<Aresta> vizinhos(int v) {
        return adj.getOrDefault(v, Collections.emptyList());
    }

    public Set<Integer> vertices() { return adj.keySet(); }

    private static void validarCapacidade(double capacidade) {
        if (!Double.isFinite(capacidade) || capacidade < 0) {
            throw new IllegalArgumentException("A capacidade da aresta deve ser finita e nao negativa.");
        }
    }

    public void limparFluxo() {
        for (List<Aresta> l : adj.values())
            for (Aresta a : l) a.fluxo = 0;
    }
}
