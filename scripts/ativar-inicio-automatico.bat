@echo off
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-startup.ps1"
if errorlevel 1 (
  echo Nao foi possivel concluir. Consulte o erro acima e "%~dp0..\docs\OPERACAO-PC.md".
  pause
  exit /b 1
)
echo Configuracao concluida. Esta janela pode ser fechada.
pause
