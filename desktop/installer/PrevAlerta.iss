#define AppVersion "0.1.0"
#define PublishDir "..\artifacts\win-x64"

[Setup]
AppId={{E5BCCF0C-60B0-46E1-A71B-87D1CFDD77D4}
AppName=PrevAlerta
AppVersion={#AppVersion}
AppPublisher=PrevAlerta
DefaultDirName={localappdata}\Programs\PrevAlerta
DefaultGroupName=PrevAlerta
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0.19041
OutputDir=..\..\dist
OutputBaseFilename=PrevAlerta-Setup
SetupIconFile=..\assets\PrevAlerta.ico
UninstallDisplayIcon={app}\PrevAlerta.ico
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
CloseApplications=yes
CloseApplicationsFilter=PrevAlerta.exe
RestartApplications=no
ChangesAssociations=yes
Uninstallable=yes
VersionInfoDescription=Instalador do PrevAlerta

[Languages]
Name: "brazilianportuguese"; MessagesFile: "compiler:Languages\BrazilianPortuguese.isl"

[Tasks]
Name: "desktopicon"; Description: "Criar atalho na Área de Trabalho"; Flags: checkedonce
Name: "startup"; Description: "Iniciar o PrevAlerta ao entrar no Windows"; Flags: unchecked

[Files]
Source: "{#PublishDir}\*"; DestDir: "{app}"; Excludes: "config.json,PrevAlerta.ico,*.pdb,*.xml"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\assets\PrevAlerta.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "config.example.json"; DestDir: "{app}"; DestName: "config.json"; Flags: onlyifdoesntexist uninsneveruninstall
Source: "config.example.json"; DestDir: "{app}"; Flags: ignoreversion
Source: "README-TI.txt"; DestDir: "{app}"; Flags: ignoreversion
Source: "licenses\*"; DestDir: "{app}\licenses"; Flags: ignoreversion
Source: "..\..\scripts\local-notification.ps1"; DestDir: "{app}\suporte"; Flags: ignoreversion
Source: "..\..\scripts\test-notification.ps1"; DestDir: "{app}\suporte"; Flags: ignoreversion
Source: "prerequisites\MicrosoftEdgeWebView2RuntimeInstallerX64.exe"; Flags: dontcopy nocompression

[Icons]
Name: "{userdesktop}\PrevAlerta"; Filename: "{app}\PrevAlerta.exe"; WorkingDir: "{app}"; IconFilename: "{app}\PrevAlerta.ico"; AppUserModelID: "PrevAlerta.Desktop"; Tasks: desktopicon
Name: "{userprograms}\PrevAlerta"; Filename: "{app}\PrevAlerta.exe"; WorkingDir: "{app}"; IconFilename: "{app}\PrevAlerta.ico"; AppUserModelID: "PrevAlerta.Desktop"
Name: "{userstartup}\PrevAlerta"; Filename: "{app}\PrevAlerta.exe"; WorkingDir: "{app}"; IconFilename: "{app}\PrevAlerta.ico"; AppUserModelID: "PrevAlerta.Desktop"; Tasks: startup

[Registry]
Root: HKCU; Subkey: "Software\Classes\prevalerta"; ValueType: string; ValueName: ""; ValueData: "URL:PrevAlerta"; Flags: uninsdeletekey
Root: HKCU; Subkey: "Software\Classes\prevalerta"; ValueType: string; ValueName: "URL Protocol"; ValueData: ""
Root: HKCU; Subkey: "Software\Classes\prevalerta\DefaultIcon"; ValueType: string; ValueName: ""; ValueData: """{app}\PrevAlerta.ico"",0"
Root: HKCU; Subkey: "Software\Classes\prevalerta\shell\open\command"; ValueType: string; ValueName: ""; ValueData: """{app}\PrevAlerta.exe"" --activate ""%1"""
Root: HKCU; Subkey: "Software\Classes\AppUserModelId\PrevAlerta.Desktop"; ValueType: string; ValueName: "DisplayName"; ValueData: "PrevAlerta"; Flags: uninsdeletekey
Root: HKCU; Subkey: "Software\Classes\AppUserModelId\PrevAlerta.Desktop"; ValueType: string; ValueName: "IconUri"; ValueData: "{app}\PrevAlerta.ico"

[Run]
Filename: "{app}\PrevAlerta.exe"; Description: "Abrir PrevAlerta"; Flags: nowait postinstall skipifsilent

[Code]
function InitializeUninstall: Boolean;
begin
  Result := FindWindowByWindowName('PrevAlerta') = 0;
  if not Result then
    SuppressibleMsgBox('Feche o PrevAlerta antes de desinstalar. Depois, execute a desinstalação novamente.', mbError, MB_OK, IDOK);
end;

function RuntimeRegistered(RootKey: Integer): Boolean;
var
  Version: String;
begin
  Result := RegQueryStringValue(RootKey,
    'Software\Microsoft\EdgeUpdate\Clients\{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}', 'pv', Version)
    and (Version <> '') and (Version <> '0.0.0.0');
end;

function HasWebView2: Boolean;
begin
  Result := RuntimeRegistered(HKLM32) or RuntimeRegistered(HKCU32);
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ExitCode: Integer;
begin
  Result := '';
  if HasWebView2 then
  begin
    Log('WebView2 Runtime já instalado; instalação do pré-requisito dispensada.');
    Exit;
  end;
  WizardForm.StatusLabel.Caption := 'Instalando Microsoft Edge WebView2 Runtime...';
  ExtractTemporaryFile('MicrosoftEdgeWebView2RuntimeInstallerX64.exe');
  if not Exec(ExpandConstant('{tmp}\MicrosoftEdgeWebView2RuntimeInstallerX64.exe'),
    '/silent /install', '', SW_HIDE, ewWaitUntilTerminated, ExitCode) then
  begin
    Result := 'Não foi possível iniciar a instalação do WebView2. Solicite apoio à TI e execute o instalador novamente.';
    Exit;
  end;
  Log(Format('Instalação WebView2: código %d.', [ExitCode]));
  if ExitCode = 3010 then NeedsRestart := True;
  if not HasWebView2 then
    Result := Format('O WebView2 ainda não está disponível (código %d). Reinicie o Windows se solicitado e tente novamente. Se necessário, solicite a instalação do Microsoft Edge WebView2 Runtime à TI.', [ExitCode]);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
  begin
    // Remove optional shortcuts when the task is deselected on an upgrade.
    if not WizardIsTaskSelected('startup') then
      DeleteFile(ExpandConstant('{userstartup}\PrevAlerta.lnk'));
    if not WizardIsTaskSelected('desktopicon') then
      DeleteFile(ExpandConstant('{userdesktop}\PrevAlerta.lnk'));
  end;
end;
