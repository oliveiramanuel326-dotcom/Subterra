<?php

require_once __DIR__ . "/../includes/bootstrap.php";

if (estaAutenticado()) {
    redireccionar("feed.php");
}

$erro = "";
$pseudonimo = "";

// Limite basico por sessao
$agora = time();

$falhas = $_SESSION["falhas_login"] ?? [];

$falhas = array_values(array_filter(
    $falhas,
    function ($momento) use ($agora) {
        return is_int($momento)
            && $momento > ($agora - 900);
    }
));

$_SESSION["falhas_login"] = $falhas;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? null;

    $pseudonimo = is_string(
        $_POST["pseudonimo"] ?? null
    ) ? trim($_POST["pseudonimo"]) : "";

    $senha = is_string(
        $_POST["senha"] ?? null
    ) ? $_POST["senha"] : "";

    if (!validarCSRF($token)) {

        $erro = "Pedido invalido. Actualiza a pagina.";

    } elseif (count($falhas) >= 5) {

        $erro = "Muitas tentativas. Aguarda ate "
              . "15 minutos antes de tentar novamente.";

    } elseif ($pseudonimo === "" || $senha === "") {

        $erro = "Preenche todos os campos.";

    } else {

        try {

            $conexao = conectarBD();

            $sql = "
                SELECT
                    id,
                    pseudonimo,
                    senha_hash,
                    papel
                FROM usuarios
                WHERE pseudonimo = :pseudonimo
                LIMIT 1
            ";

            $stmt = $conexao->prepare($sql);

            $stmt->execute([
                ":pseudonimo" => $pseudonimo
            ]);

            $usuario = $stmt->fetch();

            if (
                $usuario &&
                password_verify(
                    $senha,
                    $usuario["senha_hash"]
                )
            ) {

                session_regenerate_id(true);

                $_SESSION["usuario_id"] =
                    (int) $usuario["id"];

                $_SESSION["pseudonimo"] =
                    $usuario["pseudonimo"];

                $_SESSION["papel"] =
                    $usuario["papel"];

                unset(
                    $_SESSION["falhas_login"],
                    $_SESSION["csrf_token"]
                );

                redireccionar("feed.php");

            } else {

                $_SESSION["falhas_login"][] =
                    time();

                $erro = "Pseudonimo ou password "
                      . "incorrectos.";
            }

        } catch (PDOException $ex) {

            error_log(
                "[SUBTERRA LOGIN] " .
                $ex->getMessage()
            );

            $erro = "Ocorreu um erro no servidor.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Entrar | SUBTERRA</title>

    <link rel="stylesheet" href="../css/style.css">

    <script src="../js/app.js" defer></script>
</head>

<body>

<header class="cabecalho">

    <a class="logo" href="../index.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">
        <a class="nav-link" href="registo.php">
            Criar conta
        </a>
    </nav>

</header>

<main class="auth-layout">

    <section class="auth-card">

        <div class="auth-intro">

            <p class="etiqueta">
                / ACESSO A COMUNIDADE
            </p>

            <h1>Bem-vindo de volta.</h1>

            <p>
                Entra com o teu pseudonimo
                para aceder a SUBTERRA.
            </p>

        </div>

        <?php if ($erro !== ""): ?>

            <div class="alert alert-error"
                 role="alert">
                <?= escapar($erro) ?>
            </div>

        <?php endif; ?>

        <form method="POST"
              class="auth-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(tokenCSRF()) ?>"
            >

            <div class="field">

                <label for="pseudonimo">
                    Pseudonimo
                </label>

                <input
                    class="input"
                    type="text"
                    id="pseudonimo"
                    name="pseudonimo"
                    value="<?= escapar($pseudonimo) ?>"
                    placeholder="O teu pseudonimo"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="field">

                <label for="senha">
                    Password
                </label>

                <div class="input-wrap">

                    <input
                        class="input"
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="A tua password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-toggle-password="senha"
                        aria-label="Mostrar password"
                    >
                        Mostrar
                    </button>

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary btn-full"
            >
                Entrar na SUBTERRA
            </button>

        </form>

        <p class="auth-footer">
            Ainda nao tens conta?
            <a href="registo.php">Criar conta</a>
        </p>

    </section>

</main>

<footer class="rodape">
    <span>SUBTERRA © 2026</span>
    <span>ACESSO PRIVADO</span>
</footer>

</body>
</html>