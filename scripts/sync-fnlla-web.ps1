<#
===============================================================================
FNLLA PHP MAINTAINER SCRIPT
File: scripts\sync-fnlla-web.ps1
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA PHP framework.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
#>

[CmdletBinding()]
param(
    [string]$SourcePath,
    [string]$RepoUrl,
    [string]$Repository = "techayoDEV/fnlla-web",
    [string]$WorkingClonePath,
    [string]$Ref
)

Set-StrictMode -Version 3.0
$ErrorActionPreference = "Stop"

function Resolve-AbsolutePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    return [System.IO.Path]::GetFullPath($Path)
}

function Assert-DirectoryExists {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [Parameter(Mandatory = $true)]
        [string]$Description
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw "$Description does not exist: $Path"
    }
}

function Assert-CommandExists {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name
    )

    $command = Get-Command -Name $Name -ErrorAction SilentlyContinue
    if ($null -eq $command) {
        throw "Required command is not available: $Name"
    }

    return $command.Source
}

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments,
        [string]$Label = $FilePath
    )

    Write-Host "Running $Label $($Arguments -join ' ')"
    & $FilePath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

function Resolve-PublishScriptPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BasePath
    )

    $preferredPath = Join-Path $BasePath "scripts\publish-fnlla-web.mjs"
    if (Test-Path -LiteralPath $preferredPath -PathType Leaf) {
        return $preferredPath
    }

    $publishScripts = @(Get-ChildItem -LiteralPath (Join-Path $BasePath "scripts") -Filter "publish-*.mjs" -File -ErrorAction SilentlyContinue | Sort-Object -Property Name)
    foreach ($script in $publishScripts) {
        return $script.FullName
    }

    return $null
}

function Try-CloneSource {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ClonePath,
        [Parameter(Mandatory = $true)]
        [string]$ProjectRepository,
        [string]$ExplicitRepoUrl
    )

    $gitPath = Assert-CommandExists -Name "git"
    $previousPrompt = $env:GIT_TERMINAL_PROMPT
    $env:GIT_TERMINAL_PROMPT = "0"

    try {
        if ($ExplicitRepoUrl) {
            Invoke-CheckedCommand -FilePath $gitPath -Arguments @("clone", "--depth", "1", $ExplicitRepoUrl, $ClonePath) -Label "git"
            return
        }

        $attempts = @()

        $ghCommand = Get-Command -Name "gh" -ErrorAction SilentlyContinue
        if ($null -ne $ghCommand) {
            $attempts += @{
                Label = "gh"
                FilePath = $ghCommand.Source
                Arguments = @("repo", "clone", $ProjectRepository, $ClonePath, "--", "--depth", "1")
            }
        }

        $attempts += @{
            Label = "git"
            FilePath = $gitPath
            Arguments = @("clone", "--depth", "1", "git@github.com:techayoDEV/fnlla-web.git", $ClonePath)
        }
        $attempts += @{
            Label = "git"
            FilePath = $gitPath
            Arguments = @("clone", "--depth", "1", "https://github.com/techayoDEV/fnlla-web.git", $ClonePath)
        }

        $errors = New-Object System.Collections.Generic.List[string]

        foreach ($attempt in $attempts) {
            try {
                Invoke-CheckedCommand -FilePath $attempt.FilePath -Arguments $attempt.Arguments -Label $attempt.Label
                return
            }
            catch {
                $errors.Add($_.Exception.Message)
                if (Test-Path -LiteralPath $ClonePath) {
                    Remove-Item -LiteralPath $ClonePath -Recurse -Force
                }
            }
        }

        throw "Unable to clone private repository techayoDEV/fnlla-web. Attempts failed: $($errors -join ' | ')"
    }
    finally {
        $env:GIT_TERMINAL_PROMPT = $previousPrompt
    }
}

