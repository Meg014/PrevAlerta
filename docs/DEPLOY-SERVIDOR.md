# PrevAlerta — servidor central com PostgreSQL

## 1. Pré-requisitos

O banco oficial é **PostgreSQL**. A aplicação continua em CakePHP 5.4.1/PHP 8.2 ou superior, com Composer 2, Git e Apache/PHP-FPM (ou equivalente). PostgreSQL 16.14 foi usado na validação. A TI pode aproveitar o PostgreSQL 16 da empresa. O banco será novo; não será feita transferência dos dados de teste do MariaDB.

O app Windows continua acessando somente a URL do servidor. Não instale PHP ou banco nos clientes.

### Extensões PHP

O driver CakePHP **Cake\Database\Driver\Postgres** requer **pdo_pgsql**. Habilite também **pgsql**, além de intl, mbstring, dom, simplexml, xml, xmlwriter, curl, openssl e zip.

No Ubuntu, instale o pacote correspondente à versão do PHP do serviço. Exemplo para PHP 8.3:

~~~bash
sudo apt update
sudo apt install php8.3-pgsql
php -m | grep -E 'PDO|pdo_pgsql|pgsql'
~~~

Para PHP 8.2, use php8.2-pgsql; para outra série, substitua o número. Confira tanto o PHP da CLI quanto o PHP-FPM/Apache e reinicie o serviço correto após habilitar a extensão. No Windows, habilite extension=pdo_pgsql e extension=pgsql no php.ini efetivamente carregado.

