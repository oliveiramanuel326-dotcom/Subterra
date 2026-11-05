<?php

require_once __DIR__ .
    "/../includes/publicacoes.php";

exigirLogin();

// Receber ID da publicacao
$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

if (!$id) {
    http_response_code(404);
    exit("Publicacao nao encontrada.");
}

$erro = "";

try {

    $conexao = conectarBD();

    // Apenas o autor pode consultar
    // os dados para edicao
    $sql = "
        SELECT
            id,
            titulo,
            conteudo,
            categoria
        FROM publicacoes
        WHERE id = :id
          AND usuario_id = :usuario_id
          AND estado = 'publicado'
        LIMIT 1
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":id" => $id,
        ":usuario_id" =>
            $_SESSION["usuario_id"]
    ]);

    $publicacao = $stmt->fetch();

    if (!$publicacao) {

        http_response_code(404);

        exit(
            "Publicacao nao encontrada "
            . "ou sem permissao de edicao."
        );
    }

} catch (PDOException $ex) {

    error_log(
        "[SUBTERRA CONSULTAR EDICAO] " .
        $ex->getMessage()
    );

    http_response_code(500);

    exit("Erro ao carregar a publicacao.");
}

// Valores iniciais
$titulo = $publicacao["titulo"];
$conteudo = $publicacao["conteudo"];
$categoria = $publicacao["categoria"];

// Actualizar publicacao
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = is_string(
        $_POST["titulo"] ?? null
    ) ? trim($_POST["titulo"]) : "";

    $conteudo = is_string(
        $_POST["conteudo"] ?? null
    ) ? trim($_POST["conteudo"]) : "";

    $categoria = is_string(
        $_POST["categoria"] ?? null
    ) ? $_POST["categoria"] : "";

    if (!validarCSRF(
        $_POST["csrf_token"] ?? null
    )) {

        http_response_code(403);

        $erro = "Pedido invalido. "
              . "Actualiza a pagina.";

    } else {

        $erro = validarPublicacao(
            $titulo,
            $conteudo,
            $categoria
        );

        if ($erro === "") {

            try {

                $sql = "
                    UPDATE publicacoes
                    SET
                        titulo = :titulo,
                        conteudo = :conteudo,
                        categoria = :categoria
                    WHERE id = :id
                      AND usuario_id = :usuario_id
                      AND estado = 'publicado'
                ";

                $stmt = $conexao->prepare($sql);

                $stmt->execute([
                    ":titulo" => $titulo,
                    ":conteudo" => $conteudo,
                    ":categoria" => $categoria,
                    ":id" => $id,
                    ":usuario_id" =>
                        $_SESSION["usuario_id"]
                ]);

                definirMensagem(
                    "sucesso",
                    "Publicacao actualizada com sucesso!"
                );

                redireccionar("feed.php");

            } catch (PDOException $ex) {

                error_log(
                    "[SUBTERRA EDITAR] " .
                    $ex->getMessage()
                );

                $erro = "Nao foi possivel "
                      . "actualizar a publicacao.";
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

    <title>Editar publicacao | SUBTERRA</title>

    <link rel="stylesheet"
          href="../css/style.css">

    <link rel="stylesheet"
          href="../css/publicacoes.css">

</head>

<body>

<header class="cabecalho">

    <a class="logo" href="../feed.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <a class="nav-link" href="../feed.php">
            Voltar ao feed
        </a>

    </nav>

</header>

<main class="editor-shell">

    <div class="editor-heading">

        <p class="etiqueta">
            / GESTAO DE PUBLICACAO
        </p>

        <h1>Editar publicacao.</h1>

        <p>
            Actualiza a tua ideia.
            A publicacao continuara anonima
            para os outros utilizadores.
        </p>

    </div>

    <?php if ($erro !== ""): ?>

        <div class="alert alert-error"
             role="alert">
            <?= escapar($erro) ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action="editar.php?id=<?= (int) $id ?>"
        class="editor-form"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(tokenCSRF()) ?>"
        >

        <div class="field">

            <label for="titulo">
                Titulo
            </label>

            <input
                class="input"
                type="text"
                id="titulo"
                name="titulo"
                minlength="5"
                maxlength="160"
                value="<?= escapar($titulo) ?>"
                required
            >

        </div>

        <div class="field">

            <label for="categoria">
                Categoria
            </label>

            <select
                class="input"
                id="categoria"
                name="categoria"
                required
            >

                <?php foreach (
                    categoriasPublicacoes()
                    as $codigo => $nome
                ): ?>

                    <option
                        value="<?= escapar($codigo) ?>"
                        <?= $categoria === $codigo
                            ? "selected"
                            : "" ?>
                    >
                        <?= escapar($nome) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="field">

            <label for="conteudo">
                Conteudo
            </label>

            <textarea
                class="input textarea-editor"
                id="conteudo"
                name="conteudo"
                minlength="10"
                maxlength="5000"
                required
            ><?= escapar($conteudo) ?></textarea>

        </div>

        <div class="editor-actions">

            <a class="btn btn-outline"
               href="../feed.php">
                Cancelar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
            >
                Guardar alteracoes
            </button>

        </div>

    </form>

</main>

</body>
</html>
