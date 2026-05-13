# Hotspot auto-start Windows 11 - v5
# Run as administrator

Start-Service -Name "icssvc" -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

Add-Type -AssemblyName System.Runtime.WindowsRuntime

[Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager,Windows.Networking.NetworkOperators,ContentType=WindowsRuntime] | Out-Null
[Windows.Networking.Connectivity.NetworkInformation,Windows.Networking.Connectivity,ContentType=WindowsRuntime] | Out-Null
[Windows.Networking.NetworkOperators.NetworkOperatorTetheringOperationResult,Windows.Networking.NetworkOperators,ContentType=WindowsRuntime] | Out-Null

function AwaitOperation($WinRtTask) {
    $resultType = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringOperationResult]
    $asTaskMethod = [System.WindowsRuntimeSystemExtensions].GetMethods() |
        Where-Object {
            $_.Name -eq "AsTask" -and
            $_.IsGenericMethodDefinition -and
            $_.GetParameters().Count -eq 1
        } | Select-Object -First 1
    $genericMethod = $asTaskMethod.MakeGenericMethod($resultType)
    $netTask = $genericMethod.Invoke($null, @($WinRtTask))
    $netTask.Wait(-1) | Out-Null
    return $netTask.Result
}

$profile = [Windows.Networking.Connectivity.NetworkInformation]::GetInternetConnectionProfile()

if ($profile -eq $null) {
    Write-Host "ERROR: No internet connection found." -ForegroundColor Red
    exit 1
}

$manager = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager]::CreateFromConnectionProfile($profile)

$state = $manager.TetheringOperationalState
Write-Host "Hotspot state: $state"

if ($state -ne "On") {
    Write-Host "Starting hotspot..." -ForegroundColor Cyan
    $result = AwaitOperation ($manager.StartTetheringAsync())
    if ($result.Status -eq "Success") {
        Write-Host "Hotspot started successfully!" -ForegroundColor Green
    } else {
        Write-Host "Failed - Status: $($result.Status)" -ForegroundColor Red
        Write-Host "Detail: $($result.AdditionalErrorMessage)" -ForegroundColor Red
    }
} else {
    Write-Host "Hotspot already active." -ForegroundColor Green
}