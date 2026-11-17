' Runs sync_daemon.bat completely hidden in the background (no window)
Set objShell = CreateObject("Wscript.Shell")
strPath = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
objShell.Run """" & strPath & "\sync_daemon.bat""", 0, False
