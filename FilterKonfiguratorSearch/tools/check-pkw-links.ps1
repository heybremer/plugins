# Opens random PKW Marke/Modell/Baujahr combinations via /fk-search and reports series links that show no products.
# Usage: powershell -File tools\check-pkw-links.ps1 [-Samples 15] [-Base https://bremer-sitzbezuege.de]
param(
    [int]$Samples = 15,
    [string]$Base = 'https://bremer-sitzbezuege.de'
)

$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36'
$api = "$Base/fk-search"

function Get-Json([string]$url) {
    $raw = curl.exe -s -A $ua $url | Out-String
    if (-not $raw.Trim()) { return @() }
    try { return @($raw | ConvertFrom-Json) } catch { return @() }
}

function Enc([string]$s) { [uri]::EscapeDataString($s) }

$brands = (Get-Json "$api`?mode=options&field=hersteller&segment=pkw").value | Where-Object { $_ }
if (-not $brands) { Write-Error 'No brands returned'; exit 1 }

$checked = 0; $empty = @(); $noSeries = @()
for ($i = 0; $i -lt $Samples; $i++) {
    $h = Get-Random -InputObject $brands
    $models = (Get-Json "$api`?mode=options&field=modell&segment=pkw&hersteller=$(Enc $h)").value | Where-Object { $_ }
    if (-not $models) { continue }
    $m = Get-Random -InputObject $models
    $years = (Get-Json "$api`?mode=options&field=baujahr&segment=pkw&hersteller=$(Enc $h)&modell=$(Enc $m)").value | Where-Object { $_ }
    if (-not $years) { $noSeries += "$h / $m (kein Baujahr)"; continue }
    $y = Get-Random -InputObject $years
    $series = Get-Json "$api`?segment=pkw&hersteller=$(Enc $h)&modell=$(Enc $m)&baujahr=$(Enc $y)"
    if (-not $series) { $noSeries += "$h / $m / $y"; continue }
    $s = Get-Random -InputObject $series
    $page = curl.exe -s -L -A $ua $s.url | Out-String
    $isProduct = $page -match 'itemprop="sku"|id="buy_form"'
    $items = [regex]::Matches($page, 'productbox-inner|product-wrapper').Count
    $checked++
    $state = if ($isProduct) { 'Artikel' } elseif ($items -gt 0) { "Liste($items)" } else { 'LEER' }
    '{0,-12} {1,-22} {2,-5} {3,-6} {4}' -f $h, $m, $y, $s.serie, $state
    if ($state -eq 'LEER') { $empty += "$h / $m / $y / $($s.serie) -> $($s.url)" }
}

""
"Geprüft: $checked   Leer: $($empty.Count)   Ohne Serien: $($noSeries.Count)"
if ($empty)    { "`nLeere Serien-Links:";    $empty    | ForEach-Object { "  $_" } }
if ($noSeries) { "`nKombinationen ohne Serien:"; $noSeries | ForEach-Object { "  $_" } }
