param(
    [string]$Php = 'php.exe',
    [string]$MariaDb = '',
    [ValidateRange(1, 65535)]
    [int]$WebPort = 8765
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'runtime-paths.ps1')
$Php = Resolve-PrevAgendaPhp -Php $Php
$serverLock = New-Object System.Threading.Mutex($false, "Local\PrevAgenda.Server.$WebPort")
$lockTaken = $false
try {
    try { $lockTaken = $serverLock.WaitOne(60000) } catch [System.Threading.AbandonedMutexException] { $lockTaken = $true }
    if (-not $lockTaken) { throw 'Outra inicializacao do PrevAgenda ainda esta em andamento.' }
$projectPath = Split-Path -Parent $PSScriptRoot
$null = New-Item -ItemType Directory -Path (Join-Path $projectPath 'logs') -Force
foreach ($requiredPath in @($Php, (Join-Path $projectPath 'config\app_local.php'), (Join-Path $projectPath 'vendor\autoload.php'))) {
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Arquivo necessario nao encontrado: $requiredPath"
    }
}
$databaseConfig = Join-Path $projectPath 'tmp\mariadb\my.ini'
if (Test-Path -LiteralPath $databaseConfig) {
    if (-not (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) {
        # Legacy isolated instance only. A new installation uses its Windows MariaDB service.
        $databaseCommands = if ($MariaDb) { @($MariaDb) } else { @('mariadbd.exe', 'mysqld.exe') }
        $databaseCommand = $databaseCommands | ForEach-Object { Get-Command -Name $_ -CommandType Application -ErrorAction SilentlyContinue } | Select-Object -First 1
        if (-not $databaseCommand) { throw 'MariaDB local nao encontrado. Para usar tmp\mariadb\my.ini, configure mariadbd.exe ou mysqld.exe no PATH. Em uma instalacao nova, use o servico MariaDB do Windows e config\app_local.php.' }
        $MariaDb = $databaseCommand.Source
        $databaseProcess = Start-Process -FilePath $MariaDb -ArgumentList "--defaults-file=`"$databaseConfig`"", '--bind-address=127.0.0.1', '--port=3307' -WindowStyle Hidden -PassThru
        $deadline = (Get-Date).AddSeconds(30)
        while (-not (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) {
            if ($databaseProcess.HasExited -or (Get-Date) -ge $deadline) {
                throw 'MariaDB nao iniciou na porta 3307. Consulte os logs em tmp\mariadb.'
            }
            Start-Sleep -Milliseconds 500
        }
    }
}
if (-not (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort $WebPort -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $Php -WorkingDirectory $projectPath -ArgumentList '-S', "127.0.0.1:$WebPort", '-t', 'webroot', 'webroot/index.php' -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectPath 'logs\server.out.log') -RedirectStandardError (Join-Path $projectPath 'logs\server.err.log')
}
$dashboardUrl = "http://127.0.0.1:$WebPort/"
$deadline = (Get-Date).AddSeconds(30)
do {
    try {
        $response = Invoke-WebRequest -Uri $dashboardUrl -UseBasicParsing -TimeoutSec 30
        if ($response.StatusCode -eq 200 -and $response.Content -match 'Painel de alertas') {
            Write-Output "PrevAgenda pronto: $dashboardUrl"
            return
        }
    } catch {
        $startupError = $_.Exception.Message
    }
    Start-Sleep -Milliseconds 500
} while ((Get-Date) -lt $deadline)
throw "PrevAgenda nao respondeu corretamente em $dashboardUrl. Verifique a porta e logs\server.err.log. $startupError"
} finally {
    if ($lockTaken) { $serverLock.ReleaseMutex() }
    $serverLock.Dispose()
}
