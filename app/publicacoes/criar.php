<?php

require_once __DIR__ .
    "/../includes/publicacoes.php";
require_once __DIR__ .
    "/../includes/algoritmos.php";

exigirLogin();

$erro = "";
$titulo = "";
$conteudo = "";
$categoria = "geral";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = is_string(
        $_POST["titulo"] ?? null
    ) ? trim($_POST["titulo"]) : "";

    $conteudo = is_string(
        $_POST["conteudo"] ?? null
    ) ? trim($_POST["conteudo"]) : "";

    $categoria = is_string(
        $_POST["categoria"] ?? null
    ) ? $_POST["categoria"] : "";

    if (!validarCSRF(
        $_POST["csrf_token"] ?? null
    )) {

        http_response_code(403);

        $erro = "Pedido invalido. "
              . "Actualiza a pagina.";

    } else {

        $erro = validarPublicacao(
            $titulo,
            $conteudo,
            $categoria
        );

        if ($erro === "") {

            try {
                $moderacao = moderarTextoComAlgoritmos($conteudo);

                if (($moderacao["result"] ?? "") !== "APROVADO") {
                    $erro = match ($moderacao["result"] ?? "") {
                        "CURTO_DEMAIS" =>
                            "A publicacao deve ter pelo menos 25 palavras.",
                        "SPAM" =>
                            "A publicacao foi sinalizada como spam.",
                        "DUPLICADO" =>
                            "Esta publicacao ja foi enviada recentemente.",
                        "PROIBIDO" =>
                            "A publicacao contem termos bloqueados pela moderacao.",
                        default =>
                            "A publicacao nao passou pela moderacao."
                    };
                }

                if ($erro !== "") {
                    throw new DomainException($erro);
                }

                $conexao = conectarBD();

                $sql = "
                    INSERT INTO publicacoes
                    (
                        usuario_id,
                        titulo,
                        conteudo,
                        categoria,
                        estado
                    )
                    VALUES
                    (
                        :usuario_id,
                        :titulo,
                        :conteudo,
                        :categoria,
                        'publicado'
                    )
                ";

                $stmt = $conexao->prepare($sql);

                $stmt->execute([
                    ":usuario_id" =>
                        $_SESSION["usuario_id"],

                    ":titulo" => $titulo,

                    ":conteudo" => $conteudo,

                    ":categoria" => $categoria
                ]);

                definirMensagem(
                    "sucesso",
                    "Publicacao criada com sucesso!"
                );

                redireccionar("feed.php");

            } catch (DomainException $ex) {
                $erro = $ex->getMessage();
            } catch (SubterraAlgorithmException $ex) {
                error_log("[SUBTERRA ALGORITMOS] " . $ex->getMessage());
                $erro = "O servico de algoritmos esta indisponivel. Tenta novamente.";
            } catch (PDOException $ex) {
                error_log("[SUBTERRA CRIAR] " . $ex->getMessage());
                $erro = "Nao foi possivel criar a publicacao.";
            } catch (Throwable $ex) {

                error_log(
                    "[SUBTERRA CRIAR] " .
                    $ex->getMessage()
                );

                $erro = "Nao foi possivel criar a publicacao.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Nova publicacao | SUBTERRA</title>

    <link rel="stylesheet"
          href="../css/style.css">

    <link rel="stylesheet"
          href="../css/publicacoes.css">

</head>

<body>

<header class="cabecalho">

    <a class="logo" href="../feed.php">
        SUBTERRA<span>.</span>
    </a>

    <nav class="nav-links">

        <a class="nav-link" href="../feed.php">
            Voltar ao feed
        </a>

    </nav>

</header>

<main class="editor-shell">

    <div class="editor-heading">

        <p class="etiqueta">
            / PUBLICACAO ANONIMA
        </p>

        <h1>Partilha uma ideia.</h1>

        <p>
            Escreve livremente.
            O teu pseudonimo nao sera
            apresentado na publicacao.
        </p>

    </div>

    <?php if ($erro !== ""): ?>

        <div class="alert alert-error"
             role="alert">
            <?= escapar($erro) ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          class="editor-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(tokenCSRF()) ?>"
        >

        <div class="field">

            <label for="titulo">
                Titulo
            </label>

            <input
                class="input"
                type="text"
                id="titulo"
                name="titulo"
                placeholder="Sobre o que queres falar?"
                maxlength="160"
                minlength="5"
                value="<?= escapar($titulo) ?>"
                required
            >

        </div>

        <div class="field">

            <label for="categoria">
                Categoria
            </label>

            <select
                class="input"
                id="categoria"
                name="categoria"
                required
            >

                <?php foreach (
                    categoriasPublicacoes()
                    as $codigo => $nome
                ): ?>

                    <option
                        value="<?= escapar($codigo) ?>"
                        <?= $categoria === $codigo
                            ? "selected"
                            : "" ?>
                    >
                        <?= escapar($nome) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="field">

            <label for="conteudo">
                Conteudo
            </label>

            <textarea
                class="input textarea-editor"
                id="conteudo"
                name="conteudo"
                placeholder="Escreve aqui a tua publicacao..."
                minlength="10"
                maxlength="5000"
                required
            ><?= escapar($conteudo) ?></textarea>

            <small>
                Entre 25 palavras e 5000 caracteres. A moderacao automatica
                verifica tamanho, links e repeticoes.
            </small>

        </div>

        <div class="editor-actions">

            <a class="btn btn-outline"
               href="../feed.php">
                Cancelar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
            >
                Publicar anonimamente
            </button>

        </div>

    </form>

</main>

</body>
</html>
