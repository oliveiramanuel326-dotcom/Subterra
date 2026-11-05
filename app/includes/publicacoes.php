<?php

require_once __DIR__ . "/bootstrap.php";

// ========================================
// CATEGORIAS DISPONIVEIS
// ========================================

function categoriasPublicacoes(): array
{
    return [
        "geral" => "Geral",
        "tecnologia" => "Tecnologia",
        "educacao" => "Educacao",
        "sociedade" => "Sociedade",
        "desabafos" => "Desabafos"
    ];
}


// ========================================
// CONTAR CARACTERES UTF-8
// ========================================

function contarCaracteres(string $texto): int
{
    if (function_exists("mb_strlen")) {
        return mb_strlen($texto, "UTF-8");
    }

    $quantidade = preg_match_all(
        "/./us",
        $texto
    );

    return $quantidade === false
        ? -1
        : $quantidade;
}


// ========================================
// VALIDAR PUBLICACAO
// ========================================

function validarPublicacao(
    string $titulo,
    string $conteudo,
    string $categoria
): string {

    $tamanhoTitulo = contarCaracteres($titulo);
    $tamanhoConteudo = contarCaracteres($conteudo);

    if (
        $tamanhoTitulo < 5 ||
        $tamanhoTitulo > 160
    ) {
        return "O titulo deve ter entre "
             . "5 e 160 caracteres.";
    }

    if (
        $tamanhoConteudo < 10 ||
        $tamanhoConteudo > 5000
    ) {
        return "O conteudo deve ter entre "
             . "10 e 5000 caracteres.";
    }

    if (
        !array_key_exists(
            $categoria,
            categoriasPublicacoes()
        )
    ) {
        return "Selecciona uma categoria valida.";
    }

    return "";
}


// ========================================
// MENSAGENS TEMPORARIAS
// ========================================

function definirMensagem(
    string $tipo,
    string $texto
): void {

    $_SESSION["flash"] = [
        "tipo" => $tipo,
        "texto" => $texto
    ];
}


function obterMensagem(): ?array
{
    $mensagem = $_SESSION["flash"] ?? null;

    unset($_SESSION["flash"]);

    return is_array($mensagem)
        ? $mensagem
        : null;
}
