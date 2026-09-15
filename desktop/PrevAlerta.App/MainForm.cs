using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;
using System.Text.Json;

namespace PrevAlerta.App;

internal sealed class MainForm : Form
{
    private readonly AppConfig config;
    private readonly WebView2 browser = new() { Dock = DockStyle.Fill };
    private readonly Panel failure = new() { Dock = DockStyle.Fill, Visible = false };
    private readonly Label message = new() { Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleCenter };
    private readonly System.Windows.Forms.Timer timer = new() { Interval = 60000 };
    private readonly System.Windows.Forms.Timer navigationTimeout = new() { Interval = 30000 };
    private bool checking;
    private bool forceNextSummary;

    public MainForm(AppConfig config, bool checkNotification = false)
    {
        this.config = config;
        forceNextSummary = checkNotification;
        LocalFiles.Log($"App: {Application.ExecutablePath}; servidor configurado: {config.ServerUri}; resumo: {config.SummaryUri}");
        Text = "PrevAlerta";
        Size = new Size(1280, 850);
        MinimumSize = new Size(800, 600);
        StartPosition = FormStartPosition.CenterScreen;
        Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
        var retry = new Button { Text = "Tentar novamente", Dock = DockStyle.Bottom, Height = 48 };
        retry.Click += async (_, _) => await OpenServerAsync();
        failure.Controls.Add(message);
        failure.Controls.Add(retry);
        Controls.Add(browser);
        Controls.Add(failure);
        Shown += async (_, _) => await OpenServerAsync();
        timer.Tick += async (_, _) => await CheckSummaryAsync();
        navigationTimeout.Tick += (_, _) => { navigationTimeout.Stop(); browser.CoreWebView2?.Stop(); ShowFailure(); };
    }

    public void RestoreWindow()
    {
        Show();
        if (WindowState == FormWindowState.Minimized) WindowState = FormWindowState.Normal;
        Activate();
        BringToFront();
    }

    public void CheckNotification()
    {
        forceNextSummary = true;
        RestoreWindow();
        _ = CheckSummaryAsync();
    }

    private void ShowFailure(string? detail = null)
    {
        message.Text = detail ?? "Não foi possível acessar o servidor do PrevAlerta.\nVerifique sua conexão e tente novamente.\nSe o problema continuar, entre em contato com o responsável pelo servidor.";
        failure.Visible = true;
        failure.BringToFront();
        LocalFiles.Log("Tela de indisponibilidade: " + (detail ?? "servidor inacessível"));
    }

    private async Task OpenServerAsync()
    {
        try
        {
            if (browser.CoreWebView2 is null)
            {
                var environment = await CoreWebView2Environment.CreateAsync(null, Path.Combine(LocalFiles.DirectoryPath, "WebView2"));
                await browser.EnsureCoreWebView2Async(environment);
                browser.CoreWebView2!.Settings.IsStatusBarEnabled = false;
                browser.CoreWebView2.Settings.IsBuiltInErrorPageEnabled = false;
                browser.CoreWebView2.NavigationStarting += (_, e) =>
                {
                    if (!Uri.TryCreate(e.Uri, UriKind.Absolute, out var uri) || !config.IsInternal(uri))
                    {
                        e.Cancel = true;
                        return;
                    }
                    navigationTimeout.Start();
                };
                browser.CoreWebView2.NewWindowRequested += (_, e) =>
                {
                    e.Handled = true;
                    if (Uri.TryCreate(e.Uri, UriKind.Absolute, out var uri) && config.IsInternal(uri))
                        browser.CoreWebView2.Navigate(uri.AbsoluteUri);
                };
                browser.CoreWebView2.NavigationCompleted += async (_, e) =>
                {
                    navigationTimeout.Stop();
                    if (!e.IsSuccess || e.HttpStatusCode >= 500) { ShowFailure(); return; }
                    failure.Visible = false;
                    LocalFiles.Log($"Navegação concluída: HTTP {e.HttpStatusCode}.");
                    await CheckSummaryAsync();
                };
                browser.CoreWebView2.ProcessFailed += (_, _) => ShowFailure("A janela do PrevAlerta foi interrompida. Feche e abra o aplicativo novamente.");
                timer.Start();
            }
            failure.Visible = false;
            browser.CoreWebView2.Navigate(config.ServerUri.AbsoluteUri);
        }
        catch (WebView2RuntimeNotFoundException)
        {
            ShowFailure("O Microsoft Edge WebView2 Runtime precisa ser instalado para abrir o PrevAlerta.\nSolicite a instalação ao suporte e abra o app novamente.");
        }
        catch (Exception error) { LocalFiles.Log(error.ToString()); ShowFailure(); }
    }

    private async Task CheckSummaryAsync()
    {
        if (checking || browser.CoreWebView2 is null || failure.Visible) return;
        checking = true;
        try
        {
            if (!Uri.TryCreate(browser.CoreWebView2.Source, UriKind.Absolute, out var current) || !config.IsInternal(current)) return;
            using var handler = new HttpClientHandler { AllowAutoRedirect = false };
            foreach (var cookie in await browser.CoreWebView2.CookieManager.GetCookiesAsync(config.SummaryUri.AbsoluteUri))
                handler.CookieContainer.Add(cookie.ToSystemNetCookie());
            using var client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(15) };
            client.DefaultRequestHeaders.CacheControl = new() { NoCache = true, NoStore = true };
            using var response = await client.GetAsync(config.SummaryUri);
            if (!response.IsSuccessStatusCode || response.Content.Headers.ContentType?.MediaType != "application/json")
            {
                LocalFiles.Log($"Resumo não disponível: {config.SummaryUri}; HTTP {(int)response.StatusCode}; autentique-se no app se necessário.");
                return;
            }
            using var json = JsonDocument.Parse(await response.Content.ReadAsStringAsync());
            var overdue = json.RootElement.GetProperty("overdue").GetInt32();
            var today = json.RootElement.GetProperty("today").GetInt32();
            if (overdue < 0 || today < 0) return;
            LocalFiles.Log($"Resumo HTTP atual: {config.SummaryUri}; overdue={overdue}, today={today}.");
            DesktopNotification.Deliver(config, overdue, today, forceNextSummary);
            forceNextSummary = false;
        }
        catch (Exception error) { LocalFiles.Log("Consulta de notificações: " + error.Message); }
        finally { checking = false; }
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing) { timer.Dispose(); navigationTimeout.Dispose(); }
        base.Dispose(disposing);
    }
}
