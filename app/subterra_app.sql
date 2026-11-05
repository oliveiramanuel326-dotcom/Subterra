
-- ============================================
-- SUBTERRA - BASE DE DADOS CORRIGIDA
-- MySQL Server - Porta 3306
-- ============================================

-- 1. CRIAR E SELECCIONAR A BASE DE DADOS

CREATE DATABASE IF NOT EXISTS subterra_app
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE subterra_app;


-- 2. TABELA DE UTILIZADORES

CREATE TABLE IF NOT EXISTS usuarios (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    pseudonimo VARCHAR(60) NOT NULL UNIQUE,

    senha_hash VARCHAR(255) NOT NULL,

    papel ENUM(
        'usuario',
        'moderador',
        'admin'
    ) NOT NULL DEFAULT 'usuario',

    criado_em TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- 3. TABELA DE PUBLICACOES

CREATE TABLE IF NOT EXISTS publicacoes (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id BIGINT UNSIGNED NOT NULL,

    titulo VARCHAR(160) NOT NULL,

    conteudo TEXT NOT NULL,

    categoria VARCHAR(80)
        NOT NULL DEFAULT 'geral',

    pontuacao DOUBLE NOT NULL DEFAULT 0,

    estado ENUM(
        'publicado',
        'pendente',
        'oculto'
    ) NOT NULL DEFAULT 'publicado',

    criado_em TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- 4. TABELA DE COMENTARIOS

CREATE TABLE IF NOT EXISTS comentarios (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    publicacao_id BIGINT UNSIGNED NOT NULL,

    usuario_id BIGINT UNSIGNED NOT NULL,

    conteudo TEXT NOT NULL,

    criado_em TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (publicacao_id)
        REFERENCES publicacoes(id)
        ON DELETE CASCADE,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- 5. TABELA DE VOTOS

CREATE TABLE IF NOT EXISTS votos (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    publicacao_id BIGINT UNSIGNED NOT NULL,

    usuario_id BIGINT UNSIGNED NOT NULL,

    valor TINYINT NOT NULL DEFAULT 1,

    UNIQUE KEY voto_unico (
        publicacao_id,
        usuario_id
    ),

    CONSTRAINT voto_valor_valido
        CHECK (valor IN (-1, 1)),

    FOREIGN KEY (publicacao_id)
        REFERENCES publicacoes(id)
        ON DELETE CASCADE,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- 6. TABELA DE MENSAGENS

CREATE TABLE IF NOT EXISTS mensagens (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id BIGINT UNSIGNED NOT NULL,

    conteudo VARCHAR(500) NOT NULL,

    criado_em TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- 7. TABELA DE DENUNCIAS

CREATE TABLE IF NOT EXISTS denuncias (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    publicacao_id BIGINT UNSIGNED NOT NULL,

    usuario_id BIGINT UNSIGNED NOT NULL,

    motivo VARCHAR(500) NOT NULL,

    estado ENUM(
        'pendente',
        'resolvida'
    ) NOT NULL DEFAULT 'pendente',

    UNIQUE KEY denuncia_unica (
        publicacao_id,
        usuario_id
    ),

    criado_em TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (publicacao_id)
        REFERENCES publicacoes(id),

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- 8. VERIFICAR AS TABELAS

SHOW TABLES;


-- 9. VERIFICAR SERVIDOR E BASE DE DADOS

SELECT
    @@hostname AS servidor,
    @@port AS porta,
    DATABASE() AS base_dados;


-- 10. CONSULTAR UTILIZADORES REGISTADOS

SELECT
    id,
    pseudonimo,
    papel,
    criado_em
FROM usuarios
ORDER BY id DESC;


-- 11. VERIFICAR ESTRUTURA DA TABELA

DESCRIBE usuarios;


USE subterra_app;

ALTER TABLE publicacoes
MODIFY COLUMN estado ENUM(
    'publicado',
    'pendente',
    'oculto',
    'eliminado'
) NOT NULL DEFAULT 'publicado';

DESCRIBE publicacoes;

-- Consultar publicacoes
SELECT
    id,
    titulo,
    categoria,
    estado,
    criado_em
FROM publicacoes
ORDER BY id DESC;

-- Quantidade de publicacoes activas
SELECT COUNT(*) AS publicacoes_activas
FROM publicacoes
WHERE estado = 'publicado';

-- Publicacoes eliminadas logicamente
SELECT
    id,
    titulo,
    estado
FROM publicacoes
WHERE estado = 'eliminado';

-- Consultar os votos
SELECT * FROM votos;

-- Confirmar a tabela de comentarios
DESCRIBE comentarios;

USE subterra_app;

-- Ver os utilizadores e respectivos IDs
SELECT id, pseudonimo
FROM usuarios
ORDER BY id;

-- Ver quem criou cada publicacao
SELECT
    p.id AS publicacao_id,
    p.titulo,
    p.usuario_id AS autor_id,
    u.pseudonimo AS autor
FROM publicacoes p
INNER JOIN usuarios u
    ON p.usuario_id = u.id
ORDER BY p.id DESC;

-- Ver quem criou cada comentario
SELECT
    c.id AS comentario_id,
    c.publicacao_id,
    c.usuario_id AS autor_id,
    u.pseudonimo AS autor
FROM comentarios c
INNER JOIN usuarios u
    ON c.usuario_id = u.id
ORDER BY c.id DESC;