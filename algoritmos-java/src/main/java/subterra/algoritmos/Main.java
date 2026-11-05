package subterra.algoritmos;

import java.util.*;

/** Demonstração de todos os algoritmos do Subterra. */
public class Main {

    public static void main(String[] args) {
        long agora = 1_800_000_000L;
        Anonimato anon = new Anonimato();

        // ------------------------------------------------ 1. Anonimato
        System.out.println("=== 1. ANONIMATO ===");
        String[] users = new String[6];
        for (int i = 0; i < users.length; i++) users[i] = anon.gerarUsername();
        System.out.println("Usernames: " + Arrays.toString(users));
        System.out.println("Avatar de " + users[0] + ":\n" + Anonimato.avatar(users[0]));

        // ------------------------------------------------ 2. Moderação
        System.out.println("=== 2. MODERACAO ===");
        String[] textos = {
            "O homem do subsolo sabe que a consciência excessiva é uma doença. Quanto mais percebo "
          + "as minhas contradições, menos consigo agir. Talvez a lucidez seja apenas outra forma de "
          + "paralisia, uma prisão construída com argumentos que eu próprio escrevi durante noites sem sono.",

            "Sísifo empurra a pedra e eu empurro a segunda-feira. A diferença é que ele aceitou o absurdo "
          + "e encontrou nisso alguma liberdade silenciosa. Eu ainda finjo que a montanha vai acabar, "
          + "que existe um topo onde o esforço finalmente ganha sentido e alguém aplaude no fim.",

            "Acordei como Gregor Samsa, não transformado em inseto, mas em alguém que a família tolera. "
          + "O trabalho exige presença, a casa exige silêncio, e o quarto fechado tornou-se o único "
          + "lugar onde a metamorfose não precisa de explicações nem de pedidos de desculpa a ninguém.",

            "GRATIDÃO!!! Hoje foi um dia incrível incrível incrível, só positividade e good vibes para "
          + "todos vocês!!! Foco força fé sempre, nunca desistam dos sonhos, sigam para mais conteúdo "
          + "assim, partilha se concordas!!! #blessed #motivação #sucesso #vida #foco",

            "kkkk tipo hoje foi muito muito bom né tipo muito bom mesmo kkkk eu só quero dizer que foi "
          + "muito bom e que eu tô muito feliz tipo muito feliz mesmo né kkkk é isso é isso é isso",

            "A consciência excessiva parece uma doença, mas talvez seja só o preço de não mentir. "
          + "Prefiro a paralisia lúcida do subsolo à felicidade de quem nunca pergunta nada. "
          + "Pelo menos aqui as contradições têm nome e as noites sem sono produzem alguma verdade.",

            "Curto demais para entrar.",

            "Compra já aaaaaaaaaaaa promoção http://x.com http://y.com http://z.com clica clica clica "
          + "ganha dinheiro fácil hoje mesmo sem esforço nenhum garantido cem por cento real oferta "
          + "limitada corre corre corre agora mesmo antes que acabe",
        };
        Moderacao mod = new Moderacao();
        List<Post> posts = new ArrayList<>();
        int id = 1;
        for (int i = 0; i < textos.length; i++) {
            Moderacao.Resultado r = mod.avaliar(textos[i]);
            System.out.printf("texto %d -> %s%n", i, r);
            if (r == Moderacao.Resultado.APROVADO) {
                Post p = new Post(id++, users[i % users.length], textos[i], agora - 3600L * (i + 1));
                p.setDensidade(DensidadeSemantica.calcular(p.getTexto()));
                posts.add(p);
            }
        }
        System.out.println("Duplicado (repetir texto 0) -> " + mod.avaliar(textos[0]));

        // upvotes de profundidade
        int[] ups = {12, 7, 9, 1, 0, 4};
        for (int i = 0; i < posts.size(); i++)
            for (int k = 0; k < ups[i]; k++) posts.get(i).upvoteProfundidade();

        // ------------------------------------------------ 3. Densidade semântica
        System.out.println("\n=== 3. DENSIDADE SEMANTICA ===");
        for (Post p : posts)
            System.out.printf("post #%d densidade=%.3f  penalizacao=%.2f%n",
                    p.getId(), p.getDensidade(), DensidadeSemantica.penalizacaoPolarizacao(p.getTexto()));

        // ------------------------------------------------ 4. Max-Flow / Min-Cut
        System.out.println("\n=== 4. MAX-FLOW / MIN-CUT ===");
        double ek = ClassificadorSubterraneo.classificar(posts, ClassificadorSubterraneo.Metodo.EDMONDS_KARP);
        Map<Integer, Boolean> ladoEK = new HashMap<>();
        for (Post p : posts) ladoEK.put(p.getId(), p.isIntrospectivo());
        double di = ClassificadorSubterraneo.classificar(posts, ClassificadorSubterraneo.Metodo.DINIC);
        System.out.printf("Corte minimo Edmonds-Karp = %.6f%n", ek);
        System.out.printf("Corte minimo Dinic        = %.6f%n", di);
        boolean iguais = true;
        for (Post p : posts) iguais &= ladoEK.get(p.getId()) == p.isIntrospectivo();
        System.out.println("Mesma particao nos dois metodos: " + iguais);
        for (Post p : posts)
            System.out.printf("post #%d -> %s%n", p.getId(), p.isIntrospectivo() ? "SUBTERRANEO" : "superficie");

        // ------------------------------------------------ 5. Community detection
        System.out.println("\n=== 5. COMMUNITY DETECTION (Label Propagation) ===");
        Comunidades com = new Comunidades();
        com.adicionarInteracao(users[0], users[1], 5);
        com.adicionarInteracao(users[1], users[2], 4);
        com.adicionarInteracao(users[0], users[2], 3);
        com.adicionarInteracao(users[0], users[5], 4);
        com.adicionarInteracao(users[3], users[4], 6);
        com.adicionarInteracao(users[2], users[3], 0.5);   // ponte fraca
        Map<String, String> rot = com.labelPropagation(42);
        Map<String, List<String>> grupos = new TreeMap<>();
        for (Map.Entry<String, String> e : rot.entrySet())
            grupos.computeIfAbsent(e.getValue(), k -> new ArrayList<>()).add(e.getKey());
        grupos.forEach((c, m) -> System.out.println("comunidade " + c + ": " + m));
        Comunidades.densidadePorComunidade(rot, posts).forEach((c, d) ->
                System.out.printf("comunidade %s densidade media=%.3f -> %s%n", c, d,
                        d >= Comunidades.LIMIAR ? "INTROSPECTIVA" : "superficial"));

        // ------------------------------------------------ 6. Feed
        System.out.println("\n=== 6. FEED DE PROFUNDIDADE (PriorityQueue) ===");
        for (Post p : RankingFeed.feed(posts, agora)) System.out.println(p);
        System.out.println("Top-3:");
        for (Post p : RankingFeed.topK(posts, 3, agora)) System.out.println("  #" + p.getId());

        // ------------------------------------------------ 7. Rate limit + chat
        System.out.println("\n=== 7. CHAT + RATE LIMITING ===");
        RateLimiter rl = new RateLimiter(3, 1.0);      // rajada 3, 1 msg/s
        List<String> log = new ArrayList<>();
        ChatTempoReal chat = new ChatTempoReal(rl, (dest, m) -> log.add(dest + " <- " + m.autor() + ": " + m.texto()));
        chat.entrar("subsolo", users[0]);
        chat.entrar("subsolo", users[1]);
        chat.entrar("subsolo", users[2]);
        long t = 0;
        for (int i = 1; i <= 5; i++)
            System.out.println("msg " + i + " de " + users[0] + " (t=0s) aceite=" + chat.enviar("subsolo", users[0], "msg " + i, t));
        t += 2_000_000_000L;   // +2 segundos -> 2 tokens repostos
        System.out.println("msg 6 (t=2s) aceite=" + chat.enviar("subsolo", users[0], "msg 6", t));
        System.out.println("msg de nao inscrito aceite=" + chat.enviar("subsolo", users[4], "ola", t));
        System.out.println("Entregas: " + chat.despachar());
        log.forEach(l -> System.out.println("  " + l));
    }
}
