<?php

require_once __DIR__ . "/../includes/moderacao.php";

exigirModerador();

$mensagem = obterMensagem();
$cmsUrl = rtrim(
    getenv("SUBTERRA_CMS_URL") ?: "http://127.0.0.1:8055",
    "/"
);

$denuncias = [];
$publicacoesOcultas = [];
$mensagensChat = [];

$erro = "";

try {
    $conexao = conectarBD();

    // DENUNCIAS PENDENTES
    $sql = "
        SELECT
            d.id,
            d.publicacao_id,
            d.motivo,
            d.criado_em,
            p.titulo,
            p.estado AS estado_publicacao
        FROM denuncias d
        INNER JOIN publicacoes p
            ON p.id = d.publicacao_id
        WHERE d.estado = 'pendente'
        ORDER BY d.id ASC
        LIMIT 50
    ";

    $denuncias = $conexao
        ->query($sql)
        ->fetchAll();

    // PUBLICACOES OCULTAS
    $sql = "
        SELECT
            id,
            titulo,
            criado_em
        FROM publicacoes
        WHERE estado = 'oculto'
        ORDER BY id DESC
        LIMIT 50
    ";

    $publicacoesOcultas = $conexao
        ->query($sql)
        ->fetchAll();

    // MENSAGENS RECENTES DO CHAT
    $sql = "
        SELECT
            id,
            conteudo,
            criado_em
        FROM mensagens
        ORDER BY id DESC
        LIMIT 30
    ";

    $mensagensChat = $conexao
        ->query($sql)
        ->fetchAll();

} catch (PDOException $ex) {

    error_log(
        "[SUBTERRA PAINEL MODERACAO] " .
        $ex->getMessage()
    );

    $erro = "Nao foi possivel carregar "
          . "o painel de moderacao.";
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

    <title>Moderacao | SUBTERRA</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/publicacoes.css">
    <link rel="stylesheet" href="../css/chat-moderacao.css">

</head>

<body>

<header class="cabecalho">

    <a class="logo" href="../feed.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <a class="nav-link" href="../feed.php">
            Feed
        </a>

        <a class="nav-link" href="../chat.php">
            Chat
        </a>

    </nav>

</header>

<main class="mod-shell">

    <section class="mod-heading">

        <p class="etiqueta">
            / SUBTERRA — ADMINISTRACAO
        </p>

        <h1>Painel de moderacao.</h1>

        <p>
            Analisa denuncias, gere publicacoes
            ocultas e acompanha as mensagens
            da comunidade.
        </p>

        <div class="mod-role">
            ACESSO:
            <?= escapar(
                strtoupper($_SESSION["papel"] ?? "")
            ) ?>
        </div>

        <a
            class="btn btn-outline btn-small"
            href="<?= escapar($cmsUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            Abrir CMS Directus
        </a>

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

    <?php if ($erro !== ""): ?>

        <div class="alert alert-error" role="alert">
            <?= escapar($erro) ?>
        </div>

    <?php else: ?>

        <!-- DENUNCIAS -->

        <section class="mod-section">

            <div class="mod-section-heading">

                <h2>Denuncias pendentes</h2>

                <span>
                    <?= count($denuncias) ?>
                    CARREGADAS
                </span>

            </div>

            <?php if (empty($denuncias)): ?>

                <p class="mod-empty">
                    Nao existem denuncias pendentes.
                </p>

            <?php else: ?>

                <?php foreach ($denuncias as $denuncia): ?>

                    <article class="mod-card">

                        <div class="mod-card-meta">

                            <span>
                                DENUNCIA #<?= (int) $denuncia["id"] ?>
                            </span>

                            <span>
                                <?= escapar($denuncia["criado_em"]) ?>
                            </span>

                        </div>

                        <h3>
                            <?= escapar($denuncia["titulo"]) ?>
                        </h3>

                        <p class="mod-card-status">
                            Estado da publicacao:
                            <?= escapar(
                                $denuncia["estado_publicacao"]
                            ) ?>
                        </p>

                        <p class="mod-reason">
                            <?= nl2br(
                                escapar($denuncia["motivo"])
                            ) ?>
                        </p>

                        <div class="mod-actions">

                            <?php if (
                                $denuncia["estado_publicacao"] === "publicado"
                            ): ?>

                                <form
                                    method="POST"
                                    action="acao.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapar(tokenCSRF()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="ocultar_publicacao"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $denuncia["publicacao_id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-outline btn-small btn-danger"
                                    >
                                        Ocultar publicacao
                                    </button>

                                </form>

                            <?php endif; ?>

                            <form
                                method="POST"
                                action="acao.php"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapar(tokenCSRF()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="resolver_denuncia"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $denuncia["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline btn-small"
                                >
                                    Resolver sem ocultar
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

        <!-- PUBLICACOES OCULTAS -->

        <section class="mod-section">

            <div class="mod-section-heading">

                <h2>Publicacoes ocultas</h2>

                <span>
                    <?= count($publicacoesOcultas) ?>
                    CARREGADAS
                </span>

            </div>

            <?php if (empty($publicacoesOcultas)): ?>

                <p class="mod-empty">
                    Nao existem publicacoes ocultas.
                </p>

            <?php else: ?>

                <?php foreach (
                    $publicacoesOcultas as $publicacao
                ): ?>

                    <article class="mod-card">

                        <div class="mod-card-meta">
                            PUBLICACAO #<?= (int) $publicacao["id"] ?>
                        </div>

                        <h3>
                            <?= escapar($publicacao["titulo"]) ?>
                        </h3>

                        <form
                            method="POST"
                            action="acao.php"
                            class="mod-actions"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapar(tokenCSRF()) ?>"
                            >

                            <input
                                type="hidden"
                                name="acao"
                                value="restaurar_publicacao"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $publicacao["id"] ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-primary btn-small"
                            >
                                Restaurar publicacao
                            </button>

                        </form>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

        <!-- MODERACAO DO CHAT -->

        <section class="mod-section">

            <div class="mod-section-heading">

                <h2>Mensagens recentes do chat</h2>

                <span>
                    <?= count($mensagensChat) ?>
                    CARREGADAS
                </span>

            </div>

            <?php if (empty($mensagensChat)): ?>

                <p class="mod-empty">
                    Ainda nao existem mensagens.
                </p>

            <?php else: ?>

                <?php foreach (
                    $mensagensChat as $mensagemChat
                ): ?>

                    <article class="mod-card">

                        <div class="mod-card-meta">

                            <span>
                                MENSAGEM #<?= (int) $mensagemChat["id"] ?>
                            </span>

                            <span>
                                <?= escapar($mensagemChat["criado_em"]) ?>
                            </span>

                        </div>

                        <p class="mod-reason">
                            <?= nl2br(
                                escapar($mensagemChat["conteudo"])
                            ) ?>
                        </p>

                        <form
                            method="POST"
                            action="acao.php"
                            class="mod-actions"
                            onsubmit="return confirm('Eliminar esta mensagem do chat?');"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapar(tokenCSRF()) ?>"
                            >

                            <input
                                type="hidden"
                                name="acao"
                                value="eliminar_mensagem"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $mensagemChat["id"] ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-outline btn-small btn-danger"
                            >
                                Eliminar mensagem
                            </button>

                        </form>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<footer class="rodape">

    <span>SUBTERRA © 2026</span>

    <span>PAINEL RESTRITO</span>

</footer>

</body>
</html>