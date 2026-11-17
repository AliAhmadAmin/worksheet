@echo off
title Install ZKTeco 24/7 Sync Task
echo ========================================================
echo   ZKTeco Biometric Auto-Sync: Windows Task Scheduler Setup
echo ========================================================
echo.
echo This registers 'ZKTeco_Worksheet_Sync' to run every 1 minute 24/7,
echo even when all browser tabs and computers are closed.
echo.

schtasks /create /tn "ZKTeco_Worksheet_Sync" /tr "wscript.exe \"C:\xampp\htdocs\Worksheet\cron\sync_silent.vbs\"" /sc minute /mo 1 /f

if %errorlevel% equ 0 (
    echo.
    echo ========================================================
    echo  [SUCCESS] Task created successfully!
    echo  ZKTeco biometric punches will now sync 24/7 automatically.
    echo ========================================================
) else (
    echo.
    echo [ERROR] Failed to create scheduled task.
    echo Please right-click this file and choose 'Run as administrator'.
)
echo.
pause
