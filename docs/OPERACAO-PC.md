# Operação no PC Windows

O PrevAlerta pode ser clonado em qualquer pasta do computador. Todos os caminhos do projeto são derivados da localização dos scripts; `php.exe` é localizado pelo PATH do Windows. O acesso normal é **http://127.0.0.1:8765/**, direto no dashboard, sem usuário e senha.

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


## Ativar uma única vez

Na conta do Windows que utilizará o sistema, abra a subpasta `scripts` do clone e dê **duplo clique em `ativar-inicio-automatico.bat`**. Aguarde a mensagem de configuração concluída. Não precisa executar como administrador.

O instalador:

- registra ou atualiza a tarefa **PrevAgenda - inicio local**, sem criar outra tarefa com o mesmo nome;
- configura logon da conta atual, atraso de 30 segundos e execução interativa sem privilégios elevados;
- instala a identificação **PrevAlerta** no menu Iniciar para as notificações nativas;
- permite iniciar usando bateria e tenta novamente até três vezes se a inicialização falhar;
- solicita uma primeira execução imediatamente.

Executar o arquivo novamente é seguro e atualiza a mesma tarefa com os caminhos atuais. Se outra conta for usar o PC, configure nela; o instalador não substitui silenciosamente uma tarefa pertencente a outra conta.

Não crie também um atalho na pasta Inicializar: a automação é feita pelo Agendador de Tarefas.

## O que acontece no logon

1. `start-prevagenda.ps1` chama `start-local.ps1`, que localiza o PHP no PATH e reutiliza ou inicia o servidor PHP/CakePHP na porta **8765**. Em instalações novas, o serviço MariaDB já deve estar ativo. Somente instalações antigas com `tmp\mariadb\my.ini` usam a inicialização da instância isolada na porta 3307; nesse caso, `mariadbd.exe` ou `mysqld.exe` também precisa estar no PATH.
2. Aguarda uma resposta HTTP 200 contendo o dashboard. Um bloqueio entre processos evita duas inicializações simultâneas. Uma porta ocupada sem o dashboard esperado é tratada como falha.
3. Executa o comando CakePHP `local_alert_summary`, usando as configurações de banco existentes.
4. Se houver checklists **ATRASADOS** ou que **VENCEM HOJE**, envia uma única notificação nativa **PrevAlerta** com as contagens. Sem pendências, não envia nada.
5. Abre o navegador padrão em **http://127.0.0.1:8765/**. Se o aviso falhar, ainda abre o dashboard quando o servidor estiver disponível.

O clique no corpo da notificação ou no botão **Abrir PrevAlerta** abre esse mesmo endereço, inclusive depois que o PowerShell terminou. O aviso fica sujeito às preferências de notificações e ao modo Não incomodar do Windows.

Reexecutar a tarefa na mesma sessão verifica o servidor, mas não abre outras abas nem repete a notificação. O controle fica em `%LOCALAPPDATA%\PrevAgenda\logon.json`, separado por conta e identificado pela sessão de logon. Atualizar o dashboard não dispara notificações do Windows. Sair e entrar novamente no Windows inicia uma nova sessão; bloquear/desbloquear ou suspender/retomar a mesma sessão não envia outro resumo.

O resumo usa `RecurrenceService::dashboard()` dentro de uma transação descartada ao terminar. Assim, reutiliza as regras existentes, inclusive ciclos ainda não materializados, sem salvar ocorrências, CIENTE, cursores ou auditoria pela consulta do aviso. Conta **checklists distintos**, exatamente como os cards: se um checklist tem atraso antigo e também um ciclo de hoje, participa somente de ATRASADOS. Abrir o próprio dashboard continua fazendo a sincronização normal já existente.

O alerta existente dentro do dashboard foi preservado. Não há consulta periódica em segundo plano nem novas regras de negócio.

## Acesso sem login e auditoria

