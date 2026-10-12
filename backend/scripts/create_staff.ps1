param(
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-z][a-z0-9_-]{0,39}\.[a-z][a-z0-9_-]{0,39}$')][string]$Username,
    [Parameter(Mandatory = $true)][ValidatePattern("^[\p{L} .'-]{2,160}$")][string]$FullName,
    [Parameter(Mandatory = $true)][ValidateSet('Super Admin', 'Admin', 'Technician', 'Accountant', 'Customer Service', 'Agent')][string]$Role,
    [string]$Php = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

$scriptPath = Join-Path $PSScriptRoot 'create_staff.php'
if (-not (Test-Path -LiteralPath $Php) -or -not (Test-Path -LiteralPath $scriptPath)) {
    throw 'PHP or the staff setup script was not found.'
}
$securePassword = Read-Host 'Set staff password (minimum 14 characters)' -AsSecureString
$pointer = [IntPtr]::Zero
$plainPassword = $null
$process = $null
try {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if ($plainPassword.Length -lt 14 -or $plainPassword.Length -gt 1024) { throw 'Password must be at least 14 characters and no more than 1024 characters.' }

    $start = New-Object System.Diagnostics.ProcessStartInfo
    $start.FileName = $Php
    $start.Arguments = '"' + $scriptPath + '" "' + $Username + '" "' + $FullName + '" "' + $Role + '"'
    $start.UseShellExecute = $false
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.CreateNoWindow = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $start
    [void]$process.Start()
    $process.StandardInput.WriteLine($plainPassword)
    $process.StandardInput.Close()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($stdout) { Write-Output $stdout.TrimEnd() }
    if ($process.ExitCode -ne 0) { throw ($stderr.Trim() + ' Staff setup failed.') }
} finally {
    if ($process) { $process.Dispose() }
    if ($pointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    if ($securePassword) { $securePassword.Dispose() }
    $plainPassword = $null
}
