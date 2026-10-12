@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0set_staff_password.ps1" -Username hassan.hassan
if errorlevel 1 echo Password reset did not complete. Read the message above.
echo.
pause
endlocal
