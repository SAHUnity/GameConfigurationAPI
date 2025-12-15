<#
.SYNOPSIS
    Stress tests the Game Configuration API.
.DESCRIPTION
    Sends 100 requests (or custom count) to the API to measure performance/throughput.
.EXAMPLE
    .\stress_test.ps1 -Url "http://localhost/api/v1" -Key "your_api_key"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$Url,

    [Parameter(Mandatory=$true)]
    [string]$Key,

    [int]$Count = 100
)

Write-Host "Starting Stress Test: $Count requests to $Url" -ForegroundColor Cyan

$success = 0
$fail = 0
$headers = @{ "X-API-KEY" = $Key }

$timer = [System.Diagnostics.Stopwatch]::StartNew()

1..$Count | ForEach-Object {
    try {
        $response = Invoke-WebRequest -Uri $Url -Headers $headers -Method Get -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            $success++
        } else {
            $fail++
        }
    } catch {
        # Check if it's a 429
        if ($_.Exception.Response.StatusCode -eq 429) {
            Write-Host "Rate Limit Hit (429) at request $_" -ForegroundColor Yellow
        } else {
            Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
        }
        $fail++
    }
}

$timer.Stop()
$totalSeconds = $timer.Elapsed.TotalSeconds
$rps = if ($totalSeconds -gt 0) { $success / $totalSeconds } else { 0 }

Write-Host "`nTest Completed." -ForegroundColor Green
Write-Host "Total Time: $($totalSeconds.ToString('N2')) seconds"
Write-Host "Successful: $success"
Write-Host "Failed:     $fail"
Write-Host "RPS (Est):  $($rps.ToString('N2')) requests/sec"
