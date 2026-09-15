param(
    [string]$AppPath = (Join-Path (Split-Path -Parent $PSScriptRoot) 'desktop\artifacts\win-x64\PrevAlerta.exe')
)
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $AppPath -PathType Leaf)) { throw "Compile o app primeiro. Executavel nao encontrado: $AppPath" }
$AppPath = (Resolve-Path -LiteralPath $AppPath).Path
if (-not (Test-Path -LiteralPath (Join-Path (Split-Path -Parent $AppPath) 'config.json'))) { throw 'config.json nao encontrado ao lado do app.' }
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
# Reuse the existing task name to avoid duplicate startup.
$taskName = 'PrevAgenda - inicio local'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    $owner = $existing.Principal.UserId
    $ownerSid = if ($owner -match '^S-1-') { $owner } else {
        ([System.Security.Principal.NTAccount]::new($owner)).Translate([System.Security.Principal.SecurityIdentifier]).Value
    }
    if ($ownerSid -ne $identity.User.Value) { throw 'Ja existe uma tarefa PrevAgenda de outra conta. Nao foi substituida.' }
}
$action = New-ScheduledTaskAction -Execute $AppPath -WorkingDirectory (Split-Path -Parent $AppPath)
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $identity.Name
$trigger.Delay = 'PT30S'
$principal = New-ScheduledTaskPrincipal -UserId $identity.Name -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero)
$null = Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'PrevAlerta: app Windows conectado ao servidor central.' -Force
Start-ScheduledTask -TaskName $taskName
Write-Output "Inicializacao configurada para o app: $AppPath"
