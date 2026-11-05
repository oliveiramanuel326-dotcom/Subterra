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

$conteudo = is_string($_POST["conteudo"] ?? null)
    ? trim($_POST["conteudo"])
    : "";

if (!$publicacaoId) {
    definirMensagem("erro", "Publicacao invalida.");
    redireccionar("feed.php");
}

$destino = "feed.php#post-" . $publicacaoId;

$tamanho = contarCaracteres($conteudo);

if ($tamanho < 2 || $tamanho > 1000) {
    definirMensagem(
        "erro",
        "O comentario deve ter entre 2 e 1000 caracteres."
    );

    redireccionar($destino);
}

try {
    $conexao = conectarBD();

    // Insere apenas se a publicacao estiver activa.
    $sql = "
        INSERT INTO comentarios
            (publicacao_id, usuario_id, conteudo)
        SELECT
            id, :usuario_id, :conteudo
        FROM publicacoes
        WHERE id = :publicacao_id
          AND estado = 'publicado'
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":usuario_id" => (int) $_SESSION["usuario_id"],
        ":conteudo" => $conteudo,
        ":publicacao_id" => $publicacaoId
    ]);

    if ($stmt->rowCount() === 1) {
        definirMensagem(
            "sucesso",
            "Comentario publicado com sucesso!"
        );
    } else {
        definirMensagem(
            "erro",
            "Esta publicacao nao esta disponivel."
        );
    }

} catch (PDOException $ex) {
    error_log("[SUBTERRA COMENTAR] " . $ex->getMessage());

    definirMensagem(
        "erro",
        "Nao foi possivel publicar o comentario."
    );
}

redireccionar($destino);