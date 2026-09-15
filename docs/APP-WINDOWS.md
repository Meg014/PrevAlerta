# PrevAlerta — app Windows com servidor central

## Estado atual

O ícone oficial está em `desktop/assets/PrevAlerta.ico`, derivado de `webroot/ICON.png`, com transparência e tamanhos de 16, 24, 32, 48, 64, 128 e 256 pixels. App e instalador usam esse mesmo arquivo; a publicação o copia como `PrevAlerta.ico` para a janela, atalhos e identificação Windows. O PNG em `desktop/assets/PrevAlerta.png` também acompanha a publicação para exibir a imagem oficial nas notificações.

O cliente WinForms/WebView2 está em `desktop/PrevAlerta.App`. Ele exibe diretamente as páginas do CakePHP, preservando o layout e as regras de negócio existentes. PHP, MariaDB e os dados ficam no servidor. O cliente não inicia serviços locais.

O executável publicado nesta máquina está em `desktop/artifacts/win-x64/PrevAlerta.exe`. Distribua a pasta inteira, incluindo DLLs, ícone e `config.json`; o executável sozinho não é suficiente. Esta publicação inclui o runtime .NET e exige Windows 10/11 x64 e Microsoft Edge WebView2 Evergreen Runtime instalado.

Para a entrega à TI, utilize agora **`dist/PrevAlerta-Setup.exe`**, que inclui a publicação e o instalador offline do WebView2. O Setup instala por usuário em `%LOCALAPPDATA%\Programs\PrevAlerta`, cria atalhos, registra a desinstalação e oferece início automático via `shell:startup`. A configuração inicial do pacote é `http://prevalerta/`; a URL definitiva pode ser editada em `config.json`, sem recompilar. Atualizações preservam esse arquivo. Consulte `dist/README-TI.txt` e [DEPLOY-SERVIDOR.md](DEPLOY-SERVIDOR.md).

## Executar

Abra `desktop/artifacts/win-x64/PrevAlerta.exe` ou, na raiz do projeto, execute:

```powershell
.\scripts\start-prevagenda.ps1
```

Para outra pasta, use `-AppPath 'C:\caminho\PrevAlerta.exe'`. Na primeira execução, o app registra o atalho do menu Iniciar e o protocolo `prevalerta://open` para o usuário atual. Abrir novamente restaura a instância existente, inclusive quando minimizada.

## URL: um único arquivo usado pelo app

Edite **`config.json` ao lado de `PrevAlerta.exe`**:

```json
{
  "serverUrl": "http://127.0.0.1:8765/"
}
```

Esse endereço é temporário, somente para validação local. Quando o servidor definitivo estiver disponível, substitua `serverUrl` pela URL dele e reinicie o aplicativo. Prefira HTTPS para o servidor central. Caminhos como `https://servidor/prevalerta/` são aceitos; não inclua credenciais, parâmetros ou fragmentos.

A navegação e a consulta de notificações derivam desse mesmo arquivo. Não há URL separada para notificações, atalho ou inicialização automática. O perfil WebView2, os cookies, o controle por logon e `app.log` ficam em `%LOCALAPPDATA%\PrevAlerta.Desktop`.

Para compilar, `desktop/PrevAlerta.App/config.json` é copiado para a saída. Se não existir, a compilação cria esse arquivo a partir de `config.example.json`, que é somente um modelo. Depois de publicar, o arquivo ao lado do executável é o único lido pelo app. Uma nova publicação pode copiar novamente a configuração de desenvolvimento; confira a URL antes de distribuir.

## Servidor indisponível

Falhas de conexão, certificado ou respostas HTTP 5xx exibem uma mensagem em português e o botão **Tentar novamente**. Uma navegação sem resposta é interrompida após 30 segundos. O botão abre novamente a URL configurada. A falta do WebView2 Runtime também recebe orientação em português.

## Notificações e autenticação

O app consulta `desktop/alert-summary` no servidor usando os cookies da sessão WebView2. A rota e o serviço de resumo existentes continuam responsáveis pelos números e pela autenticação. Não se executa `cake local_alert_summary` no computador cliente.

Após uma navegação bem-sucedida e a cada minuto com o app aberto, o cliente consulta o resumo atual. A requisição pede `no-cache, no-store`, e o endpoint já responde com `Cache-Control: no-store`. Redirecionamentos para login, erros e respostas sem JSON não geram alertas; autentique-se no próprio app quando necessário. A consulta HTTP tem limite de 15 segundos. URL e contagens recebidas são registradas em `app.log`.

O controle por logon, URL e contagens evita repetir um aviso idêntico, sem impedir novas consultas. Quando os totais mudam, a notificação é substituída pelo resumo atual; se ambos zerarem, o aviso anterior é removido. Somente `overdue` (ATRASADO) e `today` (HOJE) enviados pelo servidor entram no texto. O app não calcula recorrência. Fechar o app encerra as consultas; avisos são retratos do último resumo recebido. A entrega visual respeita as preferências do Windows.

