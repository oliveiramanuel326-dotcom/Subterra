package subterra.algoritmos;

import java.util.*;

/**
 * Feed de profundidade (slides 4, 5 e 7) com PriorityQueue (max-heap por score).
 *
 * score(p) = ( 0.55*densidade
 *            + 0.30*log(1 + upvotesProfundidade)/log(1 + 50)
 *            + 0.15*[p é subterrâneo] )
 *            * decaimento(idade)
 *
 * decaimento(h) = 1 / (1 + h/48)^0.8   (decai devagar: texto longo merece mais tempo de vida)
 *
 * "Posts muito curtos e rasos afundam": densidade < DENS_MIN -> score multiplicado por 0.1.
 * Top-K com min-heap de tamanho K: O(n log K).
 */
public class RankingFeed {

    public static final double DENS_MIN = 0.35;

    public static double score(Post p, long agora) {
        double up = Math.log1p(p.getUpvotesProfundidade()) / Math.log1p(50);
        double base = 0.55 * p.getDensidade() + 0.30 * Math.min(up, 1.0)
                + 0.15 * (p.isIntrospectivo() ? 1 : 0);
        double horas = Math.max(0, (agora - p.getCriadoEm()) / 3600.0);
        double decaimento = 1.0 / Math.pow(1 + horas / 48.0, 0.8);
        double s = base * decaimento;
        if (p.getDensidade() < DENS_MIN) s *= 0.1;          // afunda
        return s;
    }

    /** Feed completo ordenado (max-heap). */
    public static List<Post> feed(List<Post> posts, long agora) {
        PriorityQueue<Post> heap = new PriorityQueue<>(
                Comparator.comparingDouble(Post::getScore).reversed().thenComparingInt(Post::getId));
        for (Post p : posts) {
            p.setScore(score(p, agora));
            heap.add(p);
        }
        List<Post> res = new ArrayList<>();
        while (!heap.isEmpty()) res.add(heap.poll());
        return res;
    }

    /** Apenas os K melhores: min-heap de tamanho K. */
    public static List<Post> topK(List<Post> posts, int k, long agora) {
        if (k <= 0) return Collections.emptyList();
        PriorityQueue<Post> minHeap = new PriorityQueue<>(Comparator.comparingDouble(Post::getScore));
        for (Post p : posts) {
            p.setScore(score(p, agora));
            minHeap.add(p);
            if (minHeap.size() > k) minHeap.poll();
        }
        List<Post> res = new ArrayList<>(minHeap);
        res.sort(Comparator.comparingDouble(Post::getScore).reversed());
        return res;
    }
}
