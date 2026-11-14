<#
.SYNOPSIS
    Runs a test command with a time limit and kills its whole process tree if it hangs (D-100).

.DESCRIPTION
    The Pest browser suite sometimes hangs on Windows while Playwright starts (specs/backlog.md).
    This script runs the command, streams its output, and returns its exit code. If the command
    runs past the limit, it kills the full process tree (artisan test, pest, playwright run-server,
    ...), checks that none of those processes is left, and exits with code 124.

.EXAMPLE
    pwsh -NoProfile -File scripts/run-tests.ps1 -Command "composer ci:check" -TimeoutSeconds 300

.EXAMPLE
    pwsh -NoProfile -File scripts/run-tests.ps1 -Command "php artisan test --compact tests/Feature/LocaleTest.php" -TimeoutSeconds 120
#>
param(
    [Parameter(Mandatory)]
    [string] $Command,

    [Parameter(Mandatory)]
    [ValidateRange(1, 86400)]
    [int] $TimeoutSeconds
)

$ErrorActionPreference = 'Stop'

if (-not $IsWindows) {
    [Console]::Error.WriteLine('run-tests.ps1 only supports Windows; run the command directly instead.')
    exit 2
}

# Processes that make up a test run. Used to report leftovers after a timeout.
$testProcessPattern = 'artisan"?\s+test|[\\/]pest(\.bat)?"?(\s|$)|vendor[\\/]bin[\\/]pest|playwright.*run-server'

function Get-ProcessTree([int] $RootId) {
    $all = Get-CimInstance Win32_Process | Select-Object ProcessId, ParentProcessId, CreationDate, Name, CommandLine
    $tree = @()
    $queue = [System.Collections.Generic.Queue[int]]::new()
    $queue.Enqueue($RootId)

    while ($queue.Count -gt 0) {
        $id = $queue.Dequeue()
        foreach ($process in $all | Where-Object { $_.ProcessId -eq $id }) {
            $tree += $process
        }
        foreach ($child in $all | Where-Object { $_.ParentProcessId -eq $id -and $_.ProcessId -ne $id }) {
            $queue.Enqueue($child.ProcessId)
        }
    }

    return $tree
}

function Get-AncestorIds([int] $ProcessId) {
    # The shells that launched this script carry the test command in their command line, so they
    # match $testProcessPattern; they are not test processes. A parent must have started no later
    # than its child: otherwise its PID was reused after the real parent exited, and the walk stops
    # there instead of excluding an unrelated process. The set also stops a loop.
    $all = Get-CimInstance Win32_Process | Select-Object ProcessId, ParentProcessId, CreationDate
    $ancestors = [System.Collections.Generic.HashSet[int]]::new()
    $current = $all | Where-Object { $_.ProcessId -eq $ProcessId } | Select-Object -First 1

    while ($null -ne $current) {
        $child = $current
        $current = $all | Where-Object {
            $_.ProcessId -eq $child.ParentProcessId -and $_.CreationDate -le $child.CreationDate
        } | Select-Object -First 1

        if ($null -ne $current -and -not $ancestors.Add([int] $current.ProcessId)) {
            break
        }
    }

    # The comma keeps PowerShell from unrolling the set: with zero or one element the caller would
    # get $null or a bare [int] and fail on .Contains().
    return , $ancestors
}

function Test-StillAlive($Snapshot) {
    # Same PID and start time, so a reused PID is not mistaken for the original process.
    $current = Get-CimInstance Win32_Process -Filter "ProcessId = $($Snapshot.ProcessId)" -ErrorAction SilentlyContinue

    return $null -ne $current -and $current.CreationDate -eq $Snapshot.CreationDate
}

Write-Host "run-tests: $Command (limit $TimeoutSeconds s)"
$stopwatch = [System.Diagnostics.Stopwatch]::StartNew()

$process = Start-Process -FilePath 'cmd.exe' -ArgumentList '/d', '/c', $Command -NoNewWindow -PassThru
# Touch the handle now: without it, ExitCode can come back empty once the process has exited.
$null = $process.Handle

if ($process.WaitForExit($TimeoutSeconds * 1000)) {
    $process.WaitForExit()
    $seconds = [math]::Round($stopwatch.Elapsed.TotalSeconds, 1)
    $exitCode = $process.ExitCode
    Write-Host "run-tests: finished in $seconds s with exit code $exitCode"
    exit $exitCode
}

$tree = Get-ProcessTree $process.Id

& taskkill.exe /PID $process.Id /T /F *> $null

# taskkill /T misses descendants whose parent already exited; stop any of them still alive.
foreach ($snapshot in $tree) {
    if (Test-StillAlive $snapshot) {
        Stop-Process -Id $snapshot.ProcessId -Force -ErrorAction SilentlyContinue
    }
}

Start-Sleep -Milliseconds 500

$left = @($tree | Where-Object { Test-StillAlive $_ })
$seconds = [math]::Round($stopwatch.Elapsed.TotalSeconds, 1)

$treeIds = $tree.ProcessId
$ancestorIds = Get-AncestorIds $PID
$otherTestProcesses = @(Get-CimInstance Win32_Process | Where-Object {
    $_.ProcessId -notin $treeIds -and $_.ProcessId -ne $PID -and -not $ancestorIds.Contains([int] $_.ProcessId) -and
        $_.CommandLine -match $testProcessPattern
})

foreach ($other in $otherTestProcesses) {
    [Console]::Error.WriteLine("run-tests: warning: test process outside this run still running: $($other.ProcessId) $($other.Name)")
}

if ($left.Count -gt 0) {
    $list = ($left | ForEach-Object { "$($_.ProcessId) $($_.Name)" }) -join ', '
    [Console]::Error.WriteLine("run-tests: TIMEOUT after $seconds s running `"$Command`": killed $($tree.Count) processes, but these are still running: $list")
    exit 125
}

[Console]::Error.WriteLine("run-tests: TIMEOUT after $seconds s running `"$Command`": killed $($tree.Count) processes; none left")
exit 124
