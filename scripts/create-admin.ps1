param(
    [Parameter(Mandatory=$true)][string]$Email,
    [string]$Name = 'Administrador'
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'runtime-paths.ps1')
$phpExecutable = Resolve-PrevAgendaPhp
$passwordInput = Read-Host 'Senha (12 a 72 caracteres)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordInput)
try {
    $env:PREV_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $cakePath = Join-Path (Split-Path -Parent $PSScriptRoot) 'bin\cake.php'
    & $phpExecutable $cakePath create_admin $Email $Name
    $commandExitCode = $LASTEXITCODE
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    Remove-Item Env:\PREV_ADMIN_PASSWORD -ErrorAction SilentlyContinue
}
exit $commandExitCode
