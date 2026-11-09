@echo off
:: Run this script as Administrator (right-click → "Run as administrator")
:: It registers a Windows Task Scheduler task that fires `php artisan schedule:run`
:: every minute — this is the standard way to run Laravel's scheduler on Windows.

set PHP_PATH=C:\Users\Welcome\php\php.exe
set PROJECT_DIR=C:\Users\Welcome\Desktop\Laundrycard
set LOG_FILE=C:\Users\Welcome\Desktop\Laundrycard\storage\logs\scheduler.log
set TASK_NAME=LaundryCard-Laravel-Scheduler

echo Registering scheduled task: %TASK_NAME%

schtasks /create /tn "%TASK_NAME%" ^
  /tr "\"%PHP_PATH%\" \"%PROJECT_DIR%\artisan\" schedule:run >> \"%LOG_FILE%\" 2>&1" ^
  /sc MINUTE /mo 1 ^
  /sd TODAY ^
  /st 00:00 ^
  /rl HIGHEST ^
  /ru "%USERNAME%" ^
  /f

if %ERRORLEVEL% == 0 (
    echo.
    echo [OK] Task registered. The Laravel scheduler will now run every minute.
    echo      GHL tokens will auto-refresh every 6 hours without any manual work.
    echo.
    echo To verify: schtasks /query /tn "%TASK_NAME%"
    echo To remove: schtasks /delete /tn "%TASK_NAME%" /f
) else (
    echo.
    echo [ERROR] Failed to register task. Make sure you ran this as Administrator.
)
pause

