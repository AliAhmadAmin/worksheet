' Run ZKTeco MDB sync 100% silently in background with no window
Set objShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
strParent = fso.GetParentFolderName(WScript.ScriptFullName)

phpExe = "C:\xampp\php\php-win.exe"
If Not fso.FileExists(phpExe) Then
    phpExe = "C:\xampp\php\php.exe"
End If

scriptPath = strParent & "\sync_zkteco_mdb.php"
objShell.Run """" & phpExe & """ """ & scriptPath & """", 0, False
