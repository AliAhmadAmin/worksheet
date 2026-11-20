' Run ZKTeco MDB sync 100% silently in background with no window
On Error Resume Next
Set objShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
strParent = fso.GetParentFolderName(WScript.ScriptFullName)

' Method 1: Local HTTP trigger via MSXML (guarantees full Apache/PHP/COM permissions)
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
If Err.Number <> 0 Then
    Err.Clear
    Set http = CreateObject("MSXML2.ServerXMLHTTP")
End If

http.Open "GET", "http://localhost/Worksheet/api/zkteco_manage.php?action=trigger_auto_sync&force=1", False
http.setTimeouts 5000, 10000, 30000, 60000
http.Send

' Method 2: Fallback to curl / CLI PHP if HTTP object encountered an issue
If Err.Number <> 0 Or http.Status <> 200 Then
    Err.Clear
    phpExe = "C:\xampp\php\php-win.exe"
    If Not fso.FileExists(phpExe) Then
        phpExe = "C:\xampp\php\php.exe"
    End If
    scriptPath = strParent & "\sync_zkteco_mdb.php"
    objShell.Run """" & phpExe & """ """ & scriptPath & """ --force", 0, False
End If