Confira php --version, php --ini, php -m, composer --version e psql --version. pdo_mysql e MariaDB não são mais necessários. [Driver Postgres do CakePHP](https://api.cakephp.org/5.3/class-Cake.Database.Driver.Postgres.html).

## 2. Código e dependências

Na pasta de implantação, substituindo os marcadores pelos dados aprovados:

~~~text
git clone URL_DO_REPOSITORIO prevalerta
cd prevalerta
git checkout BRANCH_APROVADA
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
~~~

Use composer install para respeitar o lock. O CSS compilado em webroot/css/app.css acompanha o código; Node.js só é necessário na preparação de assets. Não envie config/app_local.php, .env, bancos, backups, tmp/ ou logs/ ao Git.

## 3. Criar usuário e banco

**Exemplos:** usuário pcm e banco prev_alerta. A TI pode definir outros nomes. No Ubuntu, abra o psql administrativo:

~~~bash
sudo -u postgres psql
~~~

Em outros ambientes, use a conta administrativa aprovada pela TI. Dentro do psql:

~~~sql
CREATE ROLE pcm LOGIN;
\password pcm
CREATE DATABASE prev_alerta OWNER pcm ENCODING 'UTF8' TEMPLATE template0;
\connect prev_alerta
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO pcm;
~~~

O comando \password solicita a senha sem gravá-la no exemplo. Não use a conta administrativa na aplicação. Como proprietário do banco e dos objetos criados, pcm pode aplicar as migrations e executar a aplicação, sem SUPERUSER ou CREATEDB. Se houver uma conta separada de execução, conceda USAGE no schema, SELECT/INSERT/UPDATE/DELETE nas tabelas e USAGE/SELECT nas sequências, incluindo objetos futuros da conta de migrations.

Configure listen_addresses e pg_hba.conf para o host do PHP. Se PHP e PostgreSQL estiverem na mesma máquina, uma regra de exemplo é:

~~~text
host  prev_alerta  pcm  127.0.0.1/32  scram-sha-256
~~~

Para banco remoto, autorize somente o servidor PHP e configure TLS conforme a política da TI. Recarregue PostgreSQL após alterar sua configuração. Não abra 5432 aos PCs clientes e não use trust em produção.

## 4. Configurar CakePHP

Copie config/app_local.example.php para config/app_local.php se ainda não existir. O Composer pode já ter criado o arquivo; nesse caso, edite-o preservando Security.salt. **O arquivo local antigo precisa ser ajustado:** mudar somente o exemplo não substitui uma configuração MariaDB já existente.

Exemplo sem credenciais reais:

~~~php
<?php
use Cake\Database\Driver\Postgres;

return [
    'debug' => false,
    'Security' => ['salt' => 'MANTER_O_SALT_GERADO_PARA_ESTE_AMBIENTE'],
    'App' => ['fullBaseUrl' => 'http://prevalerta'],
    'LocalAccess' => ['enabled' => false, 'allowRemote' => false],
    'Datasources' => [
        'default' => [
            'driver' => Postgres::class,
            'host' => '127.0.0.1',
            'port' => 5432,
            'username' => 'pcm',
            'password' => 'DEFINIR_NO_SERVIDOR',
            'database' => 'prev_alerta',
            'schema' => 'public',
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'quoteIdentifiers' => true,
            'url' => null,
        ],
    ],
];
~~~

O exemplo do projeto aceita DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME e DB_SCHEMA por ambiente. DATABASE_URL também pode ser usado com esquema postgres://; confira se não continua apontando ao MySQL/banco antigo. Remova opções antigas como utf8mb4 e flags PDO específicas de MySQL. PostgreSQL usa utf8.

O projeto não lê .env automaticamente: configure app_local.php ou variáveis reais na CLI e no serviço. Não coloque senha, IP interno ou usuário real no repositório. Preserve o salt nas atualizações; no ambiente novo, gere-o com:

~~~text
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
~~~

No servidor central, LocalAccess.enabled=false seleciona o login existente. App.fullBaseUrl deve usar o host definido no DNS, pois o middleware verifica o Host. Configure HTTPS e a URL final tanto no servidor quanto em serverUrl do config.json do cliente. Trocar o banco não requer mudança no executável Windows.

## 5. Conferir conexão e aplicar migrations

Confirme usuário/banco/rede:

~~~bash
psql -h 127.0.0.1 -p 5432 -U pcm -d prev_alerta -W -c "SELECT current_database(), current_user, version();"
~~~

Na raiz do projeto, com a mesma configuração do PHP do serviço:

~~~text
php bin/cake.php migrations migrate
php bin/cake.php migrations status
php bin/cake.php schema_cache clear
php bin/cake.php cache clear_all
~~~

As quatro migrations devem constar como **up**. Esses comandos também validam a conexão CakePHP com PostgreSQL. Não importe SQL de MariaDB nem schema-dump antigo: as migrations PHP são a fonte do schema. config/Migrations/schema-dump-*.lock é cache local ignorado pelo Git.

### Compatibilidade revisada

| Item | PostgreSQL |
| --- | --- |
| IDs automáticos | Identity gerada pelo adaptador de migrations |
| Booleanos | boolean, incluindo default true de users.active |
| datetime | timestamp without time zone; aplicação e conexão permanecem em UTC |
| date | date; recorrência preservada |
| string/text | varchar com os limites existentes / text |
| Nullable/defaults | Preservados, incluindo materialized_cycle=-1 |
| Índices e FKs | Preservados, incluindo RESTRICT |
| Unicidade | Código, e-mail, programação/data e auditoria CIENTE preservados |
| DECIMAL/ENUM | Não utilizados nas migrations atuais |

Os serviços mantêm suas transações e a ordem dos bloqueios por checklist/ocorrência. FOR UPDATE é compatível com PostgreSQL e foi preservado. Bloqueios e índices únicos continuam protegendo contra duplicidade. [Bloqueios PostgreSQL](https://www.postgresql.org/docs/16/explicit-locking.html).

As buscas de checklists e histórico usam ILIKE pelo Query Builder para manter a busca sem distinguir maiúsculas/minúsculas no PostgreSQL. Não houve alteração nos registros ou nas regras do histórico. [Comparação de padrões PostgreSQL](https://www.postgresql.org/docs/16/functions-matching.html).

config/schema/sessions.sql e config/schema/i18n.sql são modelos legados MySQL do esqueleto CakePHP, **não utilizados** pelas migrations ou pelo sistema atual. Não execute esses modelos no PostgreSQL. O sistema usa sessões em arquivos (Session.defaults=cake) e não utiliza o schema i18n; não existe dependência operacional desses scripts.

## 6. Administrador e servidor HTTP

Crie o primeiro administrador no Windows:

~~~powershell
.\scripts\create-admin.ps1 -Email 'administrador@empresa.local' -Name 'Administrador'
~~~

Ou no Bash:

~~~bash
read -rsp 'Senha do administrador (12 a 72 caracteres): ' PREV_ADMIN_PASSWORD
echo
export PREV_ADMIN_PASSWORD
php bin/cake.php create_admin administrador@empresa.local Administrador
unset PREV_ADMIN_PASSWORD
~~~

Se já houver um administrador ativo, use a tela Usuários.

Configure Apache/PHP-FPM como serviço, com DocumentRoot apontando **somente para webroot/**. Exemplo HTTP de associação de host/diretório, com PHP previamente habilitado:

~~~apache
<VirtualHost *:80>
    ServerName prevalerta
    DocumentRoot "/srv/prevalerta/webroot"
    <Directory "/srv/prevalerta/webroot">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
~~~

No Windows, adapte para C:/sites/prevalerta/webroot. Ative mod_rewrite, confira apachectl configtest (ou httpd -t) e recarregue. Garanta escrita do PHP em tmp/ e logs/ e restrinja app_local.php à TI/serviço. Configure HTTPS com certificado confiável pelos PCs antes do uso com credenciais reais.

Cadastre DNS interno e libere HTTP/HTTPS entre clientes e servidor; o banco só recebe conexões do PHP. Confirme login, dashboard e /checklists pela URL definitiva. O acesso por IP pode ser rejeitado quando o host configurado for diferente.

scripts/start-local.ps1 serve apenas para validação: inicia PHP e exige PostgreSQL já disponível. Não inicia mais MariaDB. Não use php -S em produção.

## 7. Validação e concorrência

Em 16/09/2026, as quatro migrations foram aplicadas em banco PostgreSQL **16.14 vazio**, numa instância temporária isolada. Tipos, índices únicos e FKs foram inspecionados. Foram reutilizados **9 testes existentes, com 88 assertions**, cobrindo abertura/login, cadastro/edição HTTP, dashboard/histórico após CIENTE, repetição de CIENTE, rollback de auditoria, pausa/reativação e requisições concorrentes. Todos passaram.

Nenhuma suíte nova foi criada. Serviços de negócio, migrations, layout, app Windows e notificações não foram alterados nesta adaptação. A CI existente passou a usar PostgreSQL/pdo_pgsql. Para testes, configure a conexão test para outro banco, nunca o de produção. A TI deve homologar a configuração real do servidor.

## 8. Backup e atualização via Git

Antes de atualizar:

~~~text
git rev-parse HEAD
pg_dump -h 127.0.0.1 -p 5432 -U pcm -W -Fc -f CAMINHO_SEGURO/prev_alerta.dump prev_alerta
~~~

Guarde app_local.php/salt separadamente, em local protegido. Para restaurar, use pg_restore em banco apropriado e homologue o backup.

Em janela de manutenção, suspenda acessos/gravações e agendamentos. Na branch aprovada:

~~~text
git status --short
git pull --ff-only origin BRANCH_APROVADA
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php bin/cake.php migrations migrate
php bin/cake.php migrations status
php bin/cake.php schema_cache clear
php bin/cake.php cache clear_all
~~~

Resolva divergências/alterações locais antes de prosseguir. Preserve configuração, salt e banco; recarregue PHP/Apache e valide antes de liberar acessos. Rollback após alteração de schema exige código e backup compatíveis.

A sincronização opcional php bin/cake.php sync_occurrences continua somente no servidor. Não é preciso reinstalar o cliente para trocar MariaDB por PostgreSQL; mantenha a interface HTTP e a URL configurada pela TI.
