<?php

/*
========================================
SUBTERRA - LOGOUT
ENCERRAMENTO SEGURO DA SESSAO
========================================
*/

require_once __DIR__ . "/../includes/bootstrap.php";


// ========================================
// 1. ACEITAR APENAS POST
// ========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    header("Allow: POST");

    exit("Metodo nao permitido.");
}


// ========================================
// 2. VALIDAR TOKEN CSRF
// ========================================

$token = $_POST["csrf_token"] ?? null;

if (!validarCSRF($token)) {

    http_response_code(403);

    exit("Pedido de logout invalido.");
}


// ========================================
// 3. LIMPAR VARIAVEIS DA SESSAO
// ========================================

// Remove:
// usuario_id
// pseudonimo
// papel
// csrf_token
// e qualquer outro dado de sessao

$_SESSION = [];


// ========================================
// 4. ELIMINAR COOKIE DA SESSAO
// ========================================

if (ini_get("session.use_cookies")) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $parametros["path"],
            "domain" => $parametros["domain"],
            "secure" => $parametros["secure"],
            "httponly" => $parametros["httponly"],
            "samesite" =>
                $parametros["samesite"] ?? "Lax"
        ]
    );
}


// ========================================
// 5. DESTRUIR SESSAO NO SERVIDOR
// ========================================

session_destroy();


// ========================================
// 6. IMPEDIR CACHE
// ========================================

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header("Pragma: no-cache");


// ========================================
// 7. REDIRECCIONAR PARA LOGIN
// ========================================

redireccionar("auth/login.php");