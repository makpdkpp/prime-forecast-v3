param(
    [string]$CredentialEnvPath = 'C:\laragon\www\Prime-Forecast-V2\.env',
    [ValidateSet('reset', 'test', 'reset-and-test')]
    [string]$Action = 'reset-and-test'
)

$ErrorActionPreference = 'Stop'
$testDatabase = 'prime_forecast_v3_test'

if ($testDatabase -ne 'prime_forecast_v3_test' -or -not $testDatabase.EndsWith('_test')) {
    throw "Unsafe database target: $testDatabase"
}

if (-not (Test-Path -LiteralPath $CredentialEnvPath)) {
    throw "Credential environment file not found: $CredentialEnvPath"
}

Get-Content -LiteralPath $CredentialEnvPath | ForEach-Object {
    if ($_ -match '^[A-Za-z_][A-Za-z0-9_]*=') {
        $pair = $_ -split '=', 2
        if ($pair[0] -notin @('PATH', 'HOME', 'USERPROFILE', 'TEMP', 'TMP', 'DB_DATABASE', 'APP_ENV')) {
            [Environment]::SetEnvironmentVariable($pair[0], $pair[1].Trim('"'), 'Process')
        }
    }
}

$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = $testDatabase
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:MAIL_MAILER = 'array'
$env:QUEUE_CONNECTION = 'sync'

if ($Action -in @('reset', 'reset-and-test')) {
    php artisan migrate:fresh --seed --env=testing --force
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if ($Action -in @('test', 'reset-and-test')) {
    php artisan test --colors=never
    exit $LASTEXITCODE
}
