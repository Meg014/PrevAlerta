# Fase 2 — recorrência e dashboard operacional

## Regra adotada

`data prevista = data inicial + n × periodicidade`, com `n >= 0`. Dias são civis; “hoje” é obtido em **America/Sao_Paulo**. Timestamps de persistência continuam em UTC, como na Fase 1.

Exemplo: início 31/12/2026, intervalo de 15 dias: 31/12/2026, 15/01/2027, 30/01/2027, 14/02/2027. Não há ajuste para último dia do mês, dia útil ou data de reconhecimento.

O serviço grava apenas ciclos até hoje inclusive. Cada transação insere até 500 registros e avança `materialized_cycle`; não há registro futuro gerado antecipadamente. O bloqueio do checklist serializa sincronizações concorrentes, e a chave única de programação/data impede duplicação. Registros existentes, inclusive reconhecidos, não são sobrescritos. Ao retomar após dias sem acesso, todos os ciclos vencidos são recuperados, sem pular os intermediários.

As ocorrências mantêm `scheduled_date`, `status`, `acknowledged_at` e `acknowledged_by`. `PENDENTE` representa um ciclo em aberto; os testes simulam `RECONHECIDA` para comprovar a continuidade da sequência. Não foi criada nenhuma ação ou botão de reconhecimento.

## Dashboard

Cada card conta checklists distintos. A prioridade é: atrasado, hoje, próximos de 1 a 7 dias, em dia (mais de 7), pausado. Um checklist com ciclos vencidos e outro ciclo hoje pertence a **Atrasados**, e seu total de ciclos pendentes inclui ambos. A próxima ocorrência pendente é a mais antiga não reconhecida; a passagem do tempo não a substitui por uma data futura.

Pausados não geram ocorrências e só aparecem ao selecionar o card/filtro Pausados. Os cinco cards são globais; a busca filtra a tabela, sem esconder a quantidade total que exige atenção. A tabela possui paginação de 25 checklists, busca por código/nome/área/rota e filtros por situação. Mantido o layout e o menu responsivo.

## Ajuste necessário à edição

Data-base e periodicidade de programações já iniciadas não podem ser sobrescritas, mesmo antes da primeira visita ao dashboard. Isso preserva ciclos vencidos ainda não materializados. A proteção existe no servidor e os campos aparecem como somente leitura no formulário. As demais informações continuam editáveis. A edição de uma programação ainda futura permanece permitida. Alterações simultâneas de programação são detectadas dentro da transação.

## Arquivos principais

- `src/Service/RecurrenceCalendar.php`: aritmética de datas e classificação.
- `src/Service/RecurrenceService.php`: sincronização e resumo operacional.
- `config/Migrations/20260911000200_AddRecurrenceProgress.php`: marcador de processamento e índice de pendências.
- `src/Controller/DashboardController.php` e `templates/Dashboard/index.php`: cards, alerta e tabela filtrada.
- `src/Service/ChecklistService.php` e `templates/Checklists/form.php`: proteção dos ciclos na edição.
- `src/Command/SyncOccurrencesCommand.php`: sincronização opcional por agendamento.

## Testes

`RecurrenceCalendarTest` cobre intervalos de 5/15/30 dias, data-base inclusiva, hoje, amanhã, início futuro, viradas de mês/ano, janeiro de 2027, ano bissexto, limites de classificação e meia-noite de São Paulo.

`RecurrenceServiceTest` cobre persistência dos vencidos, idempotência, pendência antiga, contagem por checklist, limite de sete dias, exclusão de pausados, reconhecimento simulado sem deslocar a sequência, processamento com mais de um lote, preservação de registros existentes, proteção de programação iniciada e dashboard com relógio local.

`tests/browser/phase-two.spec.js` verifica o alerta, os cinco cards, pendências acumuladas, filtros, exclusão de pausados e responsividade. Os testes da Fase 1 continuam sendo executados; a edição da data-base é testada com programação futura, respeitando a nova proteção.

Resultado local: **40 testes PHPUnit, 161 asserções e 3 testes de navegador aprovados**. PHP CodeSniffer sem erros ou avisos e compilação Tailwind concluída. Acesso, dashboard e logout também conferidos no ambiente operacional local. Capturas em `tmp/screenshots/fase2-dashboard-desktop.png` e `tmp/screenshots/fase2-dashboard-mobile.png`.

Não foram implementados CIENTE, pausa, reativação ou timeline de ações. A Fase 3 aguarda validação da Fase 2.
