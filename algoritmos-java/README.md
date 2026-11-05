# Algoritmos SUBTERRA

Serviço Java 17 sem dependências externas que integra os algoritmos do projeto
com a aplicação PHP/MySQL. O PHP continua responsável por sessões, autorização e
persistência; o serviço só aceita ligações em `127.0.0.1`.

## Compilar e testar

```sh
./algoritmos-java/build.sh
```

O script compila os algoritmos, executa os testes e gera
`algoritmos-java/build/subterra-algoritmos.jar`.

## Executar

```sh
java -jar algoritmos-java/build/subterra-algoritmos.jar
```

O serviço escuta em `127.0.0.1:8787`. Define `SUBTERRA_ALGORITHM_PORT` para
escolher outra porta. No PHP, define
`SUBTERRA_ALGORITHM_URL=http://127.0.0.1:8787`.

Endpoints internos:

- `GET /health`
- `POST /api/rank` recebe `application/x-www-form-urlencoded`, com campos
  `posts[0][id]`, `posts[0][text]`, `posts[0][created_at]` (Unix, segundos)
  e `posts[0][upvotes]`; devolve IDs ordenados, densidade, classificação
  subterrânea e pontuação.
- `POST /api/moderate` recebe `text` e devolve o resultado heurístico.
- `POST /api/rate-limit` recebe `user_id` e devolve `allowed`.

O feed, criação de publicações e mensagens passam pelo serviço. Se este estiver
indisponível, a aplicação mostra um erro em vez de usar uma classificação
alternativa.

## Algoritmos incluídos

Anonimato e identicons; moderação heurística; densidade semântica; grafos e
comunidades; Max-Flow (Edmonds-Karp e Dinic) e Min-Cut; classificação
superfície/subterrâneo; ranking por profundidade com `PriorityQueue`; rate
limiting Token Bucket; fila FIFO de chat para demonstração.

## Limites e instalação

O classificador compara textos em pares, por isso o endpoint limita cada pedido
a 50 publicações. `SUBTERRA_BLOCKED_TERMS` permite configurar termos exatos
separados por vírgula; a moderação automática não substitui revisão humana. O
histórico de duplicados e os tokens do rate limit residem na memória e reiniciam
com o serviço. `ChatTempoReal` é apenas uma demonstração: o chat web continua a
usar polling PHP/MySQL, não WebSocket.

Para produção, instala o JAR em `/opt/subterra/subterra-algoritmos.jar` e copia
`deploy/subterra-algoritmos.service` para
`/etc/systemd/system/subterra-algoritmos.service`. Cria a conta e directoria de
sistema `subterra` antes de activar a unidade.
