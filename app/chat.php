<?php

require_once __DIR__ . "/includes/moderacao.php";

exigirLogin();

$podeModerar = eModerador();

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

    <title>Chat | SUBTERRA</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/publicacoes.css">
    <link rel="stylesheet" href="css/chat-moderacao.css">

    <script src="js/chat.js" defer></script>
</head>

<body>

<header class="cabecalho">

    <a class="logo" href="feed.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <a class="nav-link" href="feed.php">
            Feed
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
                class="btn btn-outline"
                type="submit"
            >
                Sair
            </button>

        </form>

    </nav>

</header>

<main class="chat-shell">

    <section class="chat-intro">

        <p class="etiqueta">
            / SUBTERRA — COMUNIDADE
        </p>

        <h1>Conversa sem nomes.</h1>

        <p>
            Um espaco para partilhar ideias
            e conversar com a comunidade.
            As mensagens aparecem sem
            o pseudonimo do autor.
        </p>

        <div class="chat-status">

            <span class="ponto"></span>

            CHAT PUBLICO / ANONIMO

        </div>

    </section>

    <section class="chat-panel">

        <div class="chat-panel-header">

            <div>
                <h2>Sala principal</h2>

                <p>
                    Actualizacao automatica
                    a cada 4 segundos
                </p>
            </div>

            <span class="chat-room-label">
                # GERAL
            </span>

        </div>

        <div
            id="chatMessages"
            class="chat-messages"
            aria-label="Mensagens da comunidade"
            aria-live="off"
        >

            <p class="chat-placeholder">
                A carregar mensagens...
            </p>

        </div>

        <div class="chat-compose">

            <form id="chatForm">

                <label for="chatConteudo">
                    Nova mensagem
                </label>

                <textarea
                    id="chatConteudo"
                    name="conteudo"
                    class="input"
                    placeholder="Escreve uma mensagem anonima..."
                    maxlength="500"
                    rows="3"
                    required
                ></textarea>

                <div class="chat-compose-bottom">

                    <span
                        id="chatFeedback"
                        role="status"
                        aria-live="polite"
                    >
                        Maximo de 500 caracteres.
                    </span>

                    <button
                        id="chatEnviar"
                        type="submit"
                        class="btn btn-primary"
                    >
                        Enviar mensagem
                    </button>

                </div>

            </form>

        </div>

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