@echo off
REM Wipes the database and rebuilds it from bin\seed_data.php. Asks for
REM confirmation and writes a full backup first - see bin\reset_to_seed.php.

setlocal
cd /d "%~dp0"

call :findphp
if "%PHP%"=="" (
    echo.
    echo Could not find php.exe.
    echo Install PHP, or run:  ^<path to php.exe^> bin\reset_to_seed.php
    echo.
    pause
    exit /b 1
)

echo Using %PHP%
echo.
"%PHP%" bin\reset_to_seed.php %*

echo.
pause
exit /b 0

:findphp
set "PHP="
REM The copy that ships with the project, so nothing has to be installed.
if exist "%~dp0php\php.exe" set "PHP=%~dp0php\php.exe" & exit /b
where php.exe >nul 2>&1 && for /f "delims=" %%P in ('where php.exe') do set "PHP=%%P" & exit /b
exit /b
