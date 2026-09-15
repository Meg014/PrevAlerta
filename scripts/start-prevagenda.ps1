param(
    [string]$AppPath = (Join-Path (Split-Path -Parent $PSScriptRoot) 'desktop\artifacts\win-x64\PrevAlerta.exe')
)
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $AppPath -PathType Leaf)) { throw "Compile o app primeiro. Executavel nao encontrado: $AppPath" }
$AppPath = (Resolve-Path -LiteralPath $AppPath).Path
Start-Process -FilePath $AppPath -WorkingDirectory (Split-Path -Parent $AppPath)
