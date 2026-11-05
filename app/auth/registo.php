<?php

require_once __DIR__ . "/../includes/bootstrap.php";

// Se ja estiver autenticado
if (estaAutenticado()) {
    redireccionar("feed.php");
}

$erro = "";
$pseudonimo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? null;

    $pseudonimo = is_string(
        $_POST["pseudonimo"] ?? null
    ) ? trim($_POST["pseudonimo"]) : "";

    $senha = is_string(
        $_POST["senha"] ?? null
    ) ? $_POST["senha"] : "";

    $confirmar = is_string(
        $_POST["confirmar_senha"] ?? null
    ) ? $_POST["confirmar_senha"] : "";

    if (!validarCSRF($token)) {

        $erro = "Pedido invalido. Actualiza a pagina.";

    } elseif (!preg_match(
        "/^[A-Za-z0-9_]{3,30}$/",
        $pseudonimo
    )) {

        $erro = "O pseudonimo deve ter entre 3 e 30 "
              . "caracteres: letras, numeros ou _.";

    } elseif (
        strlen($senha) < 8 ||
        strlen($senha) > 72
    ) {

        $erro = "A password deve ter entre "
              . "8 e 72 bytes.";

    } elseif ($senha !== $confirmar) {

        $erro = "As passwords nao coincidem.";

    } else {

        try {

            $conexao = conectarBD();

            // Proteger password
            $hash = password_hash(
                $senha,
                PASSWORD_DEFAULT
            );

            // Inserir utilizador
            $sql = "
                INSERT INTO usuarios
                    (pseudonimo, senha_hash)
                VALUES
                    (:pseudonimo, :senha_hash)
            ";

            $stmt = $conexao->prepare($sql);

            $stmt->execute([
                ":pseudonimo" => $pseudonimo,
                ":senha_hash" => $hash
            ]);

            // Proteger contra fixacao de sessao
            session_regenerate_id(true);

            $_SESSION["usuario_id"] =
                (int) $conexao->lastInsertId();

            $_SESSION["pseudonimo"] =
                $pseudonimo;

            $_SESSION["papel"] = "usuario";

            unset($_SESSION["csrf_token"]);

            redireccionar("feed.php");

        } catch (PDOException $ex) {

            if ($ex->getCode() === "23000") {

                $erro = "Este pseudonimo ja esta em uso.";

            } else {

                error_log(
                    "[SUBTERRA REGISTO] " .
                    $ex->getMessage()
                );

                $erro = "Nao foi possivel criar a conta.";
            }
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

    <title>Criar conta | SUBTERRA</title>

    <link rel="stylesheet" href="../css/style.css">

    <script src="../js/app.js" defer></script>
</head>

<body>

<header class="cabecalho">

    <a class="logo" href="../index.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">
        <a class="nav-link" href="login.php">
            Entrar
        </a>
    </nav>

</header>

<main class="auth-layout">

    <section class="auth-card">

        <div class="auth-intro">

            <p class="etiqueta">
                / IDENTIDADE PRIVADA
            </p>

            <h1>Criar conta</h1>

            <p>
                Entra na comunidade com um pseudonimo.
                As tuas publicacoes serao apresentadas
                sem identificar publicamente o autor.
            </p>

        </div>

        <?php if ($erro !== ""): ?>

            <div class="alert alert-error"
                 role="alert">
                <?= escapar($erro) ?>
            </div>

        <?php endif; ?>

        <form method="POST"
              class="auth-form"
              autocomplete="on">

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
                    placeholder="Ex.: sombra_01"
                    minlength="3"
                    maxlength="30"
                    pattern="[A-Za-z0-9_]{3,30}"
                    autocomplete="username"
                    required
                >

                <small>
                    De 3 a 30 caracteres.
                    Letras, numeros e underscore.
                </small>

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
                        placeholder="Minimo 8 caracteres"
                        minlength="8"
                        autocomplete="new-password"
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

            <div class="field">

                <label for="confirmar_senha">
                    Confirmar password
                </label>

                <div class="input-wrap">

                    <input
                        class="input"
                        type="password"
                        id="confirmar_senha"
                        name="confirmar_senha"
                        placeholder="Repete a password"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-toggle-password="confirmar_senha"
                        aria-label="Mostrar confirmacao"
                    >
                        Mostrar
                    </button>

                </div>

            </div>

            <button
                class="btn btn-primary btn-full"
                type="submit"
            >
                Criar conta
            </button>

        </form>

        <p class="auth-footer">
            Ja tens uma conta?
            <a href="login.php">Iniciar sessao</a>
        </p>

    </section>

</main>

<footer class="rodape">
    <span>SUBTERRA © 2026</span>
    <span>IDENTIDADE PRIVADA / IDEIAS PUBLICAS</span>
</footer>

</body>
</html>
