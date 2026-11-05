# SUBTERRA

Comunidade de publicacoes e conversas anonimas, feita em PHP, MySQL, HTML,
CSS, JavaScript e Java 17. As publicacoes, comentarios e mensagens nao mostram o
pseudonimo do autor aos outros membros; as contas continuam associadas aos
registos para permitir gestao e moderacao.

## Executar localmente

Requisitos: PHP 8.1 ou superior com `pdo_mysql`, MySQL 8.0.16 ou superior e
JDK 17 ou superior.

1. Importa `app/subterra_app.sql` num servidor MySQL com permissao para criar a
   base de dados:

   ```sh
   mysql -u root -p < app/subterra_app.sql
   ```

2. Cria um utilizador MySQL dedicado. Substitui a palavra-passe de exemplo por
   uma palavra-passe unica; nao a guardes no repositorio:

   ```sql
   CREATE USER 'subterra_user'@'localhost' IDENTIFIED BY 'substitui-esta-senha';
   GRANT SELECT, INSERT, UPDATE, DELETE ON subterra_app.* TO 'subterra_user'@'localhost';
   ```

3. Compila e inicia o servico Java de algoritmos numa janela de terminal:

   ```sh
   ./algoritmos-java/build.sh
   java -jar algoritmos-java/build/subterra-algoritmos.jar
   ```

4. Configura as ligacoes e inicia o servidor PHP com `app/` como raiz publica,
   noutra janela de terminal:

   ```sh
   export SUBTERRA_DB_HOST=127.0.0.1
   export SUBTERRA_DB_PORT=3306
   export SUBTERRA_DB_NAME=subterra_app
   export SUBTERRA_DB_USER=subterra_user
   export SUBTERRA_DB_PASSWORD='a-tua-senha'
   export SUBTERRA_ALGORITHM_URL=http://127.0.0.1:8787
   php -S 127.0.0.1:8000 -t app
   ```

5. Abre <http://127.0.0.1:8000>. As variaveis de ambiente tambem podem ser
   definidas no servico PHP/Apache. `SUBTERRA_BASE_PATH` e opcional; usa-o
   apenas se a aplicacao estiver montada num subcaminho especifico.

Para verificar a ligacao, abre `/api/teste_conexao.php`. Erros detalhados
ficam no registo do servidor, nao na resposta publica.

## CMS (Directus)

O projecto inclui o Directus como CMS headless para gerir conteudo e ficheiros
da mesma base de dados. No Linux, cria uma conta MySQL dedicada para o CMS
(substitui a palavra-passe):

```sql
CREATE USER 'subterra_cms'@'%' IDENTIFIED BY 'uma-senha-unica';
GRANT ALL PRIVILEGES ON subterra_app.* TO 'subterra_cms'@'%';
```

O acesso SQL da conta fica limitado a `subterra_app`. Para executar o CMS:

```sh
cp .env.cms.example .env.cms
openssl rand -hex 32  # gera valores para KEY e SECRET
```

Edita `.env.cms` com dois valores aleatorios diferentes para `KEY` e `SECRET`,
uma senha exclusiva em `ADMIN_PASSWORD` e a senha da conta `subterra_cms` em
`DB_PASSWORD`. Depois inicia o Directus:

```sh
docker compose -f compose.cms.yml up -d
```

Abre <http://127.0.0.1:8055> e entra com `ADMIN_EMAIL` e `ADMIN_PASSWORD` do
ficheiro `.env.cms`. O Directus usa o esquema existente; nao alteres nem apagues
as tabelas tecnicas `directus_*` ou as tabelas centrais da aplicacao. O painel
de moderacao PHP continua a gerir denuncias e o Directus fica para gestao de
conteudo/media. Regista as colecoes existentes no primeiro arranque e depois de
reinicializar uma base de dados:

```sh
python3 scripts/register-directus-collections.py
```

O painel CMS mostra publicacoes, comentarios, chat e denuncias. As colecoes de
utilizadores e votos ficam ocultas para evitar expor dados de autenticacao ou
mostrar dados internos que nao sao conteudo editorial. O site publico continua
em <http://127.0.0.1:8000>; Directus e apenas a area administrativa.

Esta configuracao e para desenvolvimento local em Linux: o Directus fica
acessivel apenas em `127.0.0.1`. Para producao, configura TLS e um proxy reverso
com acesso restrito; nunca publiques as credenciais do CMS nem exponhas a porta
da base de dados.

## Funcionalidades

- Registo, login, sessao privada e logout com proteccao CSRF.
- Feed de publicacoes anonimas, categorias, votos positivos/negativos,
  comentarios, edicao, eliminacao e denuncias.
- Chat publico anonimo com actualizacao automatica.
- Serviço Java para moderação heurística, ranking por densidade/Min-Cut e
  controlo de frequência de mensagens.
- Painel de moderacao para analisar denuncias, ocultar/restaurar publicacoes
  e remover mensagens.
- CMS Directus ligado a MySQL para administracao de conteudo/media.
- Interface adaptavel a telemoveis, com uma apresentacao de imageboard simples
  com estrutura de imageboard, paleta de terminal escura, tipografia
  monoespaçada e acentos discretos.

Para promover um membro a moderador, depois de criar a conta, executa como
administrador da base de dados:

```sql
UPDATE usuarios SET papel = 'moderador' WHERE pseudonimo = 'nome_do_membro';
```

### Base de dados existente

Se a base de dados ja foi criada com uma versao anterior do esquema, aplica
`app/migrations/001-voting-and-reports.sql` uma vez. Se ja houver denuncias
duplicadas da mesma conta para a mesma publicacao, remove os duplicados antes
de criar a chave unica.

O algoritmo não substitui moderação humana e o chat continua a usar polling
PHP/MySQL (não WebSocket). As limitações e os endpoints do serviço estão
documentados em `algoritmos-java/README.md`.
