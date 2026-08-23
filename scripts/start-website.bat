@echo off
set PORT=%1
if "%PORT%"=="" set PORT=8080
echo EasyReportCreator public site — http://127.0.0.1:%PORT%/
echo Requires PHP on PATH (STRATO uses PHP 8).
cd /d "%~dp0..\website"
php -S 127.0.0.1:%PORT%
