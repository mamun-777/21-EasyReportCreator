# EasyReportCreator - IIS + PHP setup on STRATO Windows VPS
# Does NOT modify the existing ShapeDevelopAPI site.
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$siteName = "EasyReportCreator"
$sitePath = "C:\inetpub\easyreportcreator"
$phpRoot = "C:\PHP"
$phpVersionZip = "https://windows.php.net/downloads/releases/php-8.3.33-nts-Win32-vs16-x64.zip"
$zipUrlFallback = "https://windows.php.net/downloads/releases/archives/php-8.3.33-nts-Win32-vs16-x64.zip"
$deployZip = "C:\temp\easyreportcreator-website.zip"
$log = "C:\temp\erc-setup.log"

New-Item -ItemType Directory -Force -Path C:\temp | Out-Null
function Write-Log([string]$m) {
  $line = "{0} {1}" -f (Get-Date -Format o), $m
  Add-Content -Path $log -Value $line
  Write-Output $line
}

Write-Log "=== EasyReportCreator setup start ==="

Write-Log "Installing Web-CGI..."
Install-WindowsFeature Web-CGI | Out-Null
Import-Module WebAdministration

if (-not (Test-Path "$phpRoot\php-cgi.exe")) {
  Write-Log "Downloading PHP..."
  $phpZip = "C:\temp\php.zip"
  try {
    Invoke-WebRequest -Uri $phpVersionZip -OutFile $phpZip -UseBasicParsing
  } catch {
    Write-Log "Primary PHP URL failed, trying archives..."
    Invoke-WebRequest -Uri $zipUrlFallback -OutFile $phpZip -UseBasicParsing
  }
  if (Test-Path $phpRoot) { Remove-Item $phpRoot -Recurse -Force }
  New-Item -ItemType Directory -Force -Path $phpRoot | Out-Null
  Expand-Archive -Path $phpZip -DestinationPath $phpRoot -Force
  Write-Log "PHP extracted to $phpRoot"
} else {
  Write-Log "PHP already present at $phpRoot"
}

$iniSrc = Join-Path $phpRoot "php.ini-production"
$iniDst = Join-Path $phpRoot "php.ini"
if (-not (Test-Path $iniDst)) {
  Copy-Item $iniSrc $iniDst -Force
}
$ini = Get-Content $iniDst -Raw
$ini = $ini -replace ';?\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
foreach ($ext in @("curl","fileinfo","gd","mbstring","openssl","pdo_sqlite","sqlite3","zip")) {
  if ($ini -match ("(?m)^;?\s*extension\s*=\s*{0}\s*$" -f [regex]::Escape($ext))) {
    $ini = [regex]::Replace($ini, ("(?m)^;?\s*extension\s*=\s*{0}\s*$" -f [regex]::Escape($ext)), "extension=$ext")
  } elseif ($ini -notmatch ("(?m)^extension\s*=\s*{0}\s*$" -f [regex]::Escape($ext))) {
    $ini += "`r`nextension=$ext"
  }
}
$ini = [regex]::Replace($ini, "(?m)^;?\s*upload_max_filesize\s*=.*$", "upload_max_filesize = 80M")
$ini = [regex]::Replace($ini, "(?m)^;?\s*post_max_size\s*=.*$", "post_max_size = 80M")
$ini = [regex]::Replace($ini, "(?m)^;?\s*max_execution_time\s*=.*$", "max_execution_time = 120")
$ini = [regex]::Replace($ini, "(?m)^;?\s*memory_limit\s*=.*$", "memory_limit = 256M")
$ini = [regex]::Replace($ini, "(?m)^;?\s*cgi\.fix_path_info\s*=.*$", "cgi.fix_path_info = 1")
$ini = [regex]::Replace($ini, "(?m)^;?\s*fastcgi\.impersonate\s*=.*$", "fastcgi.impersonate = 1")
Set-Content -Path $iniDst -Value $ini -Encoding ASCII
Write-Log "php.ini configured"

