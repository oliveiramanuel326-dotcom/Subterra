<?php

require_once __DIR__ . "/bootstrap.php";

class SubterraAlgorithmException extends RuntimeException
{
}

function pedirAlgoritmo(string $rota, array $dados): array
{
    $base = rtrim(
        getenv("SUBTERRA_ALGORITHM_URL") ?: "http://127.0.0.1:8787",
        "/"
    );

    $contexto = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/x-www-form-urlencoded\r\n"
                . "Accept: application/json\r\n",
            "content" => http_build_query(
                $dados,
                "",
                "&",
                PHP_QUERY_RFC3986
            ),
            "timeout" => 3,
            "ignore_errors" => true
        ]
    ]);

    $resposta = @file_get_contents(
        $base . $rota,
        false,
        $contexto
    );

    if ($resposta === false) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos nao esta disponivel."
        );
    }

    $estadoHttp = $http_response_header[0] ?? "";
    if (!preg_match('/\s2\d\d\s/', $estadoHttp)) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos rejeitou o pedido."
        );
    }

    try {
        $resultado = json_decode(
            $resposta,
            true,
            32,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $ex) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos devolveu uma resposta invalida.",
            0,
            $ex
        );
    }

    if (!is_array($resultado) || ($resultado["ok"] ?? false) !== true) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos devolveu uma resposta invalida."
        );
    }

    return $resultado;
}

function ordenarFeedComAlgoritmos(array $publicacoes): array
{
    if ($publicacoes === []) {
        return [];
    }

    $entrada = [];
    $porId = [];
    foreach ($publicacoes as $publicacao) {
        $id = (int) $publicacao["id"];
        $porId[$id] = $publicacao;
        $entrada[] = [
            "id" => $id,
            "text" => (string) $publicacao["conteudo"],
            "created_at" => strtotime((string) $publicacao["criado_em"]),
            "upvotes" => (int) $publicacao["upvotes_profundidade"]
        ];
    }

    $resposta = pedirAlgoritmo("/api/rank", ["posts" => $entrada]);
    $ordenadas = $resposta["posts"] ?? null;

    if (!is_array($ordenadas) || count($ordenadas) !== count($porId)) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos devolveu um ranking incompleto."
        );
    }

    $resultado = [];
    $vistos = [];
    foreach ($ordenadas as $linha) {
        $id = filter_var(
            $linha["id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $densidade = $linha["density"] ?? null;
        $pontuacao = $linha["score"] ?? null;
        $subterranea = $linha["introspective"] ?? null;

        if (
            $id === false ||
            !isset($porId[$id]) ||
            isset($vistos[$id]) ||
            !is_numeric($densidade) ||
            !is_numeric($pontuacao) ||
            !is_bool($subterranea)
        ) {
            throw new SubterraAlgorithmException(
                "O servico de algoritmos devolveu um ranking invalido."
            );
        }

        $publicacao = $porId[$id];
        $publicacao["densidade_semantica"] = (float) $densidade;
        $publicacao["pontuacao_profundidade"] = (float) $pontuacao;
        $publicacao["e_subterranea"] = $subterranea;
        $resultado[] = $publicacao;
        $vistos[$id] = true;
    }

    return $resultado;
}

function moderarTextoComAlgoritmos(string $texto): array
{
    return pedirAlgoritmo("/api/moderate", ["text" => $texto]);
}

function permitirMensagemComAlgoritmos(int $usuarioId): bool
{
    $resposta = pedirAlgoritmo(
        "/api/rate-limit",
        ["user_id" => $usuarioId]
    );

    if (!is_bool($resposta["allowed"] ?? null)) {
        throw new SubterraAlgorithmException(
            "O servico de algoritmos devolveu uma resposta invalida."
        );
    }

    return $resposta["allowed"];
}
