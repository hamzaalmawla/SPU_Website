@echo off
rem Double-click to download the old cPanel server's data for the new VPS.
rem Runs get-old-server-data.ps1 next to this file (see that file for details).
rem The result is a folder in %USERPROFILE%\spu-vps-data\core-<date>-<time>.
setlocal
title SPU - download the old server's data
cd /d "%~dp0"

where py >nul 2>nul || where python >nul 2>nul || (
    echo.
    echo Python 3 is not installed.
    echo Install it from https://www.python.org/downloads/ ^(tick "Add python.exe to PATH"^),
    echo then double-click this file again.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0get-old-server-data.ps1"
set RC=%ERRORLEVEL%

echo.
if %RC%==0 (
    echo Finished. The folder is in %USERPROFILE%\spu-vps-data
    start "" explorer "%USERPROFILE%\spu-vps-data"
) else (
    echo Something went wrong ^(see the messages above^). Double-click this file again to retry.
)
echo.
pause
exit /b %RC%
