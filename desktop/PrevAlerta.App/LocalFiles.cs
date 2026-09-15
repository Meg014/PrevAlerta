namespace PrevAlerta.App;

internal static class LocalFiles
{
    public static string DirectoryPath { get; } = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "PrevAlerta.Desktop");

    public static void Log(string message)
    {
        try
        {
            Directory.CreateDirectory(DirectoryPath);
            File.AppendAllText(Path.Combine(DirectoryPath, "app.log"), $"{DateTimeOffset.Now:O} {message}{Environment.NewLine}");
        }
        catch (IOException) { }
        catch (UnauthorizedAccessException) { }
    }
}
