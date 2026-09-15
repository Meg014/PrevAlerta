using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Security.Principal;

namespace PrevAlerta.App;

internal static class Program
{
    [DllImport("user32.dll")]
    private static extern bool AllowSetForegroundWindow(int processId);

    [STAThread]
    private static void Main(string[] args)
    {
        ApplicationConfiguration.Initialize();
        var checkNotification = args.Contains("--check-notification", StringComparer.OrdinalIgnoreCase);
        var suffix = WindowsIdentity.GetCurrent().User!.Value;
        var name = @"Local\PrevAlerta.Desktop." + suffix;
        using var mutex = new Mutex(true, name, out var first);
        if (!first)
        {
            foreach (var process in Process.GetProcessesByName("PrevAlerta"))
                if (process.Id != Environment.ProcessId && process.SessionId == Process.GetCurrentProcess().SessionId)
                    AllowSetForegroundWindow(process.Id);
            for (var attempt = 0; attempt < 30; attempt++)
            {
                try
                {
                    using var signal = EventWaitHandle.OpenExisting(name + (checkNotification ? ".CheckNotification" : ".Activate"));
                    signal.Set();
                    return;
                }
                catch (WaitHandleCannotBeOpenedException) { Thread.Sleep(100); }
            }
            return;
        }
        try
        {
            var config = AppConfig.Load();
            try { ShellIntegration.Register(); }
            catch (Exception error) { LocalFiles.Log("Integração Windows: " + error.Message); }
            using var activation = new EventWaitHandle(false, EventResetMode.AutoReset, name + ".Activate");
            using var notificationCheck = new EventWaitHandle(false, EventResetMode.AutoReset, name + ".CheckNotification");
            using var form = new MainForm(config, checkNotification);
            // Create the handle before listening for a second instance.
            _ = form.Handle;
            var wait = ThreadPool.RegisterWaitForSingleObject(activation, (_, _) =>
            {
                try { form.BeginInvoke(form.RestoreWindow); }
                catch (InvalidOperationException) { }
            }, null, Timeout.Infinite, false);
            var notificationWait = ThreadPool.RegisterWaitForSingleObject(notificationCheck, (_, _) =>
            {
                try { form.BeginInvoke(form.CheckNotification); }
                catch (InvalidOperationException) { }
            }, null, Timeout.Infinite, false);
            try { Application.Run(form); }
            finally { wait.Unregister(null); notificationWait.Unregister(null); }
        }
        catch (Exception error)
        {
            LocalFiles.Log(error.ToString());
            MessageBox.Show("Não foi possível iniciar o PrevAlerta.\n" + error.Message,
                "PrevAlerta", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
        finally { mutex.ReleaseMutex(); }
    }
}
