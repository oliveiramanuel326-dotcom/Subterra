-- Apply only to databases created before these columns/keys were added
-- to subterra_app.sql.
ALTER TABLE votos
    ADD COLUMN valor TINYINT NOT NULL DEFAULT 1,
    ADD CONSTRAINT voto_valor_valido CHECK (valor IN (-1, 1));

ALTER TABLE denuncias
    ADD UNIQUE KEY denuncia_unica (publicacao_id, usuario_id);
