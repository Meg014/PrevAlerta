# Fase 4 — pausa e reativação

## Operação

ADMIN acessa o checklist e seleciona PAUSAR. A página de confirmação exige um motivo livre, de até 10000 caracteres. Cancelar não grava nada. Após a confirmação, o checklist fica PAUSADO e sai de todos os alertas operacionais. O card PAUSADOS abre a lista com código, nome, área, periodicidade, data/hora da pausa, motivo e responsável. ADMIN encontra REATIVAR nessa lista e no detalhe do checklist; USUARIO mantém consulta, CIENTE das programações ativas e histórico.

A reativação exige uma nova data inicial válida, sem preencher automaticamente a data antiga. A periodicidade é mantida. São aceitas datas passadas, atuais ou futuras: uma data passada cria somente os ciclos devidos da nova programação quando o motor for executado; uma data futura não gera registros antecipados.

## Programações independentes

A pausa obtém bloqueio do checklist e sincroniza os ciclos previstos até a data local da pausa, inclusive quando o dashboard não foi acessado anteriormente. Em seguida, preenche `ended_at` da programação antiga e grava o status PAUSADO, `paused_at`, `paused_by` e `pause_reason`.

A reativação insere outra linha em `checklist_schedules`, com novo ID, nova data inicial, mesma periodicidade e `materialized_cycle = -1`. Esse valor significa que nenhum ciclo da nova programação foi processado. O cursor anterior não é apagado, copiado ou reutilizado. O motor validado na Fase 2 permanece inalterado e utiliza somente a programação ativa.

Exemplo: início em 20/09/2026, intervalo de 15 dias → 20/09, 05/10, 20/10, 04/11. A programação anterior não interfere nessa sequência, mesmo que suas pendências continuem armazenadas. Um ciclo antigo não pode receber CIENTE depois do encerramento da sua programação.

ADMIN pode continuar editando os dados gerais durante a pausa; data-base e periodicidade da programação encerrada ficam protegidas. Formulários de edição abertos antes de uma pausa/reativação são recusados quando sua programação ou status já mudou.

## Histórico e integridade

Nenhuma ocorrência, reconhecimento ou auditoria anterior é excluída ou reescrita. O histórico por checklist identifica programações encerradas como fora dos alertas e mostra pausas/reativações. A auditoria geral permite filtrar PAUSA e REATIVACAO.

Cada ação registra checklist, usuário, instante UTC, descrição com nome do responsável e dados anteriores/novos em JSON. PAUSA inclui motivo; REATIVACAO inclui nova data inicial e ID da nova programação. Datas e horários são exibidos em America/Sao_Paulo; o corte de geração na pausa usa a data nesse fuso.

Todas as gravações da ação, inclusive sincronização na pausa, estão na mesma transação. Falha da auditoria desfaz status, encerramento/criação de programação e ocorrências recém-materializadas. O bloqueio do checklist usa a mesma ordem da recorrência e do CIENTE. O estado esperado e o ID da programação enviados pelo formulário são conferidos novamente sob bloqueio, impedindo duplicidade e reaproveitamento de formulários antigos após outro ciclo de pausa/reativação. O servidor exige ADMIN, autenticação, POST, CSRF e proteção do formulário para gravar.

## Arquivos principais

- `src/Service/ChecklistLifecycleService.php`: transições atômicas e auditoria.
- `src/Controller/LifecycleController.php`, `config/routes.php`, `templates/Lifecycle/confirm.php`: confirmação e permissões.
- `config/Migrations/20260911000400_AddPauseDetails.php`: metadados da pausa e chave estrangeira do responsável.
- `src/Controller/DashboardController.php`, `templates/element/paused_checklists.php`: lista operacional de pausados.
- `src/Service/ChecklistService.php`, `src/Controller/ChecklistsController.php`, `templates/Checklists/`: edição segura e ações.
- `src/Controller/HistoryController.php`, `templates/History/`: consulta das ações e programações encerradas.
- `tests/TestCase/ChecklistLifecycleTest.php`, `tests/lifecycle_worker.php`, `tests/browser/phase-four.spec.js`: testes.

## Validação

A suíte específica cobre pausa, ausência de geração e alertas durante a pausa, card PAUSADOS, motivo e responsável, reativação com data obrigatória, cursor independente, preservação do histórico, auditoria, duplicidades, formulários antigos, permissões, viradas de mês/ano e corte de meia-noite em São Paulo. Dois processos PHP independentes verificam concorrência de pausa e de reativação contra o MariaDB de testes. Falha de auditoria é injetada nas duas ações para verificar reversão completa.

O teste de navegador cobre ADMIN cadastrando um checklist, cancelando a confirmação, pausando com motivo, consultando a lista, reativando com nova data, verificando a próxima ocorrência e o histórico antigo, além da ausência de ações administrativas para USUARIO. Testes executam em `test_prev_agenda`, separado dos dados operacionais.

Resultado em 11/09/2026:

- PHPUnit: **76 testes aprovados, 449 asserções**, incluindo os 20 testes/casos da nova suíte de pausa e reativação.
- Checagem de estilo: **49 arquivos aprovados** após correções de formatação.
- Compilação Tailwind: concluída com sucesso.
- Playwright: **5 testes de navegador aprovados**, incluindo regressão das Fases 1, 2 e 3.
- Acesso, login e logout do sistema operacional local confirmados, sem pausar, reativar ou reconhecer registros reais.

Fase 4 concluída para validação do usuário. Notificações, e-mail, WhatsApp, TOTVS e abertura de O.S. não fazem parte desta entrega.
