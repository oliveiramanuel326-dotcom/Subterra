<?php

function conectarBD(): PDO
{
    static $conexao = null;

    if ($conexao instanceof PDO) {
        return $conexao;
    }

    $host = getenv("SUBTERRA_DB_HOST") ?: "127.0.0.1";
    $porta = getenv("SUBTERRA_DB_PORT") ?: "3306";
    $baseDados = getenv("SUBTERRA_DB_NAME") ?: "subterra_app";
    $utilizador = getenv("SUBTERRA_DB_USER");
    $senha = getenv("SUBTERRA_DB_PASSWORD");

    if ($utilizador === false || $senha === false) {
        throw new PDOException(
            "Define SUBTERRA_DB_USER e SUBTERRA_DB_PASSWORD antes de iniciar a aplicacao."
        );
    }

    $dsn = "mysql:host=" . $host
        . ";port=" . $porta
        . ";dbname=" . $baseDados
        . ";charset=utf8mb4";

    $conexao = new PDO($dsn, $utilizador, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    return $conexao;
}
