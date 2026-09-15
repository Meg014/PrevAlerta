param([string]$Iscc = 'ISCC.exe')
$ErrorActionPreference = 'Stop'
$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$publishPath = Join-Path $projectPath 'desktop\artifacts\win-x64'
$runtimePath = Join-Path $PSScriptRoot 'prerequisites\MicrosoftEdgeWebView2RuntimeInstallerX64.exe'
$distPath = Join-Path $projectPath 'dist'

foreach ($file in @('PrevAlerta.exe','PrevAlerta.dll','PrevAlerta.ico','PrevAlerta.png','PrevAlerta.deps.json','PrevAlerta.runtimeconfig.json','Microsoft.Web.WebView2.Core.dll','Microsoft.Web.WebView2.WinForms.dll','coreclr.dll')) {
    if (-not (Test-Path -LiteralPath (Join-Path $publishPath $file))) { throw "Publicacao incompleta: $file" }
}
# Only client runtime files may be packaged. config.json is excluded by the .iss.
foreach ($file in Get-ChildItem -LiteralPath $publishPath -File -Recurse) {
    if ($file.Extension -notin @('.exe','.dll','.json','.ico','.png','.pdb','.xml')) { throw "Arquivo inesperado na publicacao: $($file.Name)" }
    if ($file.Extension -eq '.json' -and $file.Name -notin @('config.json','PrevAlerta.deps.json','PrevAlerta.runtimeconfig.json')) { throw "JSON inesperado na publicacao: $($file.Name)" }
}
$config = Get-Content (Join-Path $PSScriptRoot 'config.example.json') -Raw | ConvertFrom-Json
if ($config.serverUrl -ne 'http://prevalerta/' -or @($config.PSObject.Properties).Count -ne 1) { throw 'O modelo de distribuicao deve conter somente serverUrl=http://prevalerta/.' }
$signature = Get-AuthenticodeSignature -LiteralPath $runtimePath
if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') { throw 'WebView2 ausente ou sem assinatura Microsoft valida. Obtenha o instalador Evergreen Standalone x64 oficial.' }
$compiler = Get-Command $Iscc -CommandType Application -ErrorAction Stop
New-Item -ItemType Directory -Path $distPath -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'README-TI.txt') -Destination $distPath
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'config.example.json') -Destination $distPath
Copy-Item -LiteralPath (Join-Path $projectPath 'docs\DEPLOY-SERVIDOR.md') -Destination $distPath
& $compiler.Source (Join-Path $PSScriptRoot 'PrevAlerta.iss')
if ($LASTEXITCODE -ne 0) { throw 'Falha ao compilar o instalador.' }
$checksums = foreach ($name in @('PrevAlerta-Setup.exe','README-TI.txt','config.example.json','DEPLOY-SERVIDOR.md')) {
    $hash = Get-FileHash -Algorithm SHA256 -LiteralPath (Join-Path $distPath $name)
    "$($hash.Hash)  $name"
}
$checksums | Set-Content -LiteralPath (Join-Path $distPath 'SHA256SUMS.txt') -Encoding ascii
Write-Output "Pacote pronto: $distPath"
