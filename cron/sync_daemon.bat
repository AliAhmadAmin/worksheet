@echo off
title ZKTeco Biometric Background Sync Daemon
echo ========================================================
echo   ZKTeco Biometric Background Sync Daemon
echo ========================================================
echo Running sync every 60 seconds... Press Ctrl+C to stop.
echo.
:loop
"C:\xampp\php\php.exe" "C:\xampp\htdocs\Worksheet\cron\sync_zkteco_mdb.php"
timeout /t 60 /nobreak >nul
goto loop
