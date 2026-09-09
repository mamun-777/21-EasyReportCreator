# Build a deploy zip of website/ for STRATO (Plesk / IIS). Excludes runtime auth DB and uploads.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$website = Join-Path $root "website"
$outDir = Join-Path $root "dist"
$stamp = Get-Date -Format "yyyyMMdd-HHmm"
$zipPath = Join-Path $outDir "easyreportcreator-website-$stamp.zip"

New-Item -ItemType Directory -Force -Path $outDir | Out-Null
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$staging = Join-Path $outDir "website-staging"
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

# Copy site tree, then scrub runtime data
Copy-Item -Path (Join-Path $website "*") -Destination $staging -Recurse -Force

$data = Join-Path $staging "data"
New-Item -ItemType Directory -Force -Path (Join-Path $data "uploads") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $data "companies") | Out-Null

# Drop live secrets / uploaded projects from the package
Remove-Item (Join-Path $data "auth.sqlite") -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path (Join-Path $data "uploads") -Force -ErrorAction SilentlyContinue |
  Where-Object { $_.Name -notin @(".gitkeep", ".htaccess", "web.config") } |
  Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
if (Test-Path (Join-Path $data "companies")) {
  Get-ChildItem -Path (Join-Path $data "companies") -Force -ErrorAction SilentlyContinue |
    Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}
Get-ChildItem -Path $staging -Recurse -Include "*.dcf" -ErrorAction SilentlyContinue |
  Remove-Item -Force -ErrorAction SilentlyContinue

Compress-Archive -Path (Join-Path $staging "*") -DestinationPath $zipPath -Force
Remove-Item $staging -Recurse -Force

Write-Host "Deploy package: $zipPath"
Write-Host "In Plesk: upload/extract into the domain document root, enable PHP 8.x, set upload limits to 80M."