function Resolve-RuntimeExportPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BasePath
    )

    $base = Resolve-AbsolutePath -Path $BasePath
    $distPath = Join-Path $base "dist\fnlla-web"
    $assetsPath = Join-Path $base "assets"
    $versionPath = Join-Path $base "VERSION"
    $sourceRepoMarkers = @(
        ".git",
        ".github",
        "docs",
        "scripts",
        "src",
        "package.json"
    )

    if ((Test-Path -LiteralPath $distPath -PathType Container) -and (Test-Path -LiteralPath (Join-Path $distPath "VERSION") -PathType Leaf)) {
        return $distPath
    }

    $looksLikeSourceRepo = $false
    foreach ($marker in $sourceRepoMarkers) {
        if (Test-Path -LiteralPath (Join-Path $base $marker)) {
            $looksLikeSourceRepo = $true
            break
        }
    }

    if ($looksLikeSourceRepo) {
        $publishScriptPath = Resolve-PublishScriptPath -BasePath $base
        if ($null -eq $publishScriptPath) {
            throw "The provided FNLLA Web path looks like a source repository checkout, but no publish script was found. Publish FNLLA Web first and sync from dist\\fnlla-web."
        }

        $nodePath = Assert-CommandExists -Name "node"
        Invoke-CheckedCommand -FilePath $nodePath -Arguments @($publishScriptPath) -Label "node"

        if ((Test-Path -LiteralPath $distPath -PathType Container) -and (Test-Path -LiteralPath (Join-Path $distPath "VERSION") -PathType Leaf)) {
            return $distPath
        }

        throw "FNLLA Web publish completed, but dist\\fnlla-web was not created under: $base"
    }

    if ((Test-Path -LiteralPath $assetsPath -PathType Container) -and (Test-Path -LiteralPath $versionPath -PathType Leaf)) {
        return $base
    }

    throw "Could not locate FNLLA Web runtime export under: $BasePath"
}

function Assert-SafeCloneReset {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ClonePath,
        [Parameter(Mandatory = $true)]
        [string]$ProjectRoot
    )

    $resolvedClonePath = Resolve-AbsolutePath -Path $ClonePath
    $resolvedProjectRoot = Resolve-AbsolutePath -Path $ProjectRoot
    $resolvedTempRoot = Resolve-AbsolutePath -Path ([System.IO.Path]::GetTempPath())
    $cloneItem = Get-Item -LiteralPath $resolvedClonePath -ErrorAction SilentlyContinue

    if ($null -eq $cloneItem) {
        return
    }

    if ($cloneItem.PSIsContainer -eq $false) {
        throw "Working clone path must be a directory: $resolvedClonePath"
    }

    if ($resolvedClonePath -eq $resolvedProjectRoot) {
        throw "Refusing to reset the project root as a working clone path."
    }

    if ($resolvedClonePath.Length -le 3) {
        throw "Refusing to reset a root-level path: $resolvedClonePath"
    }

    $isUnderProject = $resolvedClonePath.StartsWith($resolvedProjectRoot, [System.StringComparison]::OrdinalIgnoreCase)
    $isUnderTemp = $resolvedClonePath.StartsWith($resolvedTempRoot, [System.StringComparison]::OrdinalIgnoreCase)

    if (-not $isUnderProject -and -not $isUnderTemp) {
        throw "Refusing to reset a working clone path outside the project or temp directory: $resolvedClonePath"
    }

    $gitDirectory = Join-Path $resolvedClonePath ".git"
    $entries = @(Get-ChildItem -LiteralPath $resolvedClonePath -Force)

    if ($entries.Count -gt 0 -and -not (Test-Path -LiteralPath $gitDirectory -PathType Container)) {
        throw "Refusing to reset a non-empty directory that does not look like a git clone: $resolvedClonePath"
    }
}

