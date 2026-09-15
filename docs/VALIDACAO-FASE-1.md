# Validação da Fase 1

Ambiente: PHP 8.2.12, CakePHP 5.4.1, MariaDB 10.4.32 (instância local isolada, porta 3307), Tailwind compilado e Chromium via Playwright.

## Verificações realizadas

- Migrations aplicadas no banco local e no banco exclusivo de testes.
- Testes PHPUnit de autenticação, sessão, autorização, proteção CSRF, validação de campos, duplicidade, persistência de programação, auditoria, rollback, gestão de usuários e comando de administrador.
- Verificação de estilo pelo PHP CodeSniffer e compilação do Tailwind.
- Dois testes de navegador: cadastro/edição/filtro/logout no computador e consulta/drawer/foco/permissões no celular.
- Inspeção visual das capturas desktop e mobile; sem transbordamento horizontal nas páginas verificadas.
- Dependências PHP compatíveis com o ambiente; CakePHP fixado em 5.4.1 por requisito.

As credenciais de navegador pertencem ao banco de testes. O banco operacional contém apenas o administrador local gerado, sem checklists de demonstração.

## Roteiro de revisão pelo usuário

1. Acessar o endereço e as credenciais indicados no README.
2. Cadastrar um checklist com código, nome, área, rota opcional, intervalo de 15 dias e início em 10/09/2026.
3. Conferir os dados no detalhe e editar uma observação.
4. Buscar pelo código ou pela área na listagem.
5. Criar um usuário com perfil USUARIO; confirmar que pode consultar e não pode cadastrar, editar ou gerenciar usuários.
6. Abrir no celular ou reduzir a largura da janela e verificar o menu lateral.

## Limites desta entrega

Os testes desta fase não validam o motor de recorrência, o CIENTE ou as regras de pausa/reativação, pois esses fluxos ainda não foram implementados. A estrutura está preparada, mas a aplicação ainda não deve ser usada como fonte de alertas operacionais. Antes da Fase 2, validar o cadastro e a interface e definir a política de edição de data-base/periodicidade quando já houver ocorrências.
