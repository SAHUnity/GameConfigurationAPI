<#
.SYNOPSIS
    Generates a bcrypt hash for an admin password.
.DESCRIPTION
    Prompts for a password and outputs the SQL query to insert/update the user.
#>

param()

$password = Read-Host "Enter the new Admin Password" -AsSecureString
$plainPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))

if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    Write-Error "Password cannot be empty."
    exit
}

# PHP's default cost for BCRYPT is 10.
# Since we don't have native Create-Bcrypt in PS easily without .NET libs that might not be present,
# We will use a simple PHP script one-liner to generate it if PHP is in path, 
# OR warn usage.

if (Get-Command php -ErrorAction SilentlyContinue) {
    # Escape quotes
    $safePass = $plainPassword.Replace("'", "'\''")
    $hash = php -r "echo password_hash('$safePass', PASSWORD_BCRYPT);"
    
    Write-Host "`n--- Credentials Generated ---" -ForegroundColor Green
    Write-Host "Password Hash: $hash"
    Write-Host "`nSQL to Update Admin:" -ForegroundColor Yellow
    Write-Host "UPDATE users SET password_hash = '$hash' WHERE username = 'admin';"
    Write-Host "`nSQL to Create Admin:" -ForegroundColor Yellow
    Write-Host "INSERT INTO users (username, password_hash) VALUES ('admin', '$hash');"
} else {
    Write-Error "PHP is not in the system PATH. Cannot generate hash reliably entirely in PowerShell without deps."
    Write-Hote "Please run this command on your server:"
    Write-Host "php -r ""echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT);"""
}
