# PrevAlerta — implantação no servidor interno

## 1. Entrega e arquitetura

Os PCs executam somente o cliente Windows. O servidor hospeda CakePHP, PHP, servidor HTTP e MariaDB. O instalador Windows não inclui código PHP, banco nem credenciais.

A TI recebe `dist/` e precisa receber acesso ao **repositório do projeto separadamente**. Esta pasta de trabalho não tem metadados `.git`; o endereço do repositório e a versão de produção ainda precisam ser definidos pela equipe. Os exemplos `URL_DO_REPOSITORIO` e `BRANCH_APROVADA` abaixo devem ser substituídos. Não envie `config/app_local.php`, `.env`, bancos, `tmp/` ou `logs/` junto com o código.

Defina um nome no DNS interno, inicialmente `prevalerta`, apontando para o IP fixo do servidor. O cliente é entregue com `http://prevalerta/`; quando a TI definir HTTPS/nome definitivo, ajuste a mesma URL em `App.fullBaseUrl` no servidor e `serverUrl` nos clientes.

## 2. Pré-requisitos do servidor

- PHP **8.2 ou superior**, compatível com `composer.lock`, na linha de comando e no servidor HTTP. Habilitar `intl`, `mbstring`, `pdo_mysql`, `dom`, `simplexml`, `xml`, `xmlwriter`, `curl`, `openssl` e `zip`; JSON/PDO também precisam estar disponíveis.
- Composer 2, Git e MariaDB como serviço com início automático.
- Apache 2.4 com PHP habilitado e `mod_rewrite`, ou servidor HTTP equivalente configurado para CakePHP. Este guia usa Apache como exemplo.
- Conta de implantação para código/Composer e conta de serviço do PHP com escrita em `tmp/` e `logs/`. O restante do código precisa somente de leitura pelo serviço.
- Acesso ao repositório e às dependências pelo servidor ou por uma etapa de preparação aprovada pela TI.

Em Windows Server, instale esses componentes e configure Apache/PHP e MariaDB como serviços. Em Linux, use os pacotes mantidos pela distribuição ou pela TI. Ajuste os caminhos dos exemplos ao sistema escolhido. Não use `php -S`, `start-local.ps1` ou a instância de desenvolvimento em `tmp/mariadb` para hospedar produção.

Confira antes de prosseguir:

```text
php --version
php --ini
php -m
composer --version
git --version
mariadb --version
```

O CSS já compilado em `webroot/css/app.css` deve acompanhar o código. Node.js não é necessário para executar a aplicação; só é usado na etapa de build quando houver alterações nos assets. Referência: [instalação do CakePHP](https://book.cakephp.org/5/en/installation.html).

## 3. Obter o código e instalar dependências

Use uma pasta fora da raiz pública genérica do servidor, por exemplo `C:/sites/prevalerta` no Windows ou `/srv/prevalerta` no Linux:

```text
git clone URL_DO_REPOSITORIO prevalerta
cd prevalerta
git checkout BRANCH_APROVADA
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Execute o Composer com a conta de implantação. Use `composer install` para respeitar as versões do lock; não use `composer update` como procedimento de implantação. O PHP da linha de comando e o do serviço HTTP devem ter as mesmas extensões. [Comandos do Composer](https://getcomposer.org/doc/03-cli.md).

## 4. Criar o banco

Conecte-se ao MariaDB no próprio servidor com uma conta administrativa. Exemplo para banco novo, usando conexão TCP local:

```sql
CREATE DATABASE prev_agenda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'prev_agenda'@'127.0.0.1' IDENTIFIED BY 'SUBSTITUIR_POR_SENHA_EXCLUSIVA';
GRANT ALL PRIVILEGES ON prev_agenda.* TO 'prev_agenda'@'127.0.0.1';
```

A senha acima é um marcador, não uma credencial para uso. As permissões ficam limitadas a esse banco e permitem aplicar migrations. A TI pode separar uma conta de migrations e uma conta de execução conforme sua política. Não use `root` na aplicação. Se o banco estiver em outro servidor, adapte host, conta e firewall para aceitar somente o servidor da aplicação. [Criação de usuários](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/create-user) e [permissões no MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant).

Para transportar dados existentes, faça backup/restauração do banco completo, incluindo o histórico das migrations. Não copie o diretório físico de dados do ambiente de desenvolvimento. Não importe esquemas de teste nem recrie tabelas sobre dados existentes.

## 5. Configuração exclusiva do servidor

Crie `config/app_local.php` a partir de `config/app_local.example.php` se ainda não existir. O script do Composer também pode ter criado esse arquivo; nesse caso, edite-o e preserve o `Security.salt` já gerado.

Exemplo mínimo de configuração final (substitua os marcadores):

```php
<?php
return [
    'debug' => false,
    'Security' => ['salt' => 'SUBSTITUIR_POR_VALOR_ALEATORIO_EXCLUSIVO'],
    'App' => ['fullBaseUrl' => 'http://prevalerta'],
    'LocalAccess' => ['enabled' => false, 'allowRemote' => false],
    'Datasources' => [
        'default' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'prev_agenda',
            'password' => 'SUBSTITUIR_POR_SENHA_EXCLUSIVA',
            'database' => 'prev_agenda',
            'encoding' => 'utf8mb4',
            'url' => null,
        ],
    ],
];
```

Para um ambiente novo, gere o salt com `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` e guarde o valor no arquivo. Não regenere esse valor a cada atualização.

**Defina explicitamente `LocalAccess.enabled=false` no servidor central.** A configuração padrão do projeto é voltada à validação local; o servidor de rede deve utilizar o login e os usuários já implementados. Não habilite `allowRemote` para contornar login. Essa configuração seleciona o mecanismo de autenticação existente, sem mudar regras de negócio.

O host de `App.fullBaseUrl` deve corresponder ao nome usado no acesso: com `debug=false`, o middleware rejeita outro cabeçalho Host. Acesso direto pelo IP pode ser rejeitado mesmo com o servidor funcionando. Para HTTPS, configure `https://nome-definitivo` e certificado confiável pelos PCs; a URL do cliente deve já usar o esquema/host final, sem depender de redirecionamento entre origens.

