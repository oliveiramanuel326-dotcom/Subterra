<?php

require_once __DIR__ . "/../includes/publicacoes.php";

exigirLogin();

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(404);
    exit("Comentario nao encontrado.");
}

try {
    $conexao = conectarBD();

    $sql = "
        SELECT
            c.id,
            c.publicacao_id,
            c.conteudo
        FROM comentarios c
        INNER JOIN publicacoes p
            ON p.id = c.publicacao_id
        WHERE c.id = :id
          AND c.usuario_id = :usuario_id
          AND p.estado = 'publicado'
        LIMIT 1
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":id" => $id,
        ":usuario_id" => (int) $_SESSION["usuario_id"]
    ]);

    $comentario = $stmt->fetch();

    if (!$comentario) {
        http_response_code(404);
        exit("Comentario nao encontrado ou sem permissao.");
    }

} catch (PDOException $ex) {
    error_log("[SUBTERRA EDITAR COMENTARIO] " . $ex->getMessage());

    http_response_code(500);
    exit("Erro ao carregar comentario.");
}

$conteudo = $comentario["conteudo"];
$erro = "";

$destino = "feed.php#post-" .
    (int) $comentario["publicacao_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $conteudo = is_string($_POST["conteudo"] ?? null)
        ? trim($_POST["conteudo"])
        : "";

    if (!validarCSRF($_POST["csrf_token"] ?? null)) {

        http_response_code(403);
        $erro = "Pedido invalido. Actualiza a pagina.";

    } elseif (
        contarCaracteres($conteudo) < 2 ||
        contarCaracteres($conteudo) > 1000
    ) {

        $erro = "O comentario deve ter entre 2 e 1000 caracteres.";

    } else {

        try {
            $sql = "
                UPDATE comentarios c
                INNER JOIN publicacoes p
                    ON p.id = c.publicacao_id
                SET c.conteudo = :conteudo
                WHERE c.id = :id
                  AND c.usuario_id = :usuario_id
                  AND p.estado = 'publicado'
            ";

            $stmt = $conexao->prepare($sql);

            $stmt->execute([
                ":conteudo" => $conteudo,
                ":id" => $id,
                ":usuario_id" => (int) $_SESSION["usuario_id"]
            ]);

            definirMensagem(
                "sucesso",
                "Comentario actualizado com sucesso!"
            );

            redireccionar($destino);

        } catch (PDOException $ex) {
            error_log(
                "[SUBTERRA GUARDAR COMENTARIO] " .
                $ex->getMessage()
            );

            $erro = "Nao foi possivel guardar o comentario.";
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

    <title>Editar comentario | SUBTERRA</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/publicacoes.css">
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
        <p class="etiqueta">/ COMENTARIO ANONIMO</p>
        <h1>Editar comentario.</h1>
        <p>
            Podes alterar o teu comentario sem
            revelar o teu pseudonimo.
        </p>
    </div>

    <?php if ($erro !== ""): ?>
        <div class="alert alert-error" role="alert">
            <?= escapar($erro) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="editor-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(tokenCSRF()) ?>"
        >

        <div class="field">
            <label for="conteudo">Comentario</label>

            <textarea
                class="input textarea-editor"
                id="conteudo"
                name="conteudo"
                minlength="2"
                maxlength="1000"
                required
            ><?= escapar($conteudo) ?></textarea>

            <small>Maximo de 1000 caracteres.</small>
        </div>

        <div class="editor-actions">
            <a class="btn btn-outline"
               href="../feed.php#post-<?= (int) $comentario["publicacao_id"] ?>">
                Cancelar
            </a>

            <button type="submit"
                    class="btn btn-primary">
                Guardar alteracoes
            </button>
        </div>

    </form>

</main>

</body>
</html>