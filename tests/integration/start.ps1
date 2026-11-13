# Start only the isolated synthetic project declared beside this script.
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
Set-Location -LiteralPath $projectRoot
$composeFile = Join-Path $PSScriptRoot 'compose.yaml'
$toolsDirectory = Join-Path $projectRoot '.dev-tools\integration'
New-Item -ItemType Directory -Path $toolsDirectory -Force | Out-Null

function Invoke-LocalWp {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$WpArguments)
    & docker compose -f $composeFile run --rm cli wp @WpArguments
    if ($LASTEXITCODE -ne 0) { throw "Isolated WP command failed: $($WpArguments -join ' ')" }
}

$woocommerceDirectory = Join-Path $toolsDirectory 'woocommerce'
if (-not (Test-Path -LiteralPath (Join-Path $woocommerceDirectory 'woocommerce.php'))) {
    $archivePath = Join-Path $toolsDirectory 'woocommerce.zip'
    Invoke-WebRequest -Uri 'https://downloads.wordpress.org/plugin/woocommerce.11.3.0.zip' -OutFile $archivePath -TimeoutSec 120
    if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash -ne 'D9EB1E46DF8309EB37B6F7F3FBA51480F2A9A00E7FEB10B1487592FDE39ACCC6') { throw 'WooCommerce archive checksum mismatch' }
    Expand-Archive -LiteralPath $archivePath -DestinationPath $toolsDirectory -Force
}
& docker compose -f $composeFile up -d db wordpress
if ($LASTEXITCODE -ne 0) { throw 'Sandbox startup failed' }
# A dedicated Linux volume avoids slow Windows filesystem traversal in WooCommerce.
& docker cp "$woocommerceDirectory/." 'adwc-stage2-local-wordpress-1:/var/www/html/wp-content/plugins/woocommerce'
if ($LASTEXITCODE -ne 0) { throw 'Sandbox WooCommerce copy failed' }
& docker compose -f $composeFile run --rm cli wp core is-installed 2>$null
if ($LASTEXITCODE -ne 0) {
    Invoke-LocalWp core install --url=http://127.0.0.1:38971 --title='ADWC isolated synthetic store' --admin_user=adwc_admin --admin_password=adwc-local-review-only --admin_email=admin@example.test --skip-email
}
Invoke-LocalWp plugin activate advanced-dashboard-for-woocommerce woocommerce
Write-Output 'Local sandbox: http://127.0.0.1:38971/wp-admin/index.php'
Write-Output 'Synthetic login: adwc_admin / adwc-local-review-only'
Write-Output 'No fixture orders are created by this startup script. See docs/stage-2.md for the guarded test commands.'
