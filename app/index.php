<?php

require_once __DIR__ . "/includes/bootstrap.php";

?>
<!DOCTYPE html>
<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>SUBTERRA | As ideias tem voz</title>

    <link rel="stylesheet" href="css/style.css">

    <script src="js/app.js" defer></script>

</head>

<body>

<header class="cabecalho">

    <a class="logo" href="index.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <?php if (estaAutenticado()): ?>

            <a class="nav-link" href="feed.php">
                Feed
            </a>

            <a class="nav-link" href="chat.php">
                Chat
            </a>

        <?php else: ?>

            <a class="nav-link" href="auth/login.php">
                Entrar
            </a>

            <a class="btn btn-outline"
               href="auth/registo.php">
                Criar conta
            </a>

        <?php endif; ?>

    </nav>

</header>

<main class="principal">

    <section class="hero">

        <p class="etiqueta">
            / UMA COMUNIDADE DE IDEIAS
        </p>

        <h1 class="hero-title">

            As ideias tem voz.

            <span>Os nomes nao.</span>

        </h1>

        <p class="hero-subtitle">
            Um espaco para partilhar ideias,
            explorar perspectivas e participar
            em conversas sem exposicao publica
            da identidade.
        </p>

        <div class="hero-actions">

            <?php if (estaAutenticado()): ?>

                <a
                    class="btn btn-primary"
                    href="feed.php"
                >
                    Entrar no feed
                </a>

            <?php else: ?>

                <a
                    class="btn btn-primary"
                    href="auth/registo.php"
                >
                    Comecar agora
                </a>

                <a
                    class="btn btn-outline"
                    href="auth/login.php"
                >
                    Ja tenho conta
                </a>

            <?php endif; ?>

        </div>

    </section>

    <section class="painel">

        <div class="painel-cabecalho">

            <span>&gt; SUBTERRA / SESSAO ANONIMA</span>

            <span>/b/</span>

        </div>

        <h2>Uma identidade privada.</h2>

        <p class="painel-descricao">
            As publicacoes e conversas aparecem
            sem o pseudonimo do autor. A conta
            continua privada e permite moderar
            a comunidade.
        </p>

        <div class="system-line">

            <span class="ponto"></span>

            PUBLICACOES SEM AUTORIA VISIVEL

        </div>

    </section>

    <section class="modulos">

        <p class="etiqueta">
            / ARQUITECTURA
        </p>

        <h2>Explora a SUBTERRA</h2>

        <div class="grelha">

            <a class="modulo" href="feed.php">

                <h3>01 / Publicacoes</h3>

                <p>
                    Entra no feed, encontra conversas
                    e publica sem mostrar o teu pseudonimo.
                </p>

                <span>ABRIR FEED →</span>

            </a>

            <a class="modulo" href="feed.php">

                <h3>02 / Conversas</h3>

                <p>
                    Partilha ideias, comenta e vota
                    nas publicacoes da comunidade.
                </p>

                <span>VER CONVERSAS →</span>

            </a>

            <a class="modulo" href="<?= estaAutenticado()
                ? "publicacoes/criar.php"
                : "auth/registo.php" ?>">

                <h3>03 / Participar</h3>

                <p>
                    <?= estaAutenticado()
                        ? "Abre uma nova publicacao para a comunidade."
                        : "Cria uma conta para publicar, comentar e votar." ?>
                </p>

                <span><?= estaAutenticado()
                    ? "NOVA PUBLICACAO →"
                    : "CRIAR CONTA →" ?></span>

            </a>

        </div>

    </section>

</main>

<footer class="rodape">

    <span>SUBTERRA © 2026</span>

    <span>
        HTML / CSS / JS / PHP / MYSQL
    </span>

</footer>

</body>
</html>
