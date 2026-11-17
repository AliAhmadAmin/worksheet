@echo off
title Remove ZKTeco Sync Task
echo Removing old ZKTeco_Worksheet_Sync task...
schtasks /delete /tn "ZKTeco_Worksheet_Sync" /f
echo.
echo Task removed. To reinstall with the 100% silent windowless runner, run install_task.bat.
pause
