<?php

require_once __DIR__ . "/includes/moderacao.php";
require_once __DIR__ . "/includes/algoritmos.php";

exigirLogin();

header("Cache-Control: no-store, private, max-age=0");
header("Pragma: no-cache");

$podeModerar = eModerador();

$publicacoes = [];
$comentariosPorPublicacao = [];
$erroFeed = "";
$mensagem = obterMensagem();

$categorias = categoriasPublicacoes();

$usuarioId = (int) $_SESSION["usuario_id"];

try {
    $conexao = conectarBD();

    // =====================================
    // PUBLICACOES, VOTOS E CONTADORES
    // =====================================

    $sql = "
        SELECT
            p.id,
            p.titulo,
            p.conteudo,
            p.categoria,
            p.criado_em,

            (p.usuario_id = :dono_id) AS e_minha,

            COALESCE(v.pontuacao, 0) AS pontuacao,
            COALESCE(v.total_votos, 0) AS total_votos,
            COALESCE(v.upvotes_profundidade, 0) AS upvotes_profundidade,

            COALESCE(c.total_comentarios, 0)
                AS total_comentarios,

            COALESCE(meu.valor, 0) AS meu_voto

        FROM publicacoes p

        LEFT JOIN (
            SELECT
                publicacao_id,
                SUM(valor) AS pontuacao,
                COUNT(*) AS total_votos,
                SUM(CASE WHEN valor = 1 THEN 1 ELSE 0 END)
                    AS upvotes_profundidade
            FROM votos
            GROUP BY publicacao_id
        ) v ON v.publicacao_id = p.id

        LEFT JOIN (
            SELECT
                publicacao_id,
                COUNT(*) AS total_comentarios
            FROM comentarios
            GROUP BY publicacao_id
        ) c ON c.publicacao_id = p.id

        LEFT JOIN votos meu
            ON meu.publicacao_id = p.id
           AND meu.usuario_id = :votante_id

        WHERE p.estado = 'publicado'

        ORDER BY p.criado_em DESC, p.id DESC

        LIMIT 50
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->bindValue(
        ":dono_id",
        $usuarioId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ":votante_id",
        $usuarioId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $publicacoes = $stmt->fetchAll();
    $publicacoes = ordenarFeedComAlgoritmos($publicacoes);

    // =====================================
    // COMENTARIOS DAS PUBLICACOES
    // =====================================

    if (!empty($publicacoes)) {

        $ids = array_map(
            "intval",
            array_column($publicacoes, "id")
        );

        $marcadores = implode(
            ",",
            array_fill(0, count($ids), "?")
        );

        $sql = "
            SELECT
                c.id,
                c.publicacao_id,
                c.conteudo,
                c.criado_em,
                (c.usuario_id = ?) AS e_meu
            FROM comentarios c
            WHERE c.publicacao_id IN ($marcadores)
            ORDER BY
                c.publicacao_id ASC,
                c.criado_em ASC,
                c.id ASC
        ";

        $stmt = $conexao->prepare($sql);

        $stmt->execute(
            array_merge([$usuarioId], $ids)
        );

        foreach ($stmt->fetchAll() as $comentario) {

            $idPublicacao =
                (int) $comentario["publicacao_id"];

            $comentariosPorPublicacao[
                $idPublicacao
            ][] = $comentario;
        }
    }

} catch (Throwable $ex) {

    error_log(
        "[SUBTERRA FEED ETAPA 5] " .
        $ex->getMessage()
    );

    $erroFeed = "Nao foi possivel carregar o feed.";
}

?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="<?= escapar(tokenCSRF()) ?>"
    >

    <title>Feed | SUBTERRA</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/publicacoes.css">
    <link rel="stylesheet" href="css/chat-moderacao.css">

    <script src="js/app.js" defer></script>
    <script src="js/publicacoes.js" defer></script>
</head>

<body>

<header class="cabecalho">

    <a class="logo" href="feed.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <a class="nav-link" href="index.php">
            Inicio
        </a>

        <a class="nav-link" href="chat.php">
            Chat
        </a>

        <?php if ($podeModerar): ?>

            <a
                class="nav-link"
                href="Moderacao/index.php"
            >
                Moderacao
            </a>

        <?php endif; ?>

        <form
            method="POST"
            action="auth/logout.php"
            class="logout-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(tokenCSRF()) ?>"
            >

            <button
                type="submit"
                class="btn btn-outline"
            >
                Sair
            </button>

        </form>

    </nav>

</header>

<main class="feed-shell">

    <section class="feed-header">

        <p class="etiqueta">
            / SUBTERRA — COMUNIDADE
        </p>

        <h1>As ideias ganham voz.</h1>

        <p>
            Explora perspectivas, partilha
            pensamentos e participa em
            conversas anonimas.
        </p>

        <div class="user-chip">

            <span class="ponto"></span>

            Sessao privada:

            <strong>
                <?= escapar(
                    $_SESSION["pseudonimo"] ?? ""
                ) ?>
            </strong>

        </div>

        <div class="feed-toolbar">

            <a
                href="publicacoes/criar.php"
                class="btn btn-primary"
            >
                + Nova publicacao
            </a>

            <a
                href="chat.php"
                class="btn btn-outline"
            >
                Abrir chat
            </a>

            <span class="feed-indicator">
                FEED / ANONIMO
            </span>

        </div>

    </section>

    <?php if ($mensagem !== null): ?>

        <div
            class="alert <?= ($mensagem["tipo"] ?? "") === "sucesso"
                ? "alert-success"
                : "alert-error" ?>"
            role="status"
        >
            <?= escapar($mensagem["texto"] ?? "") ?>
        </div>

    <?php endif; ?>

    <section class="feed-content">

        <div class="section-heading">

            <h2>Feed de profundidade</h2>

            <span>
                <?= count($publicacoes) ?>
                CARREGADAS
            </span>

        </div>

        <?php if ($erroFeed !== ""): ?>

            <div class="alert alert-error" role="alert">
                <?= escapar($erroFeed) ?>
            </div>

        <?php elseif (empty($publicacoes)): ?>

            <div class="empty-state">

                <div class="empty-icon">/</div>

                <h3>
                    A comunidade aguarda
                    a primeira ideia.
                </h3>

                <p>
                    Cria a primeira publicacao
                    anonima da SUBTERRA.
                </p>

                <a
                    href="publicacoes/criar.php"
                    class="btn btn-primary empty-button"
                >
                    Criar publicacao
                </a>

            </div>

        <?php else: ?>

            <div class="post-list">

                <?php foreach ($publicacoes as $post): ?>

                    <?php
                        $postId = (int) $post["id"];

                        $meuVoto =
                            (int) $post["meu_voto"];

                        $eMinha =
                            (int) $post["e_minha"] === 1;

                        $comentarios =
                            $comentariosPorPublicacao[
                                $postId
                            ] ?? [];
                    ?>

                    <article
                        class="post"
                        id="post-<?= $postId ?>"
                    >

                        <!-- PUBLICACAO -->

                        <div class="post-meta">

                            <span class="anonymous-label">
                                ANONIMO
                            </span>

                            <span class="category-tag">
                                <?= escapar(
                                    $categorias[
                                        $post["categoria"]
                                    ] ?? $post["categoria"]
                                ) ?>
                            </span>

                            <span class="depth-tag <?= $post["e_subterranea"]
                                ? "is-subterranean"
                                : "" ?>">
                                <?= $post["e_subterranea"]
                                    ? "SUBTERRANEO"
                                    : "SUPERFICIE" ?>
                                ·
                                <?= number_format(
                                    $post["densidade_semantica"],
                                    2,
                                    ",",
                                    ""
                                ) ?>
                            </span>

                            <time>
                                <?= escapar(
                                    date(
                                        "d/m/Y H:i",
                                        strtotime(
                                            $post["criado_em"]
                                        )
                                    )
                                ) ?>
                            </time>

                        </div>

                        <h3 class="post-title">
                            <?= escapar($post["titulo"]) ?>
                        </h3>

                        <div class="post-body">
                            <?= nl2br(
                                escapar($post["conteudo"])
                            ) ?>
                        </div>

                        <!-- VOTOS -->

                        <div
                            class="vote-box"
                            data-vote-box
                            data-post-id="<?= $postId ?>"
                            data-my-vote="<?= $meuVoto ?>"
                        >

                            <button
                                type="button"
                                class="vote-button <?= $meuVoto === 1
                                    ? "is-active"
                                    : "" ?>"
                                data-vote-value="1"
                                aria-pressed="<?= $meuVoto === 1
                                    ? "true"
                                    : "false" ?>"
                            >
                                ▲ Apoiar
                            </button>

                            <strong
                                class="vote-score"
                                data-score
                            >
                                <?= (int) $post["pontuacao"] ?>
                            </strong>

                            <button
                                type="button"
                                class="vote-button <?= $meuVoto === -1
                                    ? "is-active"
                                    : "" ?>"
                                data-vote-value="-1"
                                aria-pressed="<?= $meuVoto === -1
                                    ? "true"
                                    : "false" ?>"
                            >
                                ▼ Discordar
                            </button>

                            <span
                                class="vote-total"
                                data-total-votes
                            >
                                <?= (int) $post["total_votos"] ?>
                                votos
                            </span>

                            <span
                                class="vote-feedback"
                                data-vote-feedback
                                role="status"
                                aria-live="polite"
                            ></span>

                        </div>

                        <!-- EDITAR / ELIMINAR -->

                        <?php if ($eMinha): ?>

                            <div class="post-actions">

                                <span class="owner-note">
                                    TUA PUBLICACAO
                                </span>

                                <a
                                    class="btn btn-outline btn-small"
                                    href="publicacoes/editar.php?id=<?= $postId ?>"
                                >
                                    Editar
                                </a>

                                <form
                                    method="POST"
                                    action="publicacoes/eliminar.php"
                                    class="delete-form"
                                    data-confirm-delete
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapar(tokenCSRF()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $postId ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline btn-small btn-danger"
                                    >
                                        Eliminar
                                    </button>

                                </form>

                            </div>

                        <?php else: ?>

                            <!-- DENUNCIAR PUBLICACAO -->

                            <details class="report-disclosure">

                                <summary>
                                    Denunciar publicacao
                                </summary>

                                <form
                                    method="POST"
                                    action="Denuncias/criar.php"
                                    class="report-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapar(tokenCSRF()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="publicacao_id"
                                        value="<?= $postId ?>"
                                    >

                                    <label for="motivo-<?= $postId ?>">
                                        Motivo da denuncia
                                    </label>

                                    <textarea
                                        class="input"
                                        id="motivo-<?= $postId ?>"
                                        name="motivo"
                                        placeholder="Explica por que motivo esta publicacao deve ser analisada..."
                                        minlength="10"
                                        maxlength="500"
                                        rows="3"
                                        required
                                    ></textarea>

                                    <button
                                        type="submit"
                                        class="btn btn-outline btn-small"
                                    >
                                        Enviar denuncia
                                    </button>

                                </form>

                            </details>

                        <?php endif; ?>

                        <!-- COMENTARIOS -->

                        <section class="comments-section">

                            <div class="comments-heading">

                                <h4>
                                    Comentarios

                                    <span>
                                        <?= (int) $post["total_comentarios"] ?>
                                    </span>
                                </h4>

                            </div>

                            <?php if (empty($comentarios)): ?>

                                <p class="comments-empty">
                                    Ainda nao existem comentarios.
                                    Participa na conversa.
                                </p>

                            <?php else: ?>

                                <div class="comments-list">

                                    <?php foreach (
                                        $comentarios as $comentario
                                    ): ?>

                                        <article class="comment">

                                            <div class="comment-meta">

                                                <strong>
                                                    ANONIMO
                                                </strong>

                                                <time>
                                                    <?= escapar(
                                                        date(
                                                            "d/m/Y H:i",
                                                            strtotime(
                                                                $comentario["criado_em"]
                                                            )
                                                        )
                                                    ) ?>
                                                </time>

                                            </div>

                                            <div class="comment-text">
                                                <?= nl2br(
                                                    escapar(
                                                        $comentario["conteudo"]
                                                    )
                                                ) ?>
                                            </div>

                                            <?php if (
                                                (int) $comentario["e_meu"] === 1
                                            ): ?>

                                                <div class="comment-actions">

                                                    <a
                                                        href="comentarios/editar.php?id=<?= (int) $comentario["id"] ?>"
                                                    >
                                                        Editar
                                                    </a>

                                                    <form
                                                        method="POST"
                                                        action="comentarios/eliminar.php"
                                                        data-confirm-delete-comment
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= escapar(tokenCSRF()) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $comentario["id"] ?>"
                                                        >

                                                        <button type="submit">
                                                            Eliminar
                                                        </button>

                                                    </form>

                                                </div>

                                            <?php endif; ?>

                                        </article>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                            <!-- CRIAR COMENTARIO -->

                            <form
                                method="POST"
                                action="comentarios/criar.php"
                                class="comment-form"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapar(tokenCSRF()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="publicacao_id"
                                    value="<?= $postId ?>"
                                >

                                <label for="comentario-<?= $postId ?>">
                                    Participar anonimamente
                                </label>

                                <textarea
                                    class="input"
                                    id="comentario-<?= $postId ?>"
                                    name="conteudo"
                                    placeholder="Escreve o teu comentario..."
                                    minlength="2"
                                    maxlength="1000"
                                    rows="3"
                                    required
                                ></textarea>

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-small"
                                >
                                    Publicar comentario
                                </button>

                            </form>

                        </section>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="rodape">

    <span>SUBTERRA © 2026</span>

    <span>
        AS IDEIAS TEM VOZ. OS NOMES NAO.
    </span>

</footer>

</body>
</html>