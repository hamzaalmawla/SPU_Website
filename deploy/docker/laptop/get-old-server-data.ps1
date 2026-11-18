# Download the old cPanel server's important data to this laptop, for the new VPS.
#
# Run from PowerShell in this repository (branch dev, after `git pull`):
#
#     powershell -ExecutionPolicy Bypass -File deploy\docker\laptop\get-old-server-data.ps1
#
# It asks for the cPanel API token (typing hidden, never saved), then the old
# server packs v2's .env, storage, runtime files, /forms, /Medicine, /spuh and
# all their databases (an export-only deploy: nothing on the site changes), this
# laptop downloads and checks every piece, and the copy on the old server is
# deleted again. The result is one folder, core-<date>-<time>, under
# %USERPROFILE%\spu-vps-data. Upload that whole folder to /srv/spu/xfer/ on the
# VPS and run migrate.sh with the access method "laptop".
#
# The folder holds the site's secrets (.env) and full database dumps: keep it
# private and delete it once it is on the VPS.
#
# Needs Python 3 (python.org or the Microsoft Store); only its standard library.

param(
    [string]$OutRoot = (Join-Path $env:USERPROFILE "spu-vps-data"),
    [string]$ApiHost = "spu.edu.sy",
    [int]$Port = 2083,
    [string]$User = "spuedu",
    [string]$Repo = "/home/spuedu/repositories/spu-v2",
    [string]$Branch = "dev",
    [switch]$WithoutApps,
    [int]$ChunkKb = 512
)

$ErrorActionPreference = "Stop"
$script = Join-Path $PSScriptRoot "..\scripts\cpanel-api.py"
if (-not (Test-Path $script)) { throw "cpanel-api.py not found next to this script. Run git pull on dev." }

$python = $null
foreach ($candidate in @("py", "python", "python3")) {
    if (Get-Command $candidate -ErrorAction SilentlyContinue) { $python = $candidate; break }
}
if (-not $python) { throw "Python 3 is not installed. Install it from python.org, then run this again." }
$pyArgs = @()
if ($python -eq "py") { $pyArgs = @("-3") }

$secure = Read-Host "cPanel API token for $User (typing is hidden)" -AsSecureString
$token = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
if (-not $token) { throw "No token given." }

$env:CPANEL_TOKEN = $token
$env:CPANEL_USER = $User
$env:CPANEL_HOST = $ApiHost
$env:CPANEL_PORT = "$Port"
$env:CPANEL_REPO = $Repo
$env:CPANEL_BRANCH = $Branch
$env:CPANEL_HOME = "/home/$User"
$env:CPANEL_CHUNK_KB = "$ChunkKb"
$env:PYTHONIOENCODING = "utf-8"

$items = @("env", "storage", "web", "db:spuedu_v2")
if (-not $WithoutApps) {
    $items += @("app:forms", "app:Medicine", "app:spuh",
                "db:spuedu_forms:forms", "db:spuedu_medical:Medicine", "db:spuedu_spuh:spuh")
}

try {
    Write-Host "`n== Checking the token and the old server"
    & $python @pyArgs -I $script check
    if ($LASTEXITCODE -ne 0) { throw "The cPanel API does not answer with this token from this laptop." }

    New-Item -ItemType Directory -Force -Path $OutRoot | Out-Null
    $out = Join-Path $OutRoot ("core-" + (Get-Date -Format "yyyyMMdd-HHmmss"))
    Write-Host "`n== Downloading to $out (a few minutes up to an hour)"
    & $python @pyArgs -I $script export $out @items
    if ($LASTEXITCODE -ne 0) { throw "The download stopped (see above). Run this script again." }

    Write-Host "`n== Deleting the packed copy on the old server"
    & $python @pyArgs -I $script clean
    if ($LASTEXITCODE -ne 0) { Write-Warning "Delete ~/vps-export on the old server in cPanel File Manager." }

    $size = (Get-ChildItem $out -Recurse -File | Measure-Object Length -Sum).Sum / 1MB
    Write-Host ("`nDone: {0} ({1:N0} MB)" -f $out, $size)
    Get-Content (Join-Path $out "INDEX") | ForEach-Object { Write-Host "   $_" }
    Write-Host "`nTomorrow, upload the whole folder to the VPS (replace VPS-IP):"
    Write-Host "   scp -r `"$out`" root@VPS-IP:/srv/spu/xfer/"
    Write-Host "then on the VPS:  sudo bash /srv/spu/src/deploy/docker/migrate.sh step 5   (answer: laptop)"
}
finally {
    Remove-Item Env:CPANEL_TOKEN -ErrorAction SilentlyContinue
    $token = $null
}
