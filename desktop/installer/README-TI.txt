PREVALERTA - ENTREGA PARA TI

SERVIDOR: implantar o projeto CakePHP conforme DEPLOY-SERVIDOR.md.
O Setup instala somente o cliente Windows; PHP e MariaDB ficam no servidor.

1. INSTALAR
   Execute PrevAlerta-Setup.exe na conta Windows que usará o sistema.
   Compatível com Windows 10 (build 19041 ou superior)/11 x64.
   Instalação por usuário, sem elevação: não executar como outra conta ou SYSTEM.
   Destino padrão: %LOCALAPPDATA%\Programs\PrevAlerta
   O pacote inclui .NET e o instalador offline oficial do WebView2.
   Escolha o atalho da Área de Trabalho e, se desejado, o início com o Windows.
   O Menu Iniciar e a desinstalação pelo Windows são configurados automaticamente.

2. CONFIGURAR A URL
   Feche o app e edite config.json na pasta de instalação:
   {
     "serverUrl": "http://prevalerta/"
   }
   Substitua pela URL definida pela TI. Use somente a URL, sem marcação Markdown.
   Reabra o aplicativo. Não exige recompilação nem reinstalação.
   O instalador preserva config.json existente nas atualizações e desinstalação.
   Nenhuma credencial de banco deve ser colocada nesse arquivo ou nos PCs.

3. TESTAR O SERVIDOR
   Confirme DNS/rede e abra a URL no app. Entre com o usuário criado no servidor.
   Teste o dashboard. HTTPS requer certificado confiável na conta/computador.
   Se indisponível, o app mostra uma mensagem e o botão Tentar novamente.

4. TESTAR NOTIFICAÇÃO
   Abra o app uma vez e execute no PowerShell:
   & "$env:LOCALAPPDATA\Programs\PrevAlerta\suporte\test-notification.ps1"
   Ajuste o caminho se escolheu outra pasta. O script solicita uma consulta
   REAL pelo app, com a sessão e a URL do config.json. Não usa dados fictícios.
   Clique no aviso para abrir/restaurar o app; se necessário, faça login.
   Respeite a política de execução de scripts da empresa; a TI pode autorizar
   esse script ou testar com pendências reais em ambiente de homologação.
   O app consulta a cada minuto; só atualiza o aviso quando os totais mudam.
   O script manual permite repetir a consulta e o aviso com os mesmos totais.
   Sem pendências, o aviso anterior é removido e nenhum novo é mostrado.
   Confira URL e totais em %LOCALAPPDATA%\PrevAlerta.Desktop\app.log.
   Confira as permissões de notificações e o modo Não incomodar do Windows.

5. TESTAR INICIALIZAÇÃO AUTOMÁTICA
   Marque "Iniciar o PrevAlerta ao entrar no Windows" no instalador.
   Saia e entre na conta e confirme a abertura do app.
   Para mudar a opção, execute o mesmo Setup e marque/desmarque a tarefa.
   A opção usa um atalho em shell:startup do usuário; não abre navegador.
   Em PCs com a versão de desenvolvimento, a TI deve desativar a tarefa antiga
   "PrevAgenda - inicio local" dessa conta para evitar dois iniciadores.

MANUTENÇÃO
   Para atualizar o cliente, execute o novo Setup na mesma conta e pasta.
   Para remover, feche o app e use:
   Configurações > Aplicativos > PrevAlerta > Desinstalar.
   config.json e %LOCALAPPDATA%\PrevAlerta.Desktop (sessão/logs) são preservados.
   O WebView2 compartilhado com outros programas não é desinstalado.
   A TI pode remover esses dados locais após a desinstalação se necessário.
   Atualizações somente do CakePHP são feitas no servidor, via Git.

AUTOMAÇÃO PELA TI (executar no contexto do usuário)
   PrevAlerta-Setup.exe /VERYSILENT /SUPPRESSMSGBOXES /NORESTART /TASKS="desktopicon,startup" /LOG="C:\caminho-gravavel\PrevAlerta-install.log"
   Use /TASKS="desktopicon" para não habilitar o início automático.
   Confira o código de saída e o log da instalação.

PACOTE
   PrevAlerta-Setup.exe, README-TI.txt, config.example.json,
   DEPLOY-SERVIDOR.md e SHA256SUMS.txt.
   O servidor precisa também do código-fonte/repositório do projeto, entregue
   separadamente. O Setup não contém o projeto PHP nem credenciais de banco.
   Este Setup não tem assinatura digital da organização; se a política exigir,
   a TI deve assiná-lo com seu certificado e recalcular o SHA256.
