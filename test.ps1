# Offline check of Debaite: JavaScript and PHP syntax (same checks as CI). Writes nothing. Exit 0 when everything passes.
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$failed = 0

node --check script.js
if ($LASTEXITCODE -ne 0) { $failed++ }

Get-ChildItem api -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Host "PHP syntax error: $($_.FullName)"; $failed++ }
}

if ($failed) { Write-Host "test.ps1: $failed failure(s)"; exit 1 }
Write-Host 'test.ps1: OK (JavaScript and PHP syntax)'