- `/` abre diretamente o dashboard e `/login` redireciona para `/`.
- O sistema cria uma única conta interna **PCM**, perfil **ADMIN**, na tabela `users`, no primeiro acesso local. Sua senha é aleatória e não é exibida nem gravada em scripts.
- As ações do uso local usam essa identidade: cadastro, edição, CIENTE, pausa e reativação continuam gravando o responsável e a data/hora pelos serviços existentes.
- A conta PCM fica protegida contra edição pela interface. As tabelas, perfis e autores do histórico anterior permanecem preservados.
- Não aparece botão Sair no modo local. Os cadastros e ações administrativas ficam acessíveis ao operador do PC.

O modo está habilitado por `LocalAccess.enabled` em `config/app.php`. O acesso automático aceita somente conexões locais reais (`127.0.0.1` ou `::1`); não confia em cabeçalhos de proxy. As proteções CSRF e de formulário permanecem ativas. Depois de configurar o banco e aplicar as migrations da instalação nova, não é necessário criar outro administrador para este fluxo.

## Conferir a tarefa no Agendador

Abra **Agendador de Tarefas → Biblioteca do Agendador de Tarefas → PrevAgenda - inicio local**. Confira:

- Geral: sua conta, **Executar somente quando o usuário estiver conectado**.
- Disparadores: **Ao fazer logon**, para a conta configurada, atraso de **30 segundos**.
- Ações: `powershell.exe`, com o caminho completo de `scripts\start-prevagenda.ps1` calculado na ativação, sem janela visível; diretório de trabalho igual à raiz do clone. O PHP é resolvido pelo PATH em cada execução.
- Configurações: **Não iniciar uma nova instância**; tentativas a cada minuto, até três vezes.

Use o arquivo de ativação para registrar a tarefa, pois ele também prepara a identificação necessária às notificações. Alternativamente, na raiz do clone:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\register-startup.ps1
```

## Testar depois de reiniciar o PC

1. Reinicie o PC e entre na conta configurada.
2. Aguarde o atraso de 30 segundos e a inicialização dos serviços. O navegador deve abrir **direto no dashboard**, sem solicitar credenciais.
3. Havendo checklists atrasados ou de hoje, confira a notificação **PrevAlerta** e compare as contagens com os cards do dashboard. Sem pendências, nenhuma notificação operacional deve aparecer.
4. Clique na notificação: deve abrir o dashboard. No Windows 11, `Win+N` abre a Central de Notificações; no Windows 10, use `Win+A`.
5. Atualize a página algumas vezes e execute a tarefa novamente pelo Agendador: não deve surgir outro aviso nem outra abertura automática do navegador na mesma sessão.
6. No Agendador, confira **Último Resultado da Execução: 0x0**. O estado normalmente volta a Pronto; o PHP e o MariaDB continuam em segundo plano.

Para verificar o servidor, abra `http://127.0.0.1:8765/`. Para diagnóstico no PowerShell:

```powershell
Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8765 -State Listen
Get-ScheduledTaskInfo -TaskName 'PrevAgenda - inicio local'
Get-Content "$env:LOCALAPPDATA\PrevAgenda\startup.log" -Tail 20
```

## Testar somente a notificação

