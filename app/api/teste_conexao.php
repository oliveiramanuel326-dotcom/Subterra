<?php

require_once __DIR__ . "/../includes/bootstrap.php";

header("Content-Type: text/plain; charset=utf-8");
header("Cache-Control: no-store");

try {
    $resultado = conectarBD()->query(
        "SELECT DATABASE() AS base_dados, @@port AS porta"
    );

    $dados = $resultado->fetch();

    echo "SUBTERRA - DIAGNOSTICO\n\n";
    echo "Estado: CONECTADO\n";
    echo "Base de dados: " . $dados["base_dados"] . "\n";
    echo "Porta: " . $dados["porta"] . "\n";
} catch (Throwable $erro) {
    error_log("[SUBTERRA DIAGNOSTICO] " . $erro->getMessage());

    http_response_code(500);
    echo "SUBTERRA - Erro de ligacao. Verifica as variaveis de ambiente e o registo do servidor.\n";
}
