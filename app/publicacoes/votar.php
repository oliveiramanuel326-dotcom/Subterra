<?php

require_once __DIR__ . "/../includes/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

function respostaJSON(int $codigo, array $dados): void
{
    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    respostaJSON(405, [
        "ok" => false,
        "erro" => "Metodo nao permitido."
    ]);
}

if (!estaAutenticado()) {
    respostaJSON(401, [
        "ok" => false,
        "erro" => "Precisas de iniciar sessao."
    ]);
}

$token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? null;

if (!validarCSRF($token)) {
    respostaJSON(403, [
        "ok" => false,
        "erro" => "Pedido invalido."
    ]);
}

$entrada = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($entrada)) {
    respostaJSON(400, [
        "ok" => false,
        "erro" => "Dados invalidos."
    ]);
}

$publicacaoId = filter_var(
    $entrada["publicacao_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$valor = $entrada["valor"] ?? null;

if (
    !$publicacaoId ||
    !in_array($valor, [-1, 0, 1], true)
) {
    respostaJSON(400, [
        "ok" => false,
        "erro" => "Voto invalido."
    ]);
}

$usuarioId = (int) $_SESSION["usuario_id"];

try {
    $conexao = conectarBD();

    $stmt = $conexao->prepare("
        SELECT id
        FROM publicacoes
        WHERE id = :id
          AND estado = 'publicado'
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $publicacaoId
    ]);

    if (!$stmt->fetch()) {
        respostaJSON(404, [
            "ok" => false,
            "erro" => "Publicacao nao encontrada."
        ]);
    }

    if ($valor === 0) {

        // Retirar voto
        $stmt = $conexao->prepare("
            DELETE FROM votos
            WHERE publicacao_id = :publicacao_id
              AND usuario_id = :usuario_id
        ");

        $stmt->execute([
            ":publicacao_id" => $publicacaoId,
            ":usuario_id" => $usuarioId
        ]);

    } else {

        // Criar voto ou alterar o voto existente
        $stmt = $conexao->prepare("
            INSERT INTO votos
                (publicacao_id, usuario_id, valor)
            VALUES
                (:publicacao_id, :usuario_id, :valor)
            ON DUPLICATE KEY UPDATE
                valor = VALUES(valor)
        ");

        $stmt->execute([
            ":publicacao_id" => $publicacaoId,
            ":usuario_id" => $usuarioId,
            ":valor" => $valor
        ]);
    }

    // Calcular pontuacao
    $stmt = $conexao->prepare("
        SELECT
            COALESCE(SUM(valor), 0) AS pontuacao,
            COUNT(*) AS total_votos
        FROM votos
        WHERE publicacao_id = :publicacao_id
    ");

    $stmt->execute([
        ":publicacao_id" => $publicacaoId
    ]);

    $resumo = $stmt->fetch();

    // Consultar voto actual do utilizador
    $stmt = $conexao->prepare("
        SELECT valor
        FROM votos
        WHERE publicacao_id = :publicacao_id
          AND usuario_id = :usuario_id
        LIMIT 1
    ");

    $stmt->execute([
        ":publicacao_id" => $publicacaoId,
        ":usuario_id" => $usuarioId
    ]);

    $meuVoto = $stmt->fetchColumn();

    respostaJSON(200, [
        "ok" => true,
        "pontuacao" => (int) $resumo["pontuacao"],
        "total_votos" => (int) $resumo["total_votos"],
        "meu_voto" => $meuVoto === false
            ? 0
            : (int) $meuVoto
    ]);

} catch (PDOException $ex) {
    error_log(
        "[SUBTERRA VOTAR] " .
        $ex->getMessage()
    );

    respostaJSON(500, [
        "ok" => false,
        "erro" => "Nao foi possivel registar o voto."
    ]);
}