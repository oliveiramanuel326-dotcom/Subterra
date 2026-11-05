<?php

/*
========================================
SUBTERRA - CONFIGURACAO GERAL
========================================
*/

function caminhoAplicacao(): string
{
    $configurado = getenv("SUBTERRA_BASE_PATH");

    if ($configurado !== false) {
        $caminho = trim($configurado, "/");
        return $caminho === "" ? "" : "/" . $caminho;
    }

    $partes = explode(
        "/",
        trim(str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/")
    );

    $pastasAninhadas = [
        "auth",
        "publicacoes",
        "comentarios",
        "denuncias",
        "Denuncias",
        "moderacao",
        "Moderacao",
        "api"
    ];

    $profundidade = count($partes) > 1
        && in_array($partes[count($partes) - 2], $pastasAninhadas, true)
            ? 2
            : 1;

    $raiz = array_slice($partes, 0, -$profundidade);

    return $raiz === [] ? "" : "/" . implode("/", $raiz);
}

$caminhoAplicacao = caminhoAplicacao();

if (session_status() === PHP_SESSION_NONE) {

    $https = (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    );

    // Rejeitar identificadores de sessao
    // que nao tenham sido criados pelo PHP.
    ini_set("session.use_strict_mode", "1");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => $caminhoAplicacao . "/",
        "secure" => $https,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}


// ========================================
// 2. IMPEDIR CACHE
// ========================================

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header("Pragma: no-cache");


require_once __DIR__ . "/database.php";


// ========================================
// 4. ESCAPAR TEXTO PARA HTML
// ========================================

function escapar($texto)
{
    return htmlspecialchars(
        (string) $texto,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}


// ========================================
// 5. GERAR TOKEN CSRF
// ========================================

function tokenCSRF()
{
    if (empty($_SESSION["csrf_token"])) {

        $_SESSION["csrf_token"] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}


// ========================================
// 6. VALIDAR TOKEN CSRF
// ========================================

function validarCSRF($token)
{
    return is_string($token)
        && isset($_SESSION["csrf_token"])
        && hash_equals(
            $_SESSION["csrf_token"],
            $token
        );
}


// ========================================
// 7. VERIFICAR AUTENTICACAO
// ========================================

function estaAutenticado()
{
    return isset($_SESSION["usuario_id"])
        && (int) $_SESSION["usuario_id"] > 0;
}


// ========================================
// 8. PROTEGER PAGINAS PRIVADAS
// ========================================

function exigirLogin()
{
    if (!estaAutenticado()) {

        redireccionar("auth/login.php");
    }
}


// ========================================
// 9. REDIRECCIONAR
// ========================================

function redireccionar($caminho)
{
    header(
        "Location: " .
        $GLOBALS["caminhoAplicacao"] .
        "/" .
        ltrim((string) $caminho, "/"),
        true,
        303
    );

    exit;
}