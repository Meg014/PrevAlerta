# Empacotamento do cliente

Este diretório empacota a publicação existente. Não compila nem modifica o CakePHP ou o app.

- `PrevAlerta.iss`: instalador Inno Setup, por usuário, Windows x64.
- `../assets/PrevAlerta.ico`: ícone oficial com transparência, em 16, 24, 32, 48, 64, 128 e 256 pixels, derivado de `webroot/ICON.png`. O PNG em `../assets/PrevAlerta.png` é usado como imagem nas notificações.
- `config.example.json`: configuração de distribuição, sem credenciais.
- `prerequisites/`: instalador offline do WebView2, obtido da Microsoft e ignorado pelo Git.
- `build.ps1`: confere os arquivos do cliente e a assinatura do WebView2, compila e gera `dist/` com documentação e hashes.

## Gerar novamente

1. Disponibilize a publicação completa em `desktop/artifacts/win-x64`.
2. Instale o [Inno Setup 6](https://jrsoftware.org/isdl.php) na máquina de empacotamento.
3. Baixe o [WebView2 Evergreen Standalone x64](https://go.microsoft.com/fwlink/?linkid=2124701) em `desktop/installer/prerequisites/MicrosoftEdgeWebView2RuntimeInstallerX64.exe`.
4. Na raiz do projeto, execute:

```powershell
.\desktop\installer\build.ps1 -Iscc 'C:\caminho\Inno Setup 6\ISCC.exe'
```

Nesta máquina, o compilador usado está em `tmp/packaging/inno/ISCC.exe` (Inno Setup 6.7.3). O build não incorpora o `config.json` local da publicação: sempre usa o modelo do instalador. Reinstalações preservam o arquivo já instalado.

O instalador verifica o Runtime nos registros HKLM/HKCU e instala a cópia offline somente quando necessário, conforme a [documentação de distribuição do WebView2](https://learn.microsoft.com/en-us/microsoft-edge/webview2/concepts/distribution). O Runtime é compartilhado e permanece na desinstalação. O perfil local e `config.json` também são preservados.

O app e seus registros são por usuário para acompanhar a integração Windows já implementada. O início automático usa `shell:startup`; não depende dos scripts de desenvolvimento ou de tarefa agendada. Instale na conta do usuário final, inclusive em distribuição gerenciada. Não execute como SYSTEM ou sob credenciais de outra conta.

O Setup não tem assinatura digital da organização. Caso seja assinado posteriormente, atualize o hash de `PrevAlerta-Setup.exe` em `dist/SHA256SUMS.txt`.

## Validação da entrega — 15/09/2026

- Compilação do instalador concluída, sem alteração/recompilação do app existente.
- Instalação silenciosa real em pasta temporária concluída com código 0; WebView2 existente reconhecido.
- Atalhos da Área de Trabalho, Menu Iniciar e inicialização apontaram para o executável instalado e o ícone oficial.
- Configuração inicial instalada com `http://prevalerta/`. Após edição, a reinstalação preservou a URL; desmarcar início automático removeu o atalho correspondente.
- O executável instalado abriu o dashboard local com HTTP 200. Executável e ícone instalados conferidos por SHA256 contra os originais.
- O script manual existente de notificação foi executado a partir da pasta instalada e retornou `sent`.
- Desinstalação com app aberto recusada com código 1; com app fechado, concluída com código 0, removendo executável, atalhos e registros e preservando somente `config.json` na pasta de instalação.
- A instalação temporária foi removida e os registros/atalho anteriores da máquina de desenvolvimento foram restaurados.

Logs locais em `tmp/packaging/`. Nenhum teste automatizado novo foi criado. Instalação do Runtime quando ausente, próximo logon completo e conexão/autenticação no servidor definitivo dependem da homologação da TI em seu ambiente.
