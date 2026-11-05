<?php

require_once __DIR__ . "/../includes/publicacoes.php";

exigirLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Metodo nao permitido.");
}

if (!validarCSRF($_POST["csrf_token"] ?? null)) {
    http_response_code(403);
    exit("Pedido invalido.");
}

$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    definirMensagem("erro", "Comentario invalido.");
    redireccionar("feed.php");
}

$destino = "feed.php";

try {
    $conexao = conectarBD();

    $sql = "
        SELECT c.publicacao_id
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
        definirMensagem(
            "erro",
            "Comentario nao encontrado ou sem permissao."
        );

        redireccionar("feed.php");
    }

    $destino = "feed.php#post-" .
        (int) $comentario["publicacao_id"];

    $sql = "
        DELETE FROM comentarios
        WHERE id = :id
          AND usuario_id = :usuario_id
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":id" => $id,
        ":usuario_id" => (int) $_SESSION["usuario_id"]
    ]);

    if ($stmt->rowCount() === 1) {
        definirMensagem(
            "sucesso",
            "Comentario eliminado com sucesso."
        );
    } else {
        definirMensagem(
            "erro",
            "Comentario nao encontrado."
        );
    }

} catch (PDOException $ex) {
    error_log(
        "[SUBTERRA ELIMINAR COMENTARIO] " .
        $ex->getMessage()
    );

    definirMensagem(
        "erro",
        "Nao foi possivel eliminar o comentario."
    );
}

redireccionar($destino);
