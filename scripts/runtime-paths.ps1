# Resolve the executable on this computer; never rely on the original installation path.
function Resolve-PrevAgendaPhp {
    param([string]$Php = 'php.exe')
    $command = Get-Command -Name $Php -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $command) {
        throw 'PHP nao encontrado. Instale PHP 8.2 ou superior e configure a pasta de php.exe no PATH do Windows. Depois, abra uma nova sessao e tente novamente.'
    }
    return $command.Source
}
