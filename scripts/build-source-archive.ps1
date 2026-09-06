param([string]$OutputPath = '')

Set-StrictMode -Version 3.0
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = [IO.Path]::GetFullPath((Split-Path $PSScriptRoot -Parent)).TrimEnd('\', '/')
if ($OutputPath -eq '') { $OutputPath = Join-Path $root 'dist/fnlla-source.zip' }
$output = [IO.Path]::GetFullPath($OutputPath)
if (-not $output.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -or
    -not $output.StartsWith((Join-Path $root 'dist') + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Source archives must be written below this repository dist directory.'
}
if (Test-Path -LiteralPath $output) { throw 'Archive already exists; use a different output filename.' }
& php (Join-Path $root 'scripts/check-docs.php')
if ($LASTEXITCODE -ne 0) { throw 'Documentation hygiene failed; archive was not created.' }
$policy = Get-Content -LiteralPath (Join-Path $root 'resources/source-distribution.json') -Raw | ConvertFrom-Json
if ($policy.schema -ne 'fnlla.source_distribution.v1') { throw 'Invalid source distribution policy.' }
$files = @(& git -c core.quotePath=false -C $root ls-files --cached --others --exclude-standard)
if ($LASTEXITCODE -ne 0) { throw 'Cannot enumerate source files with Git.' }
$selected = @()
$bytes = 0L
$excludedBytes = 0L
foreach ($relative in ($files | Sort-Object -Unique)) {
    $source = [IO.Path]::GetFullPath((Join-Path $root $relative))
    if (-not $source.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe source path.' }
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { continue }
    $skip = $false
    foreach ($prefix in $policy.excluded_prefixes) { if ($relative.StartsWith($prefix)) { $skip = $true } }
    $name = [IO.Path]::GetFileName($relative)
    if ($name.StartsWith('.env', [StringComparison]::OrdinalIgnoreCase) -and $name -notin @('.env.example', '.env.full.example')) { $skip = $true }
    if ($name -in $policy.excluded_names) { $skip = $true }
    if ([IO.Path]::GetExtension($name).TrimStart('.') -in $policy.excluded_extensions) { $skip = $true }
    if ($skip) { $excludedBytes += (Get-Item -LiteralPath $source).Length; continue }
    $probe = $source
    while ($probe -ne $root) {
        if ((Get-Item -LiteralPath $probe -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Source archive does not follow reparse points.' }
        $probe = Split-Path $probe -Parent
    }
    $selected += [pscustomobject]@{ Source = $source; Relative = $relative }
    $bytes += (Get-Item -LiteralPath $source).Length
}
$directory = Split-Path $output -Parent
$probe = $directory
while ($probe -ne $root) {
    if ((Test-Path -LiteralPath $probe) -and ((Get-Item -LiteralPath $probe -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Archive destination does not follow reparse points.' }
    $probe = Split-Path $probe -Parent
}
$null = New-Item -ItemType Directory -Path $directory -Force
$temporary = Join-Path $directory ([guid]::NewGuid().ToString('N') + '.zip')
try {
    $archive = [IO.Compression.ZipFile]::Open($temporary, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in $selected) {
            $null = [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.Source, $file.Relative, [IO.Compression.CompressionLevel]::Optimal)
        }
    } finally { $archive.Dispose() }
    Move-Item -LiteralPath $temporary -Destination $output
} finally {
    if (Test-Path -LiteralPath $temporary) { Remove-Item -LiteralPath $temporary }
}
[pscustomobject]@{ Archive = $output; Files = $selected.Count; UncompressedBytes = $bytes; ExcludedBytes = $excludedBytes;
    ArchiveBytes = (Get-Item -LiteralPath $output).Length; SHA256 = (Get-FileHash -LiteralPath $output -Algorithm SHA256).Hash } | ConvertTo-Json
