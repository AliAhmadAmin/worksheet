@echo off
title Uninstall ZKTeco Sync Task
echo Removing ZKTeco_Worksheet_Sync from Windows Task Scheduler...
schtasks /delete /tn "ZKTeco_Worksheet_Sync" /f
echo Done.
pause
