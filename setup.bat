@echo off
REM Sets this copy up on the machine it is currently sitting on: creates the
REM SQLite database, applies migrations, and loads the newest backup\*.json.
REM Safe to run more than once.

setlocal
cd /d "%~dp0"

call :findphp
if "%PHP%"=="" (
    echo.
    echo Could not find php.exe.
    echo Install PHP, or run:  ^<path to php.exe^> bin\setup.php
    echo.
    pause
    exit /b 1
)

echo Using %PHP%
echo.
"%PHP%" bin\setup.php %*

echo.
pause
exit /b 0

:findphp
set "PHP="
REM The copy that ships with the project, so nothing has to be installed.
if exist "%~dp0php\php.exe" set "PHP=%~dp0php\php.exe" & exit /b
where php.exe >nul 2>&1 && for /f "delims=" %%P in ('where php.exe') do set "PHP=%%P" & exit /b
exit /b
