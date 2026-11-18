@echo off
title Restart Attendance 24/7 Daemon
echo ===================================================
echo   Restarting ZKTeco 24/7 Background Sync Daemon
echo ===================================================
echo.
echo Terminating any existing daemon process...
powershell -NoProfile -Command "try { Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*sync_daemon_loop.php*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force; Write-Host 'Stopped PID:' $_.ProcessId } } catch {}"
timeout /t 1 /nobreak >nul
echo.
echo Launching fresh daemon in background...
wscript "%~dp0start_daemon_247.vbs"
echo.
echo ===================================================
echo [OK] Daemon is now running 24/7 in background!
echo It will check \\10.0.0.2\ZKTeco\ATTBACKUP.MDB every 60 seconds.
echo ===================================================
timeout /t 4
