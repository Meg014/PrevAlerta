# Fase 3 — CIENTE e histórico

## Operação

O dashboard mantém os cards por checklist e apresenta também cada ocorrência pendente, com seu próprio botão CIENTE. A lista é paginada, sem omitir ciclos antigos. A confirmação mostra nome, código, data prevista e dias de atraso. Cancelar não grava nada. Sem JavaScript, o link abre uma página de confirmação equivalente.

Após confirmar, o servidor grava o reconhecimento e redireciona ao dashboard atualizado. No histórico do checklist, o mesmo fluxo retorna ao próprio histórico. O CIENTE não abre O.S., não recebe número de O.S. e não altera a programação. As outras ocorrências permanecem pendentes, inclusive quando uma ocorrência mais recente é reconhecida antes de uma mais antiga.

## Integridade

`AcknowledgementService` obtém o usuário autenticado e o instante do servidor. Dentro da transação, bloqueia primeiro o checklist e depois a ocorrência, na mesma ordem utilizada pela sincronização. Só aceita uma ocorrência PENDENTE de programação ativa, prevista para hoje ou antes no fuso de São Paulo.

Se a ocorrência já estiver RECONHECIDA, retorna sem modificar usuário, horário ou auditoria. O índice único `checklist_history.checklist_occurrence_id` permite apenas um registro de auditoria de CIENTE por ocorrência, além da unicidade de programação/data já existente na Fase 2. Reconhecimento e auditoria são atômicos: falha em qualquer gravação desfaz ambos. A interface também bloqueia reenvio durante a submissão, mas a integridade não depende do JavaScript.

As rotas de gravação só aceitam POST, com autenticação, CSRF e proteção de formulário. Data prevista, usuário e horário enviados pelo cliente não são usados para registrar CIENTE. Não existem rotas normais de edição/exclusão de ocorrências reconhecidas ou histórico para nenhum dos perfis. A chave estrangeira da auditoria também restringe a exclusão da ocorrência vinculada.

## Histórico

- `checklist_occurrences`: data prevista, status RECONHECIDA, acknowledged_at e acknowledged_by, preservando created e a programação original.
- `checklist_history`: ação CIENTE, checklist, usuário, ocorrência vinculada e data/hora; valores anteriores/novos e descrição preservam o código/nome do checklist e o nome do usuário no momento do registro.
- Registros de criação/edição das fases anteriores permanecem disponíveis, com vínculo de ocorrência nulo.

A tela por checklist apresenta cada ciclo, data prevista, CIENTE, usuário, situação, dias de atraso e instante em que a ocorrência foi registrada. O atraso reconhecido é calculado pela data local do CIENTE em America/Sao_Paulo e não cresce com o tempo: mesmo dia = NO PRAZO, data posterior = ATRASADO. Pendências aparecem como PENDENTE com atraso até a data da consulta. A auditoria geral apresenta ação, checklist, responsável, data prevista e instante do CIENTE, sem ações de exclusão.

## Arquivos principais

- `src/Service/AcknowledgementService.php` — reconhecimento transacional.
- `src/Service/OccurrencePresentation.php` — classificação de atraso no histórico.
- `src/Controller/OccurrencesController.php` — confirmação e POST autenticado.
- `src/Controller/HistoryController.php` — histórico geral e por checklist.
- `config/Migrations/20260911000300_LinkAcknowledgementAudit.php` — vínculo único de auditoria.
- `templates/element/pending_occurrences.php`, `ack_button.php`, `ack_confirmation.php` e `webroot/js/acknowledgements.js` — lista individual e confirmação acessível.
- `templates/History/` — histórico somente para consulta.

## Testes

`AcknowledgementTest` cobre CIENTE no mesmo dia, um dia e vários dias de atraso, viradas de mês/ano e meia-noite em São Paulo, identidade e horário corretos, preservação do calendário, duplicidade, ciclos acumulados independentes, reversão em falha de auditoria, proibição de confirmação futura/pausada, autenticação/CSRF/POST, ausência de exclusão pela interface e dashboard/histórico após o registro.

O teste de concorrência executa dois processos PHP independentes contra a mesma ocorrência no MariaDB de testes e verifica um único reconhecimento e uma única auditoria. `tests/acknowledgement_worker.php` usa exclusivamente a conexão de testes.

`tests/browser/phase-three.spec.js` cadastra um cenário de teste, entra como USUARIO, abre e cancela a confirmação, confirma uma das três pendências, verifica sua remoção, confere as demais e consulta o histórico. Também verifica o modal no celular e a página de confirmação alternativa.

## Resultado da validação

Validação em 11/09/2026:

- `composer check`: 56 testes aprovados, 298 asserções; checagem de estilo aprovada em 45 arquivos PHP.
- `npm run build`: CSS compilado com sucesso.
- `npx playwright test`: 4 testes de navegador aprovados, incluindo regressão das Fases 1 e 2 e o fluxo CIENTE da Fase 3.
- Capturas de confirmação no desktop/celular e histórico inspecionadas; acesso, login e logout do sistema local confirmados.
- Reconhecimentos de teste executados exclusivamente no banco de testes.

Fase 3 concluída para validação. Fase 4 não iniciada: pausa, reativação e nova programação continuam fora desta entrega.