function Sync-RuntimeExport {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceRuntimePath,
        [Parameter(Mandatory = $true)]
        [string]$DestinationRuntimePath
    )

    $robocopyPath = Assert-CommandExists -Name "robocopy"
    if (Test-Path -LiteralPath $DestinationRuntimePath) {
        Remove-Item -LiteralPath $DestinationRuntimePath -Recurse -Force
    }

    New-Item -ItemType Directory -Path $DestinationRuntimePath | Out-Null

    & $robocopyPath $SourceRuntimePath $DestinationRuntimePath /MIR /NFL /NDL /NJH /NJS /NP
    $exitCode = $LASTEXITCODE

    if ($exitCode -gt 7) {
        throw "robocopy failed with exit code $exitCode."
    }
}

$projectRoot = Resolve-AbsolutePath -Path (Join-Path $PSScriptRoot "..")
$targetRuntimePath = Resolve-AbsolutePath -Path (Join-Path $projectRoot "public\vendor\fnlla-web")
$publicRoot = Resolve-AbsolutePath -Path (Join-Path $projectRoot "public")

Assert-DirectoryExists -Path $projectRoot -Description "Project root"
Assert-DirectoryExists -Path $publicRoot -Description "Public directory"

if (-not $targetRuntimePath.StartsWith($publicRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to sync outside the public directory: $targetRuntimePath"
}

$ephemeralClonePath = $null

try {
    if ($SourcePath) {
        $resolvedSourcePath = Resolve-AbsolutePath -Path $SourcePath
        Assert-DirectoryExists -Path $resolvedSourcePath -Description "FNLLA Web source path"
        $sourceRuntimePath = Resolve-RuntimeExportPath -BasePath $resolvedSourcePath
    }
    else {
        if ($WorkingClonePath) {
            $cloneRoot = Resolve-AbsolutePath -Path $WorkingClonePath
        }
        else {
            $cloneRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("fnlla-web-sync-" + [System.Guid]::NewGuid().ToString("N"))
            $ephemeralClonePath = $cloneRoot
        }

        if (Test-Path -LiteralPath $cloneRoot) {
            Assert-SafeCloneReset -ClonePath $cloneRoot -ProjectRoot $projectRoot
            Remove-Item -LiteralPath $cloneRoot -Recurse -Force
        }

        Try-CloneSource -ClonePath $cloneRoot -ProjectRepository $Repository -ExplicitRepoUrl $RepoUrl

        if ($Ref) {
            $gitPath = Assert-CommandExists -Name "git"
            Invoke-CheckedCommand -FilePath $gitPath -Arguments @("-C", $cloneRoot, "checkout", $Ref) -Label "git"
        }

        $sourceRuntimePath = Resolve-RuntimeExportPath -BasePath $cloneRoot
    }

    $sourceVersionPath = Join-Path $sourceRuntimePath "VERSION"
    $targetVersionPath = Join-Path $targetRuntimePath "VERSION"

    Sync-RuntimeExport -SourceRuntimePath $sourceRuntimePath -DestinationRuntimePath $targetRuntimePath

    $sourceVersion = (Get-Content -LiteralPath $sourceVersionPath -TotalCount 1).Trim()
    $targetVersion = (Get-Content -LiteralPath $targetVersionPath -TotalCount 1).Trim()

    Write-Host ""
    Write-Host "FNLLA Web sync complete."
    Write-Host "Source runtime: $sourceRuntimePath"
    Write-Host "Target runtime: $targetRuntimePath"
    Write-Host "Version: $sourceVersion"

    if ($sourceVersion -ne $targetVersion) {
        throw "Version mismatch after sync. Source=$sourceVersion Target=$targetVersion"
    }

    $phpPath = Assert-CommandExists -Name "php"
    $versionSyncScriptPath = Join-Path $projectRoot "scripts\sync-version-manifest.php"
    Invoke-CheckedCommand -FilePath $phpPath -Arguments @($versionSyncScriptPath) -Label "php"
}
finally {
    if ($ephemeralClonePath -and (Test-Path -LiteralPath $ephemeralClonePath)) {
        Remove-Item -LiteralPath $ephemeralClonePath -Recurse -Force
    }
}
