$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'copy-fnlla-runtime.ps1')

$fixtureRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('fnlla-distribution-test-' + [guid]::NewGuid().ToString('N'))
$fixtureRoot = [System.IO.Path]::GetFullPath($fixtureRoot)
$source = Join-Path $fixtureRoot 'source'
$project = Join-Path $fixtureRoot 'project'
$targetIcons = Join-Path $project 'public/vendor/fnlla-runtime/assets/icons'
try {
    $null = New-Item -ItemType Directory -Path (Join-Path $project '.fnlla') -Force
    Set-Content -LiteralPath (Join-Path $project '.fnlla/ui-distribution') -Value 'sprite'
    foreach ($path in @('LICENSE.md', 'MANIFEST.json', 'README.md', 'SUPPORT.md', 'TRADEMARKS.md', 'VERSION',
        'assets/css/fnlla-runtime.css', 'assets/js/fnlla-runtime.js', 'assets/icons/LICENSE', 'assets/icons/NOTICE.md', 'assets/icons/README.md')) {
        $target = Join-Path $source $path
        $null = New-Item -ItemType Directory -Path (Split-Path $target -Parent) -Force
        Set-Content -LiteralPath $target -Value 'synthetic fixture'
    }
    foreach ($name in @('search', 'old-search-alias')) {
        Set-Content -LiteralPath (Join-Path $source "assets/icons/$name.svg") -Value '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 1L2 2"/></svg>'
    }
    foreach ($iteration in 1..2) {
        Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project
        if (@(Get-ChildItem -LiteralPath $targetIcons -File).Count -ne 4) { throw 'Compact sync restored individual icons.' }
        $document = New-Object System.Xml.XmlDocument
        $document.Load((Join-Path $targetIcons 'sprite.svg'))
        $ids = @($document.SelectNodes('//*[local-name()="symbol"]') | ForEach-Object { $_.GetAttribute('id') })
        if ($ids.Count -ne 2 -or 'search' -notin $ids -or 'old-search-alias' -notin $ids) { throw 'Sprite lost an icon alias.' }
        if ($document.SelectNodes('//*[local-name()="path"]').Count -ne 1 -or $document.SelectNodes('//*[local-name()="use"]/@href')[0].Value -ne '#old-search-alias') {
            throw 'Duplicate icon geometry was not shared.'
        }
    }
    $before = (Get-FileHash -LiteralPath (Join-Path $targetIcons 'sprite.svg')).Hash
    Set-Content -LiteralPath (Join-Path $source 'assets/icons/broken.svg') -Value '<invalid'
    $rejected = $false
    try { Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project } catch { $rejected = $true }
    if (-not $rejected -or (Get-FileHash -LiteralPath (Join-Path $targetIcons 'sprite.svg')).Hash -ne $before) { throw 'Failed sync damaged the installed runtime.' }
    Remove-Item -LiteralPath (Join-Path $source 'assets/icons/broken.svg')
    Set-Content -LiteralPath (Join-Path $project '.fnlla/ui-distribution') -Value 'full'
    Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project
    if (-not (Test-Path -LiteralPath (Join-Path $targetIcons 'search.svg'))) { throw 'Full distribution lost individual icons.' }
    Remove-Item -LiteralPath (Join-Path $project '.fnlla/ui-distribution')
    Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project
    if (-not (Test-Path -LiteralPath (Join-Path $targetIcons 'search.svg'))) { throw 'Legacy projects must retain the full distribution.' }
    Set-Content -LiteralPath (Join-Path $project '.fnlla/ui-distribution') -Value 'sprite'
    foreach ($name in @('search', 'old-search-alias')) { Remove-Item -LiteralPath (Join-Path $source "assets/icons/$name.svg") }
    Set-Content -LiteralPath (Join-Path $source 'assets/icons/sprite.svg') -Value '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="search" viewBox="0 0 24 24"><path d="M1 1L2 2"/></symbol></svg>'
    Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project
    $before = (Get-FileHash -LiteralPath (Join-Path $targetIcons 'sprite.svg')).Hash
    Set-Content -LiteralPath (Join-Path $source 'assets/icons/sprite.svg') -Value '<invalid'
    $rejected = $false
    try { Copy-FnllaUiRuntime -SourceRuntimePath $source -ProjectRoot $project } catch { $rejected = $true }
    if (-not $rejected -or (Get-FileHash -LiteralPath (Join-Path $targetIcons 'sprite.svg')).Hash -ne $before) { throw 'Invalid compact source replaced the installed runtime.' }
    Write-Output 'Runtime distribution tests passed.'
} finally {
    $tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd('\', '/')
    if ((Split-Path $fixtureRoot -Parent) -ne $tempRoot -or (Split-Path $fixtureRoot -Leaf) -notlike 'fnlla-distribution-test-*') { throw 'Invalid fixture cleanup path.' }
    if (Test-Path -LiteralPath $fixtureRoot) { Remove-Item -LiteralPath $fixtureRoot -Recurse -Force }
}
