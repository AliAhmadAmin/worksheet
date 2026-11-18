' Launches sync_daemon_loop.php 100% hidden in background (no console window)
Set objShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
strParent = fso.GetParentFolderName(WScript.ScriptFullName)

phpExe = "C:\xampp\php\php-win.exe"
If Not fso.FileExists(phpExe) Then
    phpExe = "C:\xampp\php\php.exe"
End If

scriptPath = strParent & "\sync_daemon_loop.php"
objShell.Run """" & phpExe & """ """ & scriptPath & """", 0, False
