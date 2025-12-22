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
Write-Host "=" * 80 -ForegroundColor Cyan
Write-Host ""

$success = 0
$fail = 0
$headers = @{ "X-API-KEY" = $Key }
$totalTimer = [System.Diagnostics.Stopwatch]::StartNew()

1..$Count | ForEach-Object {
    $requestNum = $_
    $requestTimer = [System.Diagnostics.Stopwatch]::StartNew()
    
    try {
        $response = Invoke-WebRequest -Uri $Url -Headers $headers -Method Get -ErrorAction Stop
        $requestTimer.Stop()
        $delay = $requestTimer.ElapsedMilliseconds
        
        if ($response.StatusCode -eq 200) {
            $success++
            Write-Host "[Request $requestNum] " -NoNewline -ForegroundColor Gray
            Write-Host "SUCCESS" -NoNewline -ForegroundColor Green
            Write-Host " | Status: $($response.StatusCode) | Time: ${delay}ms" -ForegroundColor Gray
        } else {
            $fail++
            Write-Host "[Request $requestNum] " -NoNewline -ForegroundColor Gray
            Write-Host "WARNING" -NoNewline -ForegroundColor Yellow
            Write-Host " | Status: $($response.StatusCode) | Time: ${delay}ms" -ForegroundColor Gray
        }
    } catch {
        $requestTimer.Stop()
        $delay = $requestTimer.ElapsedMilliseconds
        $fail++
        
        # Check if it's a 429
        if ($_.Exception.Response.StatusCode -eq 429) {
            Write-Host "[Request $requestNum] " -NoNewline -ForegroundColor Gray
            Write-Host "RATE LIMIT" -NoNewline -ForegroundColor Yellow
            Write-Host " | Status: 429 | Time: ${delay}ms" -ForegroundColor Gray
        } else {
            $statusCode = if ($_.Exception.Response) { $_.Exception.Response.StatusCode.Value__ } else { "N/A" }
            Write-Host "[Request $requestNum] " -NoNewline -ForegroundColor Gray
            Write-Host "FAILED" -NoNewline -ForegroundColor Red
            Write-Host " | Status: $statusCode | Time: ${delay}ms" -ForegroundColor Gray
            Write-Host "    Error: $($_.Exception.Message)" -ForegroundColor DarkRed
        }
    }
}

$totalTimer.Stop()
$totalSeconds = $totalTimer.Elapsed.TotalSeconds
$rps = if ($totalSeconds -gt 0) { $success / $totalSeconds } else { 0 }
$avgDelay = if ($success -gt 0) { ($totalSeconds * 1000) / $Count } else { 0 }

Write-Host ""
Write-Host "=" * 80 -ForegroundColor Cyan
Write-Host "`nTest Completed." -ForegroundColor Green
Write-Host "=" * 80 -ForegroundColor Green
Write-Host "Total Time:     $($totalSeconds.ToString('N2')) seconds"
Write-Host "Successful:     $success"
Write-Host "Failed:         $fail"
Write-Host "Success Rate:   $(if ($Count -gt 0) { (($success / $Count) * 100).ToString('N2') } else { '0' })%"
Write-Host "Avg Delay:      $($avgDelay.ToString('N2')) ms"
Write-Host "RPS (Est):      $($rps.ToString('N2')) requests/sec"
Write-Host "=" * 80 -ForegroundColor Green

# Keep window open indefinitely
Write-Host "`n[Window will remain open - close manually when done]" -ForegroundColor Cyan
pause
