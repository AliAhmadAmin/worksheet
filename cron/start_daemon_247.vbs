' Launches sync_daemon_loop.php 100% hidden in background (no console window)
Set objShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
strParent = fso.GetParentFolderName(WScript.ScriptFullName)

' Terminate any previous instance to avoid duplicate daemons and ensure latest code is loaded
On Error Resume Next
Set objWMIService = GetObject("winmgmts:\\.\root\cimv2")
Set colProcesses = objWMIService.ExecQuery("SELECT ProcessId, CommandLine FROM Win32_Process WHERE CommandLine LIKE '%sync_daemon_loop.php%'")
For Each objProcess In colProcesses
    If objProcess.ProcessId <> 0 Then
        objProcess.Terminate()
    End If
Next
On Error GoTo 0

WScript.Sleep 500

phpExe = "C:\xampp\php\php-win.exe"
If Not fso.FileExists(phpExe) Then
    phpExe = "C:\xampp\php\php.exe"
End If

scriptPath = strParent & "\sync_daemon_loop.php"
objShell.Run """" & phpExe & """ """ & scriptPath & """", 0, False