$machinePath = [Environment]::GetEnvironmentVariable("Path", "Machine")
if ($machinePath -notlike "*$phpRoot*") {
  [Environment]::SetEnvironmentVariable("Path", "$machinePath;$phpRoot", "Machine")
  $env:Path += ";$phpRoot"
}

$appcmd = "$env:windir\system32\inetsrv\appcmd.exe"
$cgiList = & $appcmd list config -section:system.webServer/fastCGI
if ($cgiList -notmatch [regex]::Escape("$phpRoot\php-cgi.exe")) {
  & $appcmd set config /section:system.webServer/fastCGI "/+[fullPath='$phpRoot\php-cgi.exe']"
  Write-Log "Registered FastCGI"
} else {
  Write-Log "FastCGI already registered"
}
$handlers = & $appcmd list config -section:system.webServer/handlers
if ($handlers -notmatch "PHP_via_FastCGI") {
  & $appcmd set config /section:system.webServer/handlers "/+[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='$phpRoot\php-cgi.exe',resourceType='Either',requireAccess='Script']"
  Write-Log "Registered PHP handler"
} else {
  Write-Log "PHP handler already registered"
}

& $appcmd set config -section:system.webServer/fastCGI "/[fullPath='$phpRoot\php-cgi.exe'].activityTimeout:300" | Out-Null
& $appcmd set config -section:system.webServer/fastCGI "/[fullPath='$phpRoot\php-cgi.exe'].requestTimeout:300" | Out-Null
& $appcmd set config -section:system.webServer/fastCGI "/[fullPath='$phpRoot\php-cgi.exe'].instanceMaxRequests:10000" | Out-Null

New-Item -ItemType Directory -Force -Path $sitePath | Out-Null
if (-not (Test-Path $deployZip)) {
  throw "Deploy zip missing: $deployZip - upload it first."
}
Write-Log "Extracting deploy zip..."
Get-ChildItem $sitePath -Force | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Expand-Archive -Path $deployZip -DestinationPath $sitePath -Force

foreach ($d in @("$sitePath\data", "$sitePath\data\uploads", "$sitePath\data\companies")) {
  New-Item -ItemType Directory -Force -Path $d | Out-Null
}

if (-not (Test-Path "IIS:\AppPools\$siteName")) {
  New-WebAppPool -Name $siteName | Out-Null
}
Set-ItemProperty "IIS:\AppPools\$siteName" -Name managedRuntimeVersion -Value ""
Set-ItemProperty "IIS:\AppPools\$siteName" -Name enable32BitAppOnWin64 -Value $false

icacls "$sitePath\data" /grant "NT AUTHORITY\IUSR:(OI)(CI)M" /T | Out-Null
icacls "$sitePath\data" /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null
icacls "$sitePath\data" /grant "IIS AppPool\${siteName}:(OI)(CI)M" /T | Out-Null

# Anonymous auth as app-pool identity (avoids IUSR-only ACL surprises)
& $appcmd set config $siteName -section:system.webServer/security/authentication/anonymousAuthentication /userName:"" /password:"" /commit:apphost | Out-Null

if (Get-Website -Name $siteName -ErrorAction SilentlyContinue) {
  Remove-Website -Name $siteName
}
New-Website -Name $siteName -Port 8080 -PhysicalPath $sitePath -ApplicationPool $siteName | Out-Null
try {
  New-WebBinding -Name $siteName -Protocol http -Port 80 -HostHeader "easyreportcreator.com" -ErrorAction Stop
  New-WebBinding -Name $siteName -Protocol http -Port 80 -HostHeader "www.easyreportcreator.com" -ErrorAction Stop
  Write-Log "Added host bindings for easyreportcreator.com"
} catch {
  Write-Log ("Host binding note: " + $_.Exception.Message)
}

Restart-WebAppPool -Name $siteName
$phpVer = & "$phpRoot\php.exe" -v 2>&1 | Select-Object -First 1
Write-Log ("PHP version: " + $phpVer)
Write-Log "=== Setup complete ==="
Write-Log "Test URL: http://217.154.240.82:8080/"
Get-Content $log -Tail 40
