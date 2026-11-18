@echo off
title Install ZKTeco 24/7 Background Sync
echo ========================================================
echo   ZKTeco 24/7 Background Sync Setup (Unattended)
echo ========================================================
echo.
echo Installing 24/7 background sync daemon to Windows Startup...
echo This ensures biometric punches sync every 60 seconds automatically,
echo even when all browser tabs are closed.
echo.

set "STARTUP_DIR=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "TARGET_VBS=%STARTUP_DIR%\ZKTeco_247_Sync.vbs"
set "SOURCE_DIR=%~dp0"

echo Set objShell = CreateObject("WScript.Shell") > "%TARGET_VBS%"
echo objShell.Run "wscript.exe ""%SOURCE_DIR%start_daemon_247.vbs""", 0, False >> "%TARGET_VBS%"

echo Starting the 24/7 background sync daemon now...
wscript.exe "%SOURCE_DIR%start_daemon_247.vbs"

echo.
echo ========================================================
echo  [SUCCESS] 24/7 Background Sync is now running and installed!
echo  It will start automatically every time this computer boots.
echo  No browser windows are required.
echo ========================================================
echo.
pause
