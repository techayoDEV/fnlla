<#
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\publish-fnlla-runtime.ps1
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA framework.

Purpose:
- publishes the integrated FNLLA Runtime from public/vendor/fnlla-runtime/
  into a clean dist/fnlla-runtime export for downstream sync and release work
#>

[CmdletBinding()]
param(
    [string]$OutputPath
)

Set-StrictMode -Version 3.0
$ErrorActionPreference = "Stop"

function Resolve-AbsolutePath {
    param([Parameter(Mandatory = $true)][string]$Path)

    return [System.IO.Path]::GetFullPath($Path)
}

function Assert-CommandExists {
    param([Parameter(Mandatory = $true)][string]$Name)

    $command = Get-Command -Name $Name -ErrorAction SilentlyContinue
    if ($null -eq $command) {
        throw "Required command is not available: $Name"
    }

    return $command.Source
}

function Sync-Directory {
    param(
        [Parameter(Mandatory = $true)][string]$SourcePath,
        [Parameter(Mandatory = $true)][string]$DestinationPath
    )

    $robocopyPath = Assert-CommandExists -Name "robocopy"

    if (Test-Path -LiteralPath $DestinationPath) {
        Remove-Item -LiteralPath $DestinationPath -Recurse -Force
    }

    New-Item -ItemType Directory -Path $DestinationPath | Out-Null

    & $robocopyPath $SourcePath $DestinationPath /MIR /NFL /NDL /NJH /NJS /NP
    $exitCode = $LASTEXITCODE

    if ($exitCode -gt 7) {
        throw "robocopy failed with exit code $exitCode."
    }
}

$projectRoot = Resolve-AbsolutePath -Path (Join-Path $PSScriptRoot "..")
$sourceRuntimePath = Resolve-AbsolutePath -Path (Join-Path $projectRoot "public\vendor\fnlla-runtime")
$distRoot = if ($OutputPath) {
    Resolve-AbsolutePath -Path $OutputPath
} else {
    Resolve-AbsolutePath -Path (Join-Path $projectRoot "dist\fnlla-runtime")
}
$distParent = Split-Path -Path $distRoot -Parent

if (-not (Test-Path -LiteralPath $sourceRuntimePath -PathType Container)) {
    throw "Integrated FNLLA Runtime source is missing: $sourceRuntimePath"
}

if (-not (Test-Path -LiteralPath (Join-Path $sourceRuntimePath "VERSION") -PathType Leaf)) {
    throw "Integrated FNLLA Runtime version file is missing under: $sourceRuntimePath"
}

if (-not $distRoot.StartsWith($projectRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to publish outside the repository root: $distRoot"
}

if ($distRoot -eq $sourceRuntimePath) {
    throw "Refusing to publish over the integrated runtime source directory."
}

if (-not (Test-Path -LiteralPath $distParent -PathType Container)) {
    New-Item -ItemType Directory -Path $distParent | Out-Null
}

Sync-Directory -SourcePath $sourceRuntimePath -DestinationPath $distRoot

$version = (Get-Content -LiteralPath (Join-Path $distRoot "VERSION") -TotalCount 1).Trim()

Write-Host "FNLLA Runtime publish complete."
Write-Host "Source runtime: $sourceRuntimePath"
Write-Host "Published export: $distRoot"
Write-Host "Version: $version"

$global:LASTEXITCODE = 0
