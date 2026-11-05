package subterra.algoritmos;

import java.util.*;
import java.util.function.BiConsumer;

/**
 * Chat em tempo real (slide 4): WebSocket + fila de mensagens.
 *
 * Fluxo:
 *   enviar()   -> RateLimiter -> Moderação leve -> fila FIFO da sala (LinkedList)
 *   despachar()-> retira da fila e entrega a cada sessão inscrita na sala
 *
 * Em Spring Boot, "entregar" corresponde a session.sendMessage(new TextMessage(...))
 * num WebSocketHandler; aqui é um BiConsumer para se poder testar sem servidor.
 * A fila desacopla receção de entrega: picos de mensagens não bloqueiam quem envia.
 */
public class ChatTempoReal {

    public record Mensagem(String sala, String autor, String texto, long ts) {}

    private final Map<String, LinkedList<Mensagem>> filas = new HashMap<>();
    private final Map<String, Set<String>> inscritos = new HashMap<>();
    private final RateLimiter limiter;
    private final BiConsumer<String, Mensagem> entregar;   // (destinatário, mensagem)

    public ChatTempoReal(RateLimiter limiter, BiConsumer<String, Mensagem> entregar) {
        this.limiter = limiter;
        this.entregar = entregar;
    }

    public void entrar(String sala, String user) {
        inscritos.computeIfAbsent(sala, k -> new LinkedHashSet<>()).add(user);
    }

    public void sair(String sala, String user) {
        Set<String> s = inscritos.get(sala);
        if (s != null) s.remove(user);
    }

    /** Devolve false se rejeitada (limite excedido, não inscrito, ou vazia). */
    public boolean enviar(String sala, String autor, String texto, long agoraNanos) {
        if (!inscritos.getOrDefault(sala, Set.of()).contains(autor)) return false;
        if (texto == null || texto.isBlank() || texto.length() > 2000) return false;
        if (!limiter.permitir(autor, agoraNanos)) return false;
        filas.computeIfAbsent(sala, k -> new LinkedList<>())
             .addLast(new Mensagem(sala, autor, texto.strip(), agoraNanos));
        return true;
    }

    /** Esvazia as filas e entrega a todos os inscritos exceto o autor. Devolve nº de entregas. */
    public int despachar() {
        int entregas = 0;
        for (Map.Entry<String, LinkedList<Mensagem>> e : filas.entrySet()) {
            LinkedList<Mensagem> fila = e.getValue();
            while (!fila.isEmpty()) {
                Mensagem m = fila.pollFirst();
                for (String u : inscritos.getOrDefault(e.getKey(), Set.of()))
                    if (!u.equals(m.autor())) {
                        entregar.accept(u, m);
                        entregas++;
                    }
            }
        }
        return entregas;
    }
}
