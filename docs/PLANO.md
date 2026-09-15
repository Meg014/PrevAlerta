# PrevAgenda — plano de implementação

Situação: Fases 1, 2 e 3 aprovadas pelo usuário; Fase 4 implementada para validação. A recorrência está descrita em [FASE-2.md](FASE-2.md); o CIENTE e o histórico estão em [FASE-3.md](FASE-3.md); a pausa e a reativação estão em [FASE-4.md](FASE-4.md). As descrições abaixo preservam o planejamento original por etapas.

Aplicação exclusivamente de lembretes. CIENTE não abre ordem de serviço.

## Arquitetura

CakePHP **5.4.1**, PHP 8.2, MariaDB e Tailwind CSS compilado. MVC com serviços transacionais para operações de domínio, autenticação por sessão e autorização no servidor. Datas de programação são datas civis em America/Sao_Paulo; timestamps são armazenados em UTC e apresentados no fuso local.

## Modelo

- `users`: nome, e-mail único, senha com hash, perfil ADMIN/USUARIO, ativo, timestamps.
- `checklists`: código único, nome, setor, rota, descrição, observação, status e timestamps.
- `checklist_schedules`: checklist, data-base, periodicidade inteira positiva, autor, início/fim de vigência. Cada reativação abre uma programação independente.
- `checklist_occurrences`: programação, data prevista, status, responsável pelo ciente e timestamps. Chave única (programação, data prevista) impede duplicidade.
- `checklist_history`: checklist, usuário, ação, descrição, valores anterior/novo e instante da ação.

Dados da programação aparecem junto ao checklist nos formulários. A separação física preserva calendários anteriores. Não haverá exclusão de checklists, programações ou usuários que prejudique o histórico.

## Recorrência (Fase 2)

Vencimento(n) = data-base + n × periodicidade, n inteiro >= 0. CIENTE nunca muda a data-base. A próxima pendência é a ocorrência não confirmada mais antiga. Se vários ciclos vencerem, todos permanecem pendentes e serão exibidos; confirmar um ciclo não confirma os demais. Cards contam checklists distintos, e a lista detalha suas ocorrências.

Materializar os ciclos já vencidos até hoje de forma idempotente, por lotes, preservando cada data prevista. Calcular a janela futura de sete dias sem gerar registros ilimitados. A materialização deve funcionar também após períodos sem acesso ao sistema; um comando agendado pode complementá-la. Classificação: atrasado (< hoje), hoje, próximos (amanhã até hoje + 7 inclusive), em dia (> hoje + 7), pausado. A classificação principal do checklist usa sua pendência mais antiga.

## Pausa e reativação (Fase 4)

Pausar exige confirmação e motivo; materializa pendências até o momento da pausa, encerra a programação e retira o checklist dos alertas. Pendências anteriores ficam preservadas para consulta e não reaparecem na reativação. Reativar exige nova data inicial, abre outra programação e registra autor, horário e data-base. Nunca reutilizar a sequência anterior. Tratar cliques simultâneos com transação/bloqueio e unicidade de ocorrência.

Alterações de periodicidade/data-base precisam preservar a programação e o histórico anteriores; a política para pendências na troca será validada antes da Fase 2. Na Fase 1, como ainda não há ocorrências, a edição atualiza a programação inicial e registra os valores anteriores. Status não pode ser alterado pelo formulário comum: novos cadastros são ATIVOS; pausa/reativação estarão disponíveis na Fase 4 com seus campos obrigatórios.

## Entregas e validação

1. Projeto, migrations, login/logout, ADMIN/USUARIO, layout responsivo, cadastro/listagem/detalhe/edição, comando para primeiro administrador e gestão básica de usuários. Testar validação, autenticação, permissões, transação e banco MariaDB.
2. Motor de recorrência, persistência idempotente de vencidos e dashboard de alertas. Testar datas, vários ciclos pendentes e limites dos sete dias.
3. CIENTE transacional, timeline e auditoria de ocorrências; testar duplicidade, concorrência e preservação da sequência.
4. Pausa e reativação com nova programação; testar ausência de alertas durante a pausa e isolamento entre programações.
5. Revisão visual, acessibilidade, responsividade e testes integrados.

Validar cada fase antes de avançar. Na Fase 1 a página inicial informa que alertas ainda não estão habilitados, sem simular indicadores operacionais. O armazenamento de auditoria de cadastro/edição já integra a transação para não perder ações anteriores à Fase 3; a timeline completa virá nessa fase.
