package subterra.algoritmos;

import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Rate limiting por utilizador — TOKEN BUCKET (HashMap<id, balde>).
 *   capacidade = rajada máxima permitida
 *   taxa       = tokens repostos por segundo
 * Cada mensagem gasta 1 token; sem token -> mensagem rejeitada.
 *
 * Isto limita por UTILIZADOR na aplicação. O limite por PROCESSO (CPU/memória do serviço)
 * é feito pelo Linux com cgroups via systemd (ver deploy/subterra.service).
 */
public class RateLimiter {

    private static class Balde {
        double tokens;
        long ultimoNanos;
        long ultimaUtilizacao;
        Balde(double t, long n) { tokens = t; ultimoNanos = n; }
    }

    private final double capacidade;
    private final double taxaPorSegundo;
    private static final int MAX_UTILIZADORES = 10_000;
    private final Map<String, Balde> baldes = new LinkedHashMap<>();
    private final long expiracaoNanos;
    private int chamadas;

    public RateLimiter(double capacidade, double taxaPorSegundo) {
        if (!Double.isFinite(capacidade) || capacidade < 1
                || !Double.isFinite(taxaPorSegundo) || taxaPorSegundo <= 0) {
            throw new IllegalArgumentException("Configuracao de rate limit invalida.");
        }
        this.capacidade = capacidade;
        this.taxaPorSegundo = taxaPorSegundo;
        this.expiracaoNanos = (long) Math.max(
                60_000_000_000L,
                Math.min(Double.MAX_VALUE, capacidade / taxaPorSegundo * 4_000_000_000.0)
        );
    }

    public synchronized boolean permitir(String user, long agoraNanos) {
        if (user == null || user.isBlank()) {
            throw new IllegalArgumentException("Identificador de utilizador obrigatorio.");
        }
        if (++chamadas % 256 == 0) limpar(agoraNanos);

        Balde b = baldes.get(user);
        if (b == null) {
            if (baldes.size() >= MAX_UTILIZADORES) {
                Iterator<String> oldest = baldes.keySet().iterator();
                if (oldest.hasNext()) {
                    oldest.next();
                    oldest.remove();
                }
            }
            b = new Balde(capacidade, agoraNanos);
            baldes.put(user, b);
        }
        double seg = Math.max(0, (agoraNanos - b.ultimoNanos) / 1e9);
        b.tokens = Math.min(capacidade, b.tokens + seg * taxaPorSegundo);
        b.ultimoNanos = Math.max(b.ultimoNanos, agoraNanos);
        b.ultimaUtilizacao = agoraNanos;
        if (b.tokens >= 1) {
            b.tokens -= 1;
            return true;
        }
        return false;
    }

    public boolean permitir(String user) { return permitir(user, System.nanoTime()); }

    private void limpar(long agoraNanos) {
        Iterator<Map.Entry<String, Balde>> iterator = baldes.entrySet().iterator();
        while (iterator.hasNext()) {
            Map.Entry<String, Balde> entrada = iterator.next();
            if (agoraNanos - entrada.getValue().ultimaUtilizacao > expiracaoNanos) {
                iterator.remove();
            }
        }
    }
}
