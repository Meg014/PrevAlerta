# PrevAgenda

Sistema de lembretes de checklists de manutenção preventiva. **Sistema principal aprovado; Fases 1 a 4 implementadas.**

CakePHP **5.4.1**, PHP 8.2, MariaDB e Tailwind CSS compilado localmente. Sem integrações externas ou abertura de O.S.

## Acesso neste computador

Instruções para iniciar automaticamente com o Windows, verificar a execução e cuidar dos dados: [Operação no PC](docs/OPERACAO-PC.md).

Aplicação: **http://127.0.0.1:8765/** — acesso direto ao dashboard, sem login manual.

Para ativar o início automático e os alertas nativos do Windows, execute uma única vez **`scripts/ativar-inicio-automatico.bat`** na conta que usará o PC. A conta interna **PCM (ADMIN)** identifica as ações na auditoria; os autores antigos permanecem preservados. `/login` redireciona ao dashboard. Detalhes, teste de notificação e desativação estão no guia de operação acima.

Em um novo computador, use o MariaDB instalado como serviço automático do Windows e configure a conexão em `config/app_local.php`. A instância isolada antiga em `tmp/mariadb`, porta 3307, continua compatível, mas é local e não acompanha o clone.

Para reiniciar os serviços locais no Windows:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-prevagenda.ps1
```

Os scripts descobrem a raiz pela própria localização e procuram `php.exe` no PATH. A configuração do banco permanece local. Logs do servidor ficam em `logs/`; os da automação, em `%LOCALAPPDATA%\PrevAgenda`. Não são criados nem apagados bancos pelos scripts.

## Entregue na Fase 1

- Login/logout com autenticação por sessão, hash de senha e renovação da sessão.
- ADMIN gerencia checklists e usuários; USUARIO consulta os cadastros. Permissões são verificadas a cada requisição, incluindo mudanças de perfil e desativação.
- Cadastro, listagem com busca/filtro/paginação, visualização e edição de checklists.
- Código único, campos obrigatórios, periodicidade inteira positiva e data inicial válida.
- Migrations e entidades das cinco tabelas, com índices e chaves estrangeiras.
- Cadastro/programação/histórico gravados na mesma transação.
- Layout Tailwind, sidebar recolhível, drawer móvel com foco e fechamento por Escape.
- Comando para criar o primeiro administrador sem senha padrão.

## Entregue na Fase 2

- Recorrência por data-base + número do ciclo × periodicidade, com hoje em `America/Sao_Paulo`.
- Dashboard operacional: atrasados, vencem hoje, próximos 7 dias, em dia e pausados; destaque de atenção, busca, filtros e paginação.
- Cards contam checklists distintos. A situação usa a pendência mais antiga; a tabela informa quantos ciclos estão pendentes.
- Somente ocorrências previstas até hoje são persistidas, em lotes transacionais de 500. Datas futuras são calculadas sem gerar registros futuros.
- Marcador `materialized_cycle` evita reprocessamento. Chave única por programação/data e bloqueio de linha protegem contra duplicações.
- Programações já iniciadas têm data-base e periodicidade protegidas na edição. Os outros campos continuam editáveis. Programações futuras podem ser ajustadas.

Novos checklists continuam ATIVOS; o status não pode ser alterado pela edição comum. Pausa e reativação usam as ações específicas da Fase 4. A auditoria de cadastro da Fase 1 foi preservada.

Após atualizar uma instalação da Fase 1:

```bash
php bin/cake.php migrations migrate
php bin/cake.php schema_cache clear
npm run build
```

O dashboard sincroniza as ocorrências ao abrir. Opcionalmente, `php bin/cake.php sync_occurrences` pode ser agendado diariamente, sem depender de acessos. Uma primeira sincronização com muitos anos de ciclos diários pode demorar; os lotes concluídos ficam salvos e podem ser retomados.

Detalhes e validação: [docs/FASE-2.md](docs/FASE-2.md).

## Entregue na Fase 3

- CIENTE individual por ocorrência atrasada ou de hoje, com confirmação e atualização do dashboard após o registro.
- Todas as pendências acumuladas podem ser consultadas em páginas de 25 ocorrências. Confirmar uma não confirma as outras.
- Histórico por checklist em `/checklists/{id}/historico`, com data prevista, CIENTE, responsável, atraso em dias e data/hora de registro.
- Histórico geral em `/historico`, com auditoria de criação, edição e CIENTE, filtros e paginação.
- ADMIN e USUARIO podem reconhecer ocorrências e consultar histórico. Não há ações de exclusão de ocorrências ou auditoria.
- Transação, bloqueios de linha e índice único de auditoria impedem duplicidade, inclusive em pedidos simultâneos. Falha na auditoria reverte o CIENTE.

O calendário da Fase 2 foi preservado: o CIENTE nunca modifica a data-base nem a periodicidade. Horários são armazenados em UTC e apresentados em `America/Sao_Paulo`; somente a diferença de datas locais conta como atraso. Atualize esta versão com as migrations, limpeza de cache de schema e build de CSS indicados acima.

Detalhes e testes: [docs/FASE-3.md](docs/FASE-3.md).

## Entregue na Fase 4

- ADMIN pode pausar um checklist com motivo obrigatório e reativá-lo com nova data inicial obrigatória.
- Pausa preserva todos os ciclos anteriores e encerra a programação. O checklist participa somente do card PAUSADOS, cuja lista mostra motivo, data e responsável.
- Reativação cria uma programação independente, com a mesma periodicidade e progresso inicial `materialized_cycle = -1`; pendências antigas não voltam aos alertas.
- Auditoria de PAUSA e REATIVACAO, com responsável, horário e motivo/nova data, disponível no histórico geral e por checklist.
- Transações, bloqueio do checklist e conferência da programação impedem ações duplicadas ou formulários antigos. USUARIO continua sem permissão para essas ações.
- Dados gerais continuam editáveis durante a pausa; a programação encerrada permanece protegida.

Detalhes e testes: [docs/FASE-4.md](docs/FASE-4.md). Aplique migrations e limpe o cache de schema ao atualizar uma instalação anterior.

Arquitetura, regras, modelo e próximas fases: [docs/PLANO.md](docs/PLANO.md).

## Instalação em um novo computador

Execute os comandos abaixo na pasta em que deseja manter o projeto. A pasta pode estar em qualquer unidade e pode conter espaços. Não é necessário editar caminhos nos scripts.

1. **Instale PHP 8.2 ou superior, Composer, Git e MariaDB.** Habilite no PHP as extensões `intl`, `mbstring`, `pdo_mysql`, `dom`, `simplexml`, `xml` e `xmlwriter`. Adicione a pasta de `php.exe` ao **PATH do Windows** e confirme `php --version` e `composer --version` em um novo terminal. Configure o MariaDB como **serviço do Windows**, com início **Automático**, e confirme que está em execução. Após mudar o PATH, saia e entre no Windows para que o Agendador receba o ambiente atualizado.
2. **Clone o repositório** (substitua o endereço pelo endereço real):
   ```powershell
   git clone <repositorio> prev-agenda
   cd prev-agenda
   ```
3. **Instale as dependências:**
   ```powershell
   composer install
   composer check-platform-reqs
   ```
   O instalador cria `config/app_local.php`, os diretórios de trabalho e uma chave `Security.salt` exclusiva quando ainda não existem. Preserve essa chave. O CSS compilado em `webroot/css/app.css` deve estar no Git; Node.js não é necessário para apenas executar o sistema.
4. **Configure o banco local.** No MariaDB, crie um banco vazio `prev_agenda` com codificação `utf8mb4` e um usuário próprio com senha e permissões nesse banco (incluindo criação/alteração de tabelas para migrations). Em **`config/app_local.php`**, configure `Datasources.default`: host `127.0.0.1`, porta do serviço (normalmente `3306`), banco, usuário e senha. Mantenha `debug=false` e `App.fullBaseUrl=http://127.0.0.1:8765`. Esse arquivo é local e ignorado pelo Git; nunca copie credenciais para os scripts ou para o arquivo de exemplo. A conexão `test` só é necessária para executar testes e deve apontar para outro banco.
5. **Aplique as migrations no banco vazio:**
   ```powershell
   php bin/cake.php migrations migrate
   php bin/cake.php schema_cache clear
   ```
