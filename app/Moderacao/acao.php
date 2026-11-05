<?php

require_once __DIR__ . "/../includes/moderacao.php";

exigirModerador();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);
    header("Allow: POST");

    exit("Metodo nao permitido.");
}

if (!validarCSRF($_POST["csrf_token"] ?? null)) {

    http_response_code(403);

    exit("Pedido invalido.");
}

$acao = is_string($_POST["acao"] ?? null)
    ? $_POST["acao"]
    : "";

$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$acoesPermitidas = [
    "ocultar_publicacao",
    "restaurar_publicacao",
    "resolver_denuncia",
    "eliminar_mensagem"
];

if (
    !$id ||
    !in_array($acao, $acoesPermitidas, true)
) {

    definirMensagem(
        "erro",
        "Operacao administrativa invalida."
    );

    redireccionar("Moderacao/index.php");
}

try {
    $conexao = conectarBD();

    $afectados = 0;
    $mensagemSucesso = "";

    // =====================================
    // OCULTAR PUBLICACAO
    // =====================================

    if ($acao === "ocultar_publicacao") {

        $conexao->beginTransaction();

        $stmt = $conexao->prepare("
            UPDATE publicacoes
            SET estado = 'oculto'
            WHERE id = :id
              AND estado = 'publicado'
        ");

        $stmt->execute([":id" => $id]);

        $afectados = $stmt->rowCount();

        if ($afectados === 1) {

            // Resolver denuncias pendentes
            // relacionadas com esta publicacao.
            $stmt = $conexao->prepare("
                UPDATE denuncias
                SET estado = 'resolvida'
                WHERE publicacao_id = :id
                  AND estado = 'pendente'
            ");

            $stmt->execute([":id" => $id]);
        }

        $conexao->commit();

        $mensagemSucesso =
            "Publicacao ocultada do feed.";
    }

    // =====================================
    // RESTAURAR PUBLICACAO
    // =====================================

    elseif ($acao === "restaurar_publicacao") {

        $stmt = $conexao->prepare("
            UPDATE publicacoes
            SET estado = 'publicado'
            WHERE id = :id
              AND estado = 'oculto'
        ");

        $stmt->execute([":id" => $id]);

        $afectados = $stmt->rowCount();

        $mensagemSucesso =
            "Publicacao restaurada no feed.";
    }

    // =====================================
    // RESOLVER DENUNCIA
    // =====================================

    elseif ($acao === "resolver_denuncia") {

        $stmt = $conexao->prepare("
            UPDATE denuncias
            SET estado = 'resolvida'
            WHERE id = :id
              AND estado = 'pendente'
        ");

        $stmt->execute([":id" => $id]);

        $afectados = $stmt->rowCount();

        $mensagemSucesso =
            "Denuncia marcada como resolvida.";
    }

    // =====================================
    // ELIMINAR MENSAGEM DO CHAT
    // =====================================

    elseif ($acao === "eliminar_mensagem") {

        $stmt = $conexao->prepare("
            DELETE FROM mensagens
            WHERE id = :id
        ");

        $stmt->execute([":id" => $id]);

        $afectados = $stmt->rowCount();

        $mensagemSucesso =
            "Mensagem removida do chat.";
    }

    if ($afectados > 0) {

        // Registo tecnico no log do PHP
        error_log(
            "[SUBTERRA MODERACAO] " .
            "moderador_id=" .
            (int) $_SESSION["usuario_id"] .
            " acao=" . $acao .
            " alvo_id=" . $id
        );

        definirMensagem(
            "sucesso",
            $mensagemSucesso
        );

    } else {

        definirMensagem(
            "erro",
            "Registo nao encontrado ou operacao ja realizada."
        );
    }

} catch (PDOException $ex) {

    if (
        isset($conexao) &&
        $conexao instanceof PDO &&
        $conexao->inTransaction()
    ) {
        $conexao->rollBack();
    }

    error_log(
        "[SUBTERRA ACCAO MODERACAO] " .
        $ex->getMessage()
    );

    definirMensagem(
        "erro",
        "Nao foi possivel executar a operacao."
    );
}

redireccionar("Moderacao/index.php");