Depois da ativação, execute este comando de diagnóstico:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-notification.ps1
```

Na versão atual do app Windows, esse script solicita uma consulta real ao servidor pelo PrevAlerta, com sua sessão e `config.json`. Os antigos números fictícios foram removidos. Sem pendências, não aparece aviso. Confira URL e totais em `%LOCALAPPDATA%\PrevAlerta.Desktop\app.log`; para o fluxo atual de consultas e notificações, consulte `APP-WINDOWS.md`.

Para consultar o resumo real sem enviar notificação e sem persistir alterações:

```powershell
# Execute na raiz do clone.
php bin/cake.php local_alert_summary
```

O resultado é JSON, como `{"overdue":1,"today":2}`. Se ambos forem zero, o aviso operacional é suprimido.

## Desativar ou remover

No Agendador, clique com o botão direito em **PrevAgenda - inicio local** e escolha **Desabilitar**. Para remover a automação, escolha **Excluir**. Nenhuma dessas ações apaga dados ou encerra os serviços já iniciados. Para reativar, habilite a tarefa ou execute novamente `ativar-inicio-automatico.bat`.

Opcionalmente, após excluir a tarefa, remova o atalho **PrevAlerta** da pasta do menu Iniciar (`shell:programs`). Não apague `tmp\mariadb`: ela contém o banco real. A tarefa não monitora continuamente os processos; caso parem, execute-a novamente. O PC precisa estar ligado e acordado.

## Backup e recuperação

Faça backup regular do banco `prev_agenda` usando a ferramenta de exportação do MariaDB e as credenciais locais; mantenha cópia fora deste disco. Inclua `config/app_local.php` em armazenamento protegido, pois contém configurações e segredos necessários à recuperação. Não use o banco `test_prev_agenda` como backup.

Exemplo no PowerShell para esta instalação (ajuste o usuário se tiver configurado outro):

```powershell
$backupDir = Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'PrevAgenda-backups'
$null = New-Item -ItemType Directory -Path $backupDir -Force
$backupFile = Join-Path $backupDir ('prev_agenda-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql')
& mariadb-dump.exe --host=127.0.0.1 --port=3306 --user=prev_agenda --password --single-transaction --default-character-set=utf8mb4 "--result-file=$backupFile" prev_agenda
if ($LASTEXITCODE -ne 0) { throw 'Backup falhou. Nao use o arquivo gerado para recuperacao.' }
Write-Output "Backup concluido: $backupFile"
```

A senha é solicitada pelo MariaDB. Ajuste a porta, o usuário e o nome do banco aos valores da configuração local. Disponibilize `mariadb-dump.exe` no PATH; instalações antigas podem fornecer `mysqldump.exe` em seu lugar. `--result-file` evita a conversão de codificação do redirecionamento `>` no Windows PowerShell. Copie o arquivo concluído para outro disco ou armazenamento de backup. Não execute migrations durante a exportação.

Não copie `tmp\mariadb` com o banco em execução como única forma de backup. Essa pasta contém os dados reais e não deve ser apagada como se fosse cache. Antes de atualizar a instalação, faça uma exportação e verifique a restauração em banco separado. Restauração sobre o banco operacional substitui dados e deve ser planejada.

## Se não abrir

- Se aparecer “PHP nao encontrado”, configure a pasta de `php.exe` no PATH do Windows e entre novamente na conta. Não é necessário editar scripts.
- Consulte `%LOCALAPPDATA%\PrevAgenda\startup.log`, `summary.err.log`, `logs\server.err.log`, `logs\error.log` e os arquivos de erro do MariaDB em `tmp\mariadb`.
- Confira se os caminhos da tarefa ainda existem e se sua conta pode gravar em `logs` e `tmp`.
- Confira a porta 8765 e a porta de banco configurada (normalmente 3306; 3307 na instância isolada antiga). Não encerre um processo sem identificá-lo.
- Se o dashboard não abrir, verifique se o MariaDB está disponível e se as credenciais de `config/app_local.php` correspondem à instalação. Preserve `debug=false` e `App.fullBaseUrl=http://127.0.0.1:8765`.
- Se a notificação não aparecer, consulte a Central de Notificações e as configurações do Windows em **Sistema → Notificações → PrevAlerta**. O modo Não incomodar pode ocultar o banner. Use o script de teste depois de ajustar suas preferências; não é preciso apagar o arquivo de controle do logon.
- Os logs distinguem `notificacao=empty` (sem pendências), `notificacao=sent` (entregue à API do Windows) e erros. Entrega à API não significa que o banner foi visto pelo usuário.

## Limite desta instalação

Este procedimento automatiza o ambiente local existente, restrito a `127.0.0.1`. O servidor embutido do PHP e o MariaDB local com root sem senha não constituem uma implantação de produção em rede. Para disponibilizar a outras máquinas, é necessário preparar um servidor web apropriado com raiz em `webroot`, HTTPS e usuário de banco com senha e permissões próprias. Não exponha as portas locais na rede como atalho.

Não use as portas/bancos de testes na tarefa automática. A configuração mantém o ambiente local existente e não altera senhas, permissões globais de execução do PowerShell, configurações de notificações nem regras do sistema.
