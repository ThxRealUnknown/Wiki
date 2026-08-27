@echo off
REM Runs Worldbuilder using PHP's own web server.

setlocal
cd /d "%~dp0"

set "PORT=8080"
if not "%~1"=="" set "PORT=%~1"

call :findphp
if "%PHP%"=="" (
    echo.
    echo Could not find php.exe. Install PHP, or run:
    echo   ^<path to php.exe^> -S localhost:%PORT% -t public public\router.php
    echo.
    pause
    exit /b 1
)

echo Worldbuilder is at  http://localhost:%PORT%/
echo Close this window to stop it.
echo.

start "" "http://localhost:%PORT%/"
"%PHP%" -S localhost:%PORT% -t public public\router.php

exit /b 0

:findphp
set "PHP="
REM The copy that ships with the project, so nothing has to be installed.
if exist "%~dp0php\php.exe" set "PHP=%~dp0php\php.exe" & exit /b
where php.exe >nul 2>&1 && for /f "delims=" %%P in ('where php.exe') do set "PHP=%%P" & exit /b
exit /b