O corpo e o botão **Abrir PrevAlerta** usam `prevalerta://open`: com o app fechado, iniciam o executável; com ele aberto, restauram a mesma janela. Não é necessário navegador externo.

O script manual existente `scripts/test-notification.ps1` agora abre/restaura o app com `--check-notification` e solicita uma nova consulta real, inclusive quando as contagens não mudaram. Não há números fictícios. Se necessário, autentique-se na janela. Sem pendências, o app não mostra aviso. Use `-AppPath` para escolher explicitamente uma instalação; o script instalado em `suporte/` usa o executável ao lado dessa pasta.

### Revisão do fluxo em 15/09/2026

O servidor e o comando já retornavam `{"overdue":1,"today":0}`. O antigo script manual enviava valores fixos `1` e `2`, e o cliente antigo deixava de consultar após o primeiro resumo válido do logon. O ajuste ficou no cliente e no script manual. RecurrenceService, dashboard, serviço de resumo, endpoint e comando foram preservados. A publicação local usa `http://127.0.0.1:8765/`; o instalador continua com o modelo `http://prevalerta/`. A URL definitiva deve ser configurada pela TI, sem fallback automático para localhost.

## Inicialização automática

Na raiz do projeto:

```powershell
.\scripts\register-startup.ps1
```

Use `-AppPath 'C:\caminho\PrevAlerta.exe'` para outra instalação. O script substitui a ação da tarefa existente `PrevAgenda - inicio local`, mantendo o nome para evitar uma segunda tarefa. Agora ela executa diretamente `PrevAlerta.exe`, 30 segundos após o logon, sem iniciar PHP, banco ou navegador. A tarefa permanece vinculada ao usuário e não encerra o app por limite de tempo. Nesta máquina, a ação foi registrada e conferida apontando para a pasta publicada.

## Compilar/publicar

Com o SDK .NET 10 instalado, na raiz do projeto:

```powershell
dotnet publish desktop/PrevAlerta.App/PrevAlerta.App.csproj -c Release -r win-x64 --self-contained true -o desktop/artifacts/win-x64
```

Nesta sessão, o SDK foi obtido em `tmp/dotnet`; nesta máquina também é possível usar `.\tmp\dotnet\dotnet.exe` no lugar de `dotnet`. Feche o app antes de republicar.

## Validação realizada em 15/09/2026

- Publicação Release x64 concluída sem erros ou avisos após os ajustes.
- O WebView2 abriu o dashboard CakePHP do servidor local, com HTTP 200 e título `Dashboard · PrevAgenda` confirmado na janela.
- A consulta real retornou `overdue: 0` e `today: 0`; o log confirmou resumo vazio, sem notificação de pendência.
- Uma URL local em porta indisponível exibiu a mensagem amigável e o botão Tentar novamente. A configuração foi restaurada para `http://127.0.0.1:8765/` ao concluir.
- A notificação manual foi enviada e seu XML no histórico do Windows confirmou `prevalerta://open` no corpo e no botão.
- A ativação desse protocolo foi executada com o app fechado e minimizado: abriu o app e restaurou a mesma instância, respectivamente.
- Atalho do menu Iniciar e ação da tarefa agendada conferidos apontando para `PrevAlerta.exe`.

Não foram criados testes automatizados. O clique físico no banner, o próximo logon completo, a autenticação no servidor definitivo e uma instalação em Windows limpo ainda devem ser conferidos na homologação do instalador.

## Distribuição e pendências de implantação

O instalador foi preparado em `desktop/installer/PrevAlerta.iss`; `desktop/installer/build.ps1` gera `dist/` a partir da publicação existente. O ícone para empacotamento fica em `desktop/assets/PrevAlerta.ico`. O pacote não contém PHP, MariaDB ou configuração de banco.

A TI ainda precisa provisionar o servidor/DNS, definir a URL definitiva e homologar o uso na infraestrutura da empresa, incluindo logon e instalação do WebView2 em um PC limpo. O Setup não tem assinatura digital da organização. A desinstalação remove o app, atalhos e registros do instalador, preservando `config.json`, o perfil local e o WebView2 compartilhado.

Os scripts de tarefa agendada descritos anteriormente destinam-se à execução direta da publicação de desenvolvimento. O instalador usa somente o atalho de inicialização do usuário. Se já existir a tarefa antiga `PrevAgenda - inicio local` nessa conta, a TI deve desativá-la ao migrar para o Setup.

Referências técnicas: [WebView2 em WinForms](https://github.com/MicrosoftDocs/edge-developer/blob/main/microsoft-edge/webview2/get-started/winforms.md) e [notificações de aplicativos desktop](https://learn.microsoft.com/en-us/uwp/api/windows.ui.notifications.toastnotificationmanager.createtoastnotifier).
