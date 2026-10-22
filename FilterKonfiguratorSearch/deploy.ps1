# Lädt Plugin-Dateien per FTP hoch: erst Assets (js/css/Bilder), dann Templates/PHP.
# So cached Cloudflare nie eine neue Template-Version mit alter Asset-Version.
# Zugangsdaten werden aus sifreftp.md gelesen (git-ignoriert), nie ausgegeben.
#
# Beispiel:
#   .\FilterKonfiguratorSearch\deploy.ps1 templates/js/womosi-konfigurator.js templates/filterkonfigurator_index.tpl
#   .\FilterKonfiguratorSearch\deploy.ps1 -ResetOpcache Bootstrap.php
param(
    [switch]$ResetOpcache,
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$Files
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$plugin = Split-Path $root -Leaf
$lines = [IO.File]::ReadAllLines((Join-Path $root 'sifreftp.md'), [Text.Encoding]::UTF8)

function Get-After([string]$label) {
    $i = [array]::IndexOf($lines, ($lines | Where-Object { $_.Trim() -eq $label } | Select-Object -First 1))
    if ($i -lt 0) { throw "sifreftp.md: '$label' nicht gefunden" }
    for ($j = $i + 1; $j -lt $lines.Count; $j++) { if ($lines[$j].Trim() -ne '') { return $lines[$j].Trim() } }
    throw "sifreftp.md: Wert zu '$label' fehlt"
}

$server = Get-After 'Servername'
$cred = (Get-After 'Benutzername/Kontoname') + ':' + (Get-After 'Passwort/Kennwort')
$base = "ftp://$server/partsell.de/shop"

if (-not $Files -or $Files.Count -eq 0) { throw 'Keine Dateien angegeben.' }

$assetExt = '.js', '.css', '.png', '.jpg', '.jpeg', '.webp', '.svg', '.gif'
$ordered = @($Files | Where-Object { $assetExt -contains [IO.Path]::GetExtension($_).ToLower() }) +
           @($Files | Where-Object { $assetExt -notcontains [IO.Path]::GetExtension($_).ToLower() })

foreach ($f in $ordered) {
    $local = Join-Path $root $f
    if (-not (Test-Path $local)) { throw "Datei fehlt: $f" }
    $url = "$base/plugins/$plugin/" + ($f -replace '\\', '/')
    curl.exe -s -S --ftp-create-dirs -u $cred -T $local $url
    if ($LASTEXITCODE -ne 0) { throw "Upload fehlgeschlagen: $f" }
    Write-Host "OK  $f"
}

if ($ResetOpcache) {
    $tmp = Join-Path $env:TEMP 'fk-opcache-reset.php'
    Set-Content -Encoding ascii $tmp '<?php echo var_export(function_exists("opcache_reset") ? opcache_reset() : "n/a", true);'
    curl.exe -s -S -u $cred -T $tmp "$base/fk-opcache-reset.php"
    Write-Host 'Opcache-Reset-Datei hochgeladen. Jetzt https://bremer-sitzbezuege.de/fk-opcache-reset.php aufrufen, danach Enter...'
    [void](Read-Host)
    curl.exe -s -S -u $cred "ftp://$server/" --quote 'DELE /partsell.de/shop/fk-opcache-reset.php' -o NUL
    Write-Host 'Opcache-Reset-Datei geloescht.'
}
