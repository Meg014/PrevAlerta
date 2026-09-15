# Manual helper. Production notifications are delivered by the Windows app.
function Show-PrevAgendaNotification {
    param(
        [Parameter(Mandatory = $true)][ValidateRange(0, 2147483647)][int]$Overdue,
        [Parameter(Mandatory = $true)][ValidateRange(0, 2147483647)][int]$Today,
        [switch]$Test
    )
    $total = [long]$Overdue + $Today
    if ($total -eq 0) { return 'empty' }

    $null = [Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime]
    $null = [Windows.Data.Xml.Dom.XmlDocument, Windows.Data.Xml.Dom.XmlDocument, ContentType = WindowsRuntime]
    $notifier = [Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier('PrevAlerta.Desktop')
    # Show respects Windows notification preferences. Querying Setting before the
    # first delivery can fail with E_NOTFOUND even for an installed Start menu identity.

    # ASCII source remains safe in Windows PowerShell 5.1 regardless of the editor's encoding.
    $attention = 'aten' + [char]0x00e7 + [char]0x00e3 + 'o'
    $headline = if ($total -eq 1) { "1 checklist precisa de $attention." } else { "$total checklists precisam de $attention." }
    $late = if ($Overdue -eq 1) { '1 atrasado.' } else { "$Overdue atrasados." }
    $due = if ($Today -eq 1) { '1 vence hoje.' } else { "$Today vencem hoje." }
    $title = if ($Test) { 'PrevAlerta - TESTE (dados ficticios)' } else { 'PrevAlerta' }
    $xml = New-Object Windows.Data.Xml.Dom.XmlDocument
    $xml.LoadXml(@"
<toast activationType="protocol" launch="prevalerta://open">
  <visual><binding template="ToastGeneric"><text>$title</text><text>$headline</text><text>$late $due</text></binding></visual>
  <actions><action content="Abrir PrevAlerta" activationType="protocol" arguments="prevalerta://open" /></actions>
</toast>
"@)
    $toast = [Windows.UI.Notifications.ToastNotification]::new($xml)
    $toast.Tag = if ($Test) { 'test' } else { 'startup' }
    $toast.Group = 'logon'
    $toast.ExpirationTime = [DateTimeOffset]::Now.AddDays(1)
    $notifier.Show($toast)
    return 'sent'
}
