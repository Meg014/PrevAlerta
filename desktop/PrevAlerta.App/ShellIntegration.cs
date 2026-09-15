using Microsoft.Win32;
using System.Runtime.InteropServices;

namespace PrevAlerta.App;

internal static class ShellIntegration
{
    public const string AppId = "PrevAlerta.Desktop";
    public const string ActivationUri = "prevalerta://open";

    public static void Register()
    {
        var executable = Path.Combine(AppContext.BaseDirectory, "PrevAlerta.exe");
        if (!File.Exists(executable)) throw new FileNotFoundException("Execute PrevAlerta.exe para habilitar o clique das notificações.");
        var icon = Path.Combine(AppContext.BaseDirectory, "PrevAlerta.ico");
        using (var protocol = Registry.CurrentUser.CreateSubKey(@"Software\Classes\prevalerta"))
        {
            protocol.SetValue("", "URL:PrevAlerta");
            protocol.SetValue("URL Protocol", "");
            using var command = protocol.CreateSubKey(@"shell\open\command");
            command.SetValue("", $"\"{executable}\" --activate \"%1\"");
        }
        using (var app = Registry.CurrentUser.CreateSubKey(@"Software\Classes\AppUserModelId\" + AppId))
        {
            app.SetValue("DisplayName", "PrevAlerta");
            app.SetValue("IconUri", icon);
        }
        var shortcutPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Programs), "PrevAlerta.lnk");
        var shellType = Type.GetTypeFromProgID("WScript.Shell")!;
        dynamic shell = Activator.CreateInstance(shellType)!;
        dynamic shortcut = shell.CreateShortcut(shortcutPath);
        try
        {
            shortcut.TargetPath = executable;
            shortcut.Arguments = "";
            shortcut.WorkingDirectory = AppContext.BaseDirectory;
            shortcut.IconLocation = icon + ",0";
            shortcut.Save();
        }
        finally
        {
            Marshal.FinalReleaseComObject(shortcut);
            Marshal.FinalReleaseComObject(shell);
        }
        PrevAgendaShortcut.Register(shortcutPath, AppId);
    }
}