6. **Teste manualmente:**
   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
   ```
   Abra **http://127.0.0.1:8765/**. Deve aparecer o dashboard sem login, com a identidade interna PCM. O script inicia o servidor em segundo plano e aguarda a resposta; reexecutá-lo reutiliza o servidor existente.
7. **Ative a automação uma única vez:** dê duplo clique em **`scripts\ativar-inicio-automatico.bat`** na conta do operador. A tarefa e o atalho são registrados com a pasta real do clone. Não é necessário abrir PowerShell no uso diário nem informar o caminho do PHP.
8. **Reinicie e valide:** entre na mesma conta, aguarde 30 segundos mais a inicialização e confira o dashboard. Havendo atrasados ou vencimentos de hoje, confira a notificação e clique nela. Sem pendências, não deve haver aviso. Para testar a notificação sem criar dados reais, execute `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-notification.ps1`.

O Git transporta o código, não o banco nem a configuração local. Para transportar dados existentes, exporte e restaure o banco separadamente; não copie `tmp\mariadb` ou senhas para o repositório. Não execute migrations sobre uma instalação existente sem seu backup.

Se mover ou renomear a pasta depois da ativação, execute novamente `ativar-inicio-automatico.bat` no novo local para atualizar a tarefa e o atalho. Se o servidor antigo ainda estiver ativo, reinicie o PC depois dessa atualização para carregar o código do novo local.

## Verificação

```bash
composer test
composer cs-check
composer check-platform-reqs
npm run build
```

Os testes PHPUnit aplicam migrations na conexão `test` e isolam os dados com fixtures. Cobrem login/logout, permissões, CSRF, validação, duplicidade, auditoria atômica, edição e gestão de usuários. O `composer validate --strict` alerta sobre a versão exata do CakePHP; a fixação em 5.4.1 é intencional, conforme requisito.

Testes de navegador (Chromium):

```powershell
# Depois do PHPUnit, prepare exclusivamente os usuários do banco de testes.
php tests/seed_browser.php
npx playwright install chromium
# Em outro terminal, use a mesma conexão test configurada em app_local.php:
$env:DATABASE_URL='mysql://root@127.0.0.1:3307/test_prev_agenda'
$env:PREV_LOCAL_ACCESS='false' # suíte de navegador histórica de login e perfis
php bin/cake.php server -H 127.0.0.1 -p 8766
# Com esse servidor rodando, no terminal original:
npx playwright test
```

O endereço de teste pode ser configurado por `PREV_TEST_URL`. O teste de navegador cria dados somente no servidor de testes indicado. Capturas ficam em `tmp/screenshots/`.

## Datas e continuidade

Datas-base usam tipo `DATE`. Instantes são persistidos em UTC e apresentados em `America/Sao_Paulo`. O motor mantém `data-base + n × periodicidade`, sem deslocar vencimentos pela data do CIENTE. Cada reativação cria uma nova programação independente, preservando a anterior.

O sistema principal foi aprovado. A revisão final mantém o escopo existente, sem novas funcionalidades.
