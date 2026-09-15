using System.Diagnostics;
using System.Text.RegularExpressions;
using Windows.Data.Xml.Dom;
using Windows.UI.Notifications;

namespace PrevAlerta.App;

internal static class DesktopNotification
{
    private static string StatePath => Path.Combine(LocalFiles.DirectoryPath, "notification-session.txt");
    private static string? logonId;
    private static string SessionKey(AppConfig config)
    {
        if (logonId is null)
        {
            using var process = Process.Start(new ProcessStartInfo
            {
                FileName = Path.Combine(Environment.SystemDirectory, "whoami.exe"),
                Arguments = "/logonid", UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true
            })!;
            if (!process.WaitForExit(5000)) { process.Kill(); throw new IOException("Não foi possível identificar o logon do Windows."); }
            var value = Regex.Match(process.StandardOutput.ReadToEnd(), @"S-1-5-5-\d+-\d+").Value;
            if (process.ExitCode != 0 || value.Length == 0) throw new IOException("Não foi possível identificar o logon do Windows.");
            logonId = value;
        }
        return logonId + "|" + config.ServerUri.AbsoluteUri;
    }

    public static void Deliver(AppConfig config, int overdue, int today, bool force = false)
    {
        var total = (long)overdue + today;
        var signature = $"{SessionKey(config)}|{overdue}|{today}";
        // Deduplicate delivery, never the HTTP query: server counts may change during this logon.
        ToastNotificationManager.History.Remove("test", "logon", ShellIntegration.AppId);
        if (total == 0)
            ToastNotificationManager.History.Remove("startup", "logon", ShellIntegration.AppId);
        if (!force && File.Exists(StatePath) && File.ReadAllText(StatePath) == signature) return;
        if (total > 0)
        {
            var headline = total == 1 ? "1 checklist precisa de atenção." : $"{total} checklists precisam de atenção.";
            var late = overdue == 1 ? "1 atrasado." : $"{overdue} atrasados.";
            var due = today == 1 ? "1 vence hoje." : $"{today} vencem hoje.";
            var logo = System.Security.SecurityElement.Escape(new Uri(Path.Combine(AppContext.BaseDirectory, "PrevAlerta.png")).AbsoluteUri);
            var xml = new XmlDocument();
            xml.LoadXml($"""
                <toast activationType="protocol" launch="{ShellIntegration.ActivationUri}">
                  <visual><binding template="ToastGeneric"><image placement="appLogoOverride" src="{logo}" /><text>PrevAlerta</text><text>{headline}</text><text>{late} {due}</text></binding></visual>
                  <actions><action content="Abrir PrevAlerta" activationType="protocol" arguments="{ShellIntegration.ActivationUri}" /></actions>
                </toast>
                """);
            ToastNotificationManager.CreateToastNotifier(ShellIntegration.AppId).Show(new ToastNotification(xml)
            {
                Tag = "startup", Group = "logon", ExpirationTime = DateTimeOffset.Now.AddDays(1)
            });
        }
        // Only persist successful delivery so Windows failures can be retried.
        Directory.CreateDirectory(LocalFiles.DirectoryPath);
        File.WriteAllText(StatePath, signature);
        LocalFiles.Log($"Resumo do servidor: atrasados={overdue}, hoje={today}; notificação={(total > 0 ? "enviada" : "vazio")}.");
    }
}
