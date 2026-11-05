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

$publicacaoId = filter_var(
    $_POST["publicacao_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$motivo = is_string($_POST["motivo"] ?? null)
    ? trim($_POST["motivo"])
    : "";

if (!$publicacaoId) {
    definirMensagem("erro", "Publicacao invalida.");
    redireccionar("feed.php");
}

$destino = "feed.php#post-" . $publicacaoId;

$tamanho = contarCaracteres($motivo);

if ($tamanho < 10 || $tamanho > 500) {
    definirMensagem(
        "erro",
        "O motivo deve ter entre 10 e 500 caracteres."
    );

    redireccionar($destino);
}

try {
    $conexao = conectarBD();

    // Impedir que uma conta denuncie
    // a sua propria publicacao.
    $sql = "
        SELECT id
        FROM publicacoes
        WHERE id = :id
          AND usuario_id <> :usuario_id
          AND estado = 'publicado'
        LIMIT 1
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":id" => $publicacaoId,
        ":usuario_id" => (int) $_SESSION["usuario_id"]
    ]);

    if (!$stmt->fetch()) {
        definirMensagem(
            "erro",
            "Esta publicacao nao pode ser denunciada."
        );

        redireccionar($destino);
    }

    $sql = "
        INSERT INTO denuncias
            (
                publicacao_id,
                usuario_id,
                motivo,
                estado
            )
        VALUES
            (
                :publicacao_id,
                :usuario_id,
                :motivo,
                'pendente'
            )
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":publicacao_id" => $publicacaoId,
        ":usuario_id" => (int) $_SESSION["usuario_id"],
        ":motivo" => $motivo
    ]);

    definirMensagem(
        "sucesso",
        "Denuncia enviada para moderacao."
    );

} catch (PDOException $ex) {

    if ($ex->getCode() === "23000") {

        definirMensagem(
            "erro",
            "Ja denunciaste esta publicacao."
        );

    } else {

        error_log(
            "[SUBTERRA DENUNCIAS] " .
            $ex->getMessage()
        );

        definirMensagem(
            "erro",
            "Nao foi possivel enviar a denuncia."
        );
    }
}

redireccionar($destino);