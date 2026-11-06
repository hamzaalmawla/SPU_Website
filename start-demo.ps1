param(
    [ValidateRange(1024, 65535)]
    [int] $Port = 8000,

    [switch] $CheckOnly
)

$ErrorActionPreference = 'Stop'
$env:APP_ENV = 'staging'
$env:APP_DEBUG = 'false'

Set-Location -LiteralPath $PSScriptRoot

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    throw 'Could not clear Laravel caches.'
}

php artisan launch:validate
if ($LASTEXITCODE -ne 0) {
    throw 'Launch validation failed. Resolve the reported failure before the meeting.'
}

if (-not (Test-Path -LiteralPath 'public/build/manifest.json')) {
    throw 'Production frontend assets are missing. Run npm run build.'
}

Write-Host ''
Write-Host "Arabic website: http://127.0.0.1:$Port/ar"
Write-Host "English website: http://127.0.0.1:$Port/en"
Write-Host "Admin login:    http://127.0.0.1:$Port/admin/login"
Write-Host ''

if ($CheckOnly) {
    Write-Host 'Demo preflight completed. The server was not started.'
    exit 0
}

Write-Host 'Press Ctrl+C to stop the demo server.'
Write-Host ''

php artisan serve --host=127.0.0.1 --port=$Port
