<?php

/*
========================================
SUBTERRA - CONTROLO DE MODERACAO
========================================
*/

require_once __DIR__ . "/publicacoes.php";


// ========================================
// VERIFICAR MODERADOR OU ADMIN
// ========================================

function eModerador()
{
    if (!estaAutenticado()) {
        return false;
    }

    $usuarioId = (int) $_SESSION["usuario_id"];

    try {

        $pdo = conectarBD();

        $sql = "
            SELECT papel
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(
            ":id",
            $usuarioId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $papel = $stmt->fetchColumn();

        if ($papel === false) {
            unset($_SESSION["papel"]);
            return false;
        }

        $papel = trim((string) $papel);

        // Actualizar papel da sessao
        $_SESSION["papel"] = $papel;

        return in_array(
            $papel,
            ["admin", "moderador"],
            true
        );

    } catch (PDOException $erro) {

        error_log(
            "SUBTERRA - Erro na moderacao: " .
            $erro->getMessage()
        );

        return false;
    }
}


// ========================================
// PROTEGER PAGINAS DE MODERACAO
// ========================================

function exigirModerador()
{
    // Primeiro verificar login
    exigirLogin();

    // Depois verificar permissao
    if (!eModerador()) {

        http_response_code(403);

        exit(
            "Acesso negado. Esta pagina e exclusiva " .
            "para moderadores e administradores."
        );
    }
}