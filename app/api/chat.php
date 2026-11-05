<?php

require_once __DIR__ . "/../includes/algoritmos.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respostaChat(int $codigo, array $dados): void
{
    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

if (!estaAutenticado()) {
    respostaChat(401, [
        "ok" => false,
        "erro" => "Precisas de iniciar sessao."
    ]);
}

$metodo = $_SERVER["REQUEST_METHOD"];

if (!in_array($metodo, ["GET", "POST"], true)) {
    header("Allow: GET, POST");

    respostaChat(405, [
        "ok" => false,
        "erro" => "Metodo nao permitido."
    ]);
}

try {
    $conexao = conectarBD();

    // =====================================
    // GET - CONSULTAR MENSAGENS
    // =====================================

    if ($metodo === "GET") {

        $depois = filter_input(
            INPUT_GET,
            "depois",
            FILTER_VALIDATE_INT
        );

        if ($depois === null) {
            $depois = 0;
        }

        if ($depois === false || $depois < 0) {
            respostaChat(400, [
                "ok" => false,
                "erro" => "Identificador invalido."
            ]);
        }

        if ($depois > 0) {

            $sql = "
                SELECT
                    id,
                    conteudo,
                    DATE_FORMAT(
                        criado_em,
                        '%d/%m/%Y %H:%i'
                    ) AS data
                FROM mensagens
                WHERE id > :depois
                ORDER BY id ASC
                LIMIT 50
            ";

            $stmt = $conexao->prepare($sql);

            $stmt->bindValue(
                ":depois",
                $depois,
                PDO::PARAM_INT
            );

            $stmt->execute();

            $mensagens = $stmt->fetchAll();

        } else {

            $sql = "
                SELECT
                    id,
                    conteudo,
                    DATE_FORMAT(
                        criado_em,
                        '%d/%m/%Y %H:%i'
                    ) AS data
                FROM mensagens
                ORDER BY id DESC
                LIMIT 50
            ";

            $stmt = $conexao->query($sql);

            $mensagens = array_reverse(
                $stmt->fetchAll()
            );
        }

        respostaChat(200, [
            "ok" => true,
            "mensagens" => $mensagens
        ]);
    }

    // =====================================
    // POST - ENVIAR MENSAGEM
    // =====================================

    $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? null;

    if (!validarCSRF($token)) {
        respostaChat(403, [
            "ok" => false,
            "erro" => "Pedido invalido."
        ]);
    }

    $entrada = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($entrada)) {
        respostaChat(400, [
            "ok" => false,
            "erro" => "Dados invalidos."
        ]);
    }

    $conteudo = is_string($entrada["conteudo"] ?? null)
        ? trim($entrada["conteudo"])
        : "";

    $tamanho = contarCaracteres($conteudo);

    if ($tamanho < 1 || $tamanho > 500) {
        respostaChat(400, [
            "ok" => false,
            "erro" => "A mensagem deve ter entre 1 e 500 caracteres."
        ]);
    }

    $usuarioId = (int) $_SESSION["usuario_id"];

    if (!permitirMensagemComAlgoritmos($usuarioId)) {
        respostaChat(429, [
            "ok" => false,
            "erro" => "Limite de mensagens atingido. Aguarda alguns segundos."
        ]);
    }

    $stmt = $conexao->prepare("
        INSERT INTO mensagens
            (usuario_id, conteudo)
        VALUES
            (:usuario_id, :conteudo)
    ");

    $stmt->execute([
        ":usuario_id" => $usuarioId,
        ":conteudo" => $conteudo
    ]);

    respostaChat(201, [
        "ok" => true,
        "mensagem" => "Mensagem enviada."
    ]);

} catch (SubterraAlgorithmException $ex) {
    error_log("[SUBTERRA ALGORITMOS CHAT] " . $ex->getMessage());

    respostaChat(503, [
        "ok" => false,
        "erro" => "O servico de mensagens esta temporariamente indisponivel."
    ]);

} catch (Throwable $ex) {

    error_log(
        "[SUBTERRA CHAT API] " .
        $ex->getMessage()
    );

    respostaChat(500, [
        "ok" => false,
        "erro" => "Erro ao comunicar com o servidor."
    ]);
}