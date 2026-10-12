param(
    [string]$Username = 'hassan.hassan',
    [string]$FullName = 'Hassan Abdallah Hassan',
    [string]$Mysql = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$Php = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$backendPath = Split-Path -Parent $PSScriptRoot
$migration = Join-Path $backendPath 'database\migrations\004_staff_usernames.sql'
$createStaff = Join-Path $PSScriptRoot 'create_staff.ps1'
$generateKey = Join-Path $PSScriptRoot 'generate_app_key.php'

if (-not (Test-Path -LiteralPath $Mysql) -or -not (Test-Path -LiteralPath $Php)) {
    throw 'XAMPP MySQL/PHP executables were not found. Update -Mysql and -Php paths.'
}

$columnCount = & $Mysql -u root --connect-timeout=3 -N jassnet_billing -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='jassnet_billing' AND TABLE_NAME='users' AND COLUMN_NAME='username'"
if ($LASTEXITCODE -ne 0) { throw 'Cannot access jassnet_billing with local XAMPP root. Check that MariaDB is running.' }
if ([int]($columnCount | Select-Object -Last 1) -eq 0) {
    Get-Content -Raw -LiteralPath $migration | & $Mysql -u root --connect-timeout=3 jassnet_billing
    if ($LASTEXITCODE -ne 0) { throw 'Migration 004 failed. Stop and inspect the DB before retrying.' }
}

$keySet = Select-String -LiteralPath (Join-Path $backendPath '.env') -Pattern '^APP_KEY=.{32,}$' -Quiet
if (-not $keySet) {
    & $Php $generateKey
    if ($LASTEXITCODE -ne 0) { throw 'Could not generate APP_KEY; do not continue.' }
}

$existingStatus = & $Mysql -u root --connect-timeout=3 -N jassnet_billing -e "SELECT status FROM users WHERE username='$Username' LIMIT 1"
if ($LASTEXITCODE -ne 0) { throw 'Could not check whether the staff username already exists.' }
if ($existingStatus) {
    if (($existingStatus | Select-Object -Last 1).ToString().Trim() -ne 'locked') {
        throw 'This staff username already exists and is active; refusing to reset or overwrite it.'
    }
    & (Join-Path $PSScriptRoot 'set_staff_password.ps1') -Username $Username -Php $Php
} else {
    & $createStaff -Username $Username -FullName $FullName -Role 'Super Admin' -Php $Php
}
if ($LASTEXITCODE -ne 0) { throw 'Super Admin account setup failed.' }
