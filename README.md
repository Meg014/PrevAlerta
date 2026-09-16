# PrevAgenda

Sistema de lembretes de checklists de manutenção preventiva. **Sistema principal aprovado; Fases 1 a 4 implementadas.**

CakePHP **5.4.1**, PHP 8.2, PostgreSQL e Tailwind CSS compilado localmente. Sem integrações externas ou abertura de O.S.

## Implantação atual

O banco oficial é PostgreSQL. Siga [Deploy do servidor](docs/DEPLOY-SERVIDOR.md) para criar o banco vazio, configurar pdo_pgsql, aplicar as migrations e publicar o CakePHP. O exemplo está em `config/app_local.example.php`; credenciais ficam somente no ambiente/arquivo local.

O app Windows acessa o servidor central e não precisa ser alterado por causa do banco. As instruções antigas de MariaDB em documentos das fases anteriores são históricas.

Para validar localmente, PostgreSQL deve estar em execução e a conexão deve estar configurada. `scripts/start-local.ps1` inicia apenas PHP; não inicia banco. Na empresa, use o servidor HTTP como serviço, conforme o guia de deploy.

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

## Instalação em um novo servidor

Siga [docs/DEPLOY-SERVIDOR.md](docs/DEPLOY-SERVIDOR.md). Use PostgreSQL, PHP com pdo_pgsql/pgsql e Composer. As quatro migrations existentes criam o schema completo em banco vazio; não importe SQL de MariaDB nem dados de teste. Não envie configuração local, credenciais, bancos ou backups ao Git.

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
$env:DATABASE_URL=$env:DATABASE_TEST_URL # URL PostgreSQL de um banco exclusivo de testes
$env:PREV_LOCAL_ACCESS='false' # suíte de navegador histórica de login e perfis
php bin/cake.php server -H 127.0.0.1 -p 8766
# Com esse servidor rodando, no terminal original:
npx playwright test
```

O endereço de teste pode ser configurado por `PREV_TEST_URL`. O teste de navegador cria dados somente no servidor de testes indicado. Capturas ficam em `tmp/screenshots/`.

## Datas e continuidade

Datas-base usam tipo `DATE`. Instantes são persistidos em UTC e apresentados em `America/Sao_Paulo`. O motor mantém `data-base + n × periodicidade`, sem deslocar vencimentos pela data do CIENTE. Cada reativação cria uma nova programação independente, preservando a anterior.

O sistema principal foi aprovado. A revisão final mantém o escopo existente, sem novas funcionalidades.
