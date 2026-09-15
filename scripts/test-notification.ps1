param([string]$AppPath = '')
$ErrorActionPreference = 'Stop'
if (-not $AppPath) {
    $parentPath = Split-Path -Parent $PSScriptRoot
    $AppPath = Join-Path $parentPath 'PrevAlerta.exe'
    if (-not (Test-Path -LiteralPath $AppPath -PathType Leaf)) {
        $AppPath = Join-Path $parentPath 'desktop\artifacts\win-x64\PrevAlerta.exe'
    }
}
if (-not (Test-Path -LiteralPath $AppPath -PathType Leaf)) { throw "App nao encontrado: $AppPath. Informe -AppPath com o caminho do PrevAlerta.exe atualizado." }
$AppPath = (Resolve-Path -LiteralPath $AppPath).Path
Start-Process -FilePath $AppPath -ArgumentList '--check-notification'
Write-Output 'Consulta real solicitada ao app. A URL vem do config.json e a autenticacao usa a sessao do WebView2.'
Write-Output 'Sem pendencias, nao aparece aviso. Confira URL e contagens em %LOCALAPPDATA%\PrevAlerta.Desktop\app.log.'
Write-Output 'Clique na notificacao para abrir/restaurar o PrevAlerta. Este script nao usa numeros ficticios.'