O projeto não carrega `.env` automaticamente. Configure diretamente `app_local.php` ou variáveis reais no ambiente do serviço e da CLI. Restrinja a leitura desse arquivo à TI e à conta do PHP. Não o envie no pacote Windows nem o registre no Git. As configurações restantes, inclusive timezone/recorrência, continuam herdadas do projeto.

## 6. Aplicar migrations e criar o administrador

Na pasta do projeto, com acesso ao banco correto:

```text
php bin/cake.php migrations migrate
php bin/cake.php migrations status
php bin/cake.php schema_cache clear
```

Confirme que as quatro migrations fornecidas foram aplicadas. Para um banco existente, faça backup antes de executar. Garanta que a conta do PHP tenha escrita em `tmp/` e `logs/`, incluindo cache e sessão; não dê escrita global ao código.

No Windows, crie o primeiro administrador pelo script existente (a senha é solicitada sem exibição):

```powershell
.\scripts\create-admin.ps1 -Email 'administrador@empresa.local' -Name 'Administrador'
```

No Linux, usando Bash e sem colocar a senha no histórico:

```bash
read -rsp 'Senha do administrador (12 a 72 caracteres): ' PREV_ADMIN_PASSWORD
echo
export PREV_ADMIN_PASSWORD
php bin/cake.php create_admin administrador@empresa.local Administrador
unset PREV_ADMIN_PASSWORD
```

O comando recusa criar outro administrador se já houver um ativo. Nesse caso, use a tela Usuários com a conta existente.

## 7. Publicar no Apache e liberar a rede interna

O DocumentRoot deve ser **somente `webroot/`**, nunca a raiz inteira do projeto. Exemplo de VirtualHost HTTP para validação interna (substitua o caminho; PHP precisa estar previamente habilitado no Apache):

