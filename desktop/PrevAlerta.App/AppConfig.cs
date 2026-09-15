using System.Text.Json;

namespace PrevAlerta.App;

internal sealed class AppConfig
{
    public required Uri ServerUri { get; init; }
    public Uri SummaryUri => new(ServerUri, "desktop/alert-summary");

    public static AppConfig Load()
    {
        var path = Path.Combine(AppContext.BaseDirectory, "config.json");
        using var json = JsonDocument.Parse(File.ReadAllText(path));
        var value = json.RootElement.GetProperty("serverUrl").GetString();
        if (!Uri.TryCreate(value, UriKind.Absolute, out var uri) ||
            (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps) ||
            !string.IsNullOrEmpty(uri.UserInfo) || !string.IsNullOrEmpty(uri.Query) || !string.IsNullOrEmpty(uri.Fragment))
            throw new InvalidDataException("Configure serverUrl em config.json com a URL HTTP ou HTTPS do servidor, sem senha, consulta ou fragmento.");

        return new AppConfig { ServerUri = new Uri(uri.AbsoluteUri.TrimEnd('/') + "/") };
    }

    public bool IsInternal(Uri uri) => uri.Scheme == ServerUri.Scheme &&
        uri.Host.Equals(ServerUri.Host, StringComparison.OrdinalIgnoreCase) && uri.Port == ServerUri.Port;
}
