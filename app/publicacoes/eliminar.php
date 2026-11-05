<?php

require_once __DIR__ .
    "/../includes/publicacoes.php";

exigirLogin();

// Apenas pedidos POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    header("Allow: POST");

    exit("Metodo nao permitido.");
}

// Validar token CSRF
if (!validarCSRF(
    $_POST["csrf_token"] ?? null
)) {

    http_response_code(403);

    exit("Pedido invalido.");
}

// Validar ID
$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

if (!$id) {

    definirMensagem(
        "erro",
        "Identificador invalido."
    );

    redireccionar("feed.php");
}

try {

    $conexao = conectarBD();

    // Eliminacao logica:
    // apenas o autor pode alterar o estado
    $sql = "
        UPDATE publicacoes
        SET estado = 'eliminado'
        WHERE id = :id
          AND usuario_id = :usuario_id
          AND estado = 'publicado'
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":id" => $id,
        ":usuario_id" =>
            $_SESSION["usuario_id"]
    ]);

    if ($stmt->rowCount() === 1) {

        definirMensagem(
            "sucesso",
            "Publicacao eliminada do feed."
        );

    } else {

        definirMensagem(
            "erro",
            "Publicacao nao encontrada "
            . "ou sem permissao."
        );
    }

} catch (PDOException $ex) {

    error_log(
        "[SUBTERRA ELIMINAR] " .
        $ex->getMessage()
    );

    definirMensagem(
        "erro",
        "Nao foi possivel eliminar "
        . "a publicacao."
    );
}

redireccionar("feed.php");