```apache
<VirtualHost *:80>
    ServerName prevalerta
    DocumentRoot "C:/sites/prevalerta/webroot"
    <Directory "C:/sites/prevalerta/webroot">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Em Linux, use `/srv/prevalerta/webroot` no exemplo. Ative `mod_rewrite`, valide a configuração com `httpd -t` (ou `apachectl configtest`) e reinicie/recarregue o serviço. O `.htaccess` em `webroot/` encaminha rotas para `index.php`. Configure a política de acesso restrita à rede/VPN interna no firewall. Adote TLS no servidor para transportar login/sessão; o bloco acima mostra apenas a associação de host e diretório.

Libere somente a porta HTTP/HTTPS utilizada entre PCs e servidor. MariaDB deve permanecer acessível somente ao servidor PHP; não abra 3306 para os clientes. Cadastre o DNS interno e confira de outro PC:

```powershell
Resolve-DnsName prevalerta
Test-NetConnection prevalerta -Port 80
```

Para HTTPS, use a porta 443 e o nome escolhido. Abra a URL final, confirme o login e o dashboard. A aplicação deve carregar CSS, ícone e rotas como `/checklists`; se apenas a página inicial funcionar, revise rewrite. Verifique se arquivos como `/config/app_local.php` não estão publicados.

## 8. Entregar aos PCs e homologar

Envie a pasta `dist/`. Em cada conta de usuário, execute o Setup, ajuste `config.json` ao lado do executável e siga `README-TI.txt`. Nenhum PHP, Composer ou banco é necessário no PC. Para máquinas compartilhadas, esta versão do instalador precisa ser executada para cada usuário que usará o app.

Faça a homologação manual com a URL definitiva:

1. Login, dashboard e navegação com o layout existente.
2. Sessão autenticada consultando `/desktop/alert-summary`; a resposta deve ser JSON com `overdue` e `today`. A chamada sem sessão não deve liberar dados.
3. Consulta real pelo script de suporte fornecido e clique abrindo/restaurando o app. O script usa a sessão do app e os totais do servidor, sem dados fictícios; sem pendências, não mostra aviso. O cliente continua consultando a cada minuto e atualiza o aviso quando os totais mudam.
4. Saída/entrada no Windows com a opção de início automático habilitada.
5. Indisponibilidade temporária do servidor exibindo mensagem amigável no app.

O dashboard sincroniza ocorrências ao abrir. Opcionalmente a TI pode agendar `php bin/cake.php sync_occurrences` diariamente **no servidor**, com diretório de trabalho e credenciais de ambiente corretos; não é necessário agendar nos clientes.

## 9. Backup e atualizações via Git

Antes de atualizar, registre o commit atual, faça backup do banco e de `config/app_local.php` e confirme que há um procedimento de restauração. Por exemplo, no servidor:

```text
git rev-parse HEAD
mariadb-dump -h 127.0.0.1 -u USUARIO_BACKUP -p --single-transaction --routines --triggers --events --result-file=CAMINHO_SEGURO/prev_agenda.sql prev_agenda
```

Use uma conta de backup com permissões apropriadas e uma pasta protegida fora de `webroot/`; as credenciais são informadas pela TI. [Backup com mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

Em janela de manutenção, suspenda acessos/gravações pelo servidor HTTP e agendamentos de sincronização, sem alterar o código. Na branch aprovada:

```text
git status --short
git pull --ff-only origin BRANCH_APROVADA
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php bin/cake.php migrations migrate
php bin/cake.php migrations status
php bin/cake.php schema_cache clear
php bin/cake.php cache clear_all
```

Se houver alterações locais ou divergência de branch, pare e resolva com a equipe responsável; não force substituições. Preserve `config/app_local.php`, salt e banco. Recarregue PHP/Apache para limpar OPcache, retome agendamentos e valide login, dashboard e resumo antes de liberar o acesso.

Se a atualização incluir assets, utilize o CSS compilado pela equipe; quando a TI também fizer o build, execute `npm ci` e `npm run build` na etapa de preparação. Isso não é necessário nos PCs.

Atualizar o CakePHP não exige reinstalar o cliente quando a interface HTTP permanecer compatível. Para mudança no executável, distribua um novo Setup; a configuração existente é preservada. Em falha após uma migration, o rollback deve combinar a versão anterior do código e a restauração do backup compatível, conforme o plano da TI.

## 10. Limites desta entrega

O pacote foi validado nesta máquina por instalação, atualização preservando a URL, criação/remoção de atalhos e desinstalação. O executável instalado abriu o servidor local com HTTP 200. O WebView2 existente foi detectado; a instalação do Runtime ausente ainda precisa ser homologada em PC limpo. Esses procedimentos não alteraram código ou regras de negócio e não criaram testes automatizados.

O instalador é entregue com `http://prevalerta/` como nome a ser configurado pela TI. DNS, certificado, credenciais, repositório definitivo e servidor de produção ainda não foram provisionados nesta entrega. A homologação na infraestrutura da empresa e em Windows sem WebView2 pré-instalado cabe à TI antes da distribuição geral. Nenhum teste automatizado novo foi criado.
