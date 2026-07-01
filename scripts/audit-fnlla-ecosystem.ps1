<#
===============================================================================
FNLLA PHP MAINTAINER SCRIPT
File: scripts\audit-fnlla-ecosystem.ps1
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA PHP framework.

Purpose:
- audits fnlla-php against the vendored FNLLA Web runtime, sibling local repos
  and the public GitHub ecosystem contract before cross-repository release work
#>

[CmdletBinding()]
param(
    [string]$FnllaWebPath,
    [string]$FnllaOrgGithubPath,
    [switch]$SkipLocalChecks,
    [switch]$SkipRemoteChecks
)

Set-StrictMode -Version 3.0
$ErrorActionPreference = "Stop"

function Resolve-AbsolutePath {
    param([Parameter(Mandatory = $true)][string]$Path)

    return [System.IO.Path]::GetFullPath($Path)
}

function Read-VersionFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Missing version file: $Path"
    }

    $firstLine = (Get-Content -LiteralPath $Path -TotalCount 1).Trim()

    if ($firstLine -eq "") {
        throw "Version file has an empty first line: $Path"
    }

    return $firstLine
}

function Read-JsonFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Missing JSON file: $Path"
    }

    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
}

function Add-Error {
    param(
        [System.Collections.Generic.List[string]]$Errors,
        [Parameter(Mandatory = $true)][string]$Message
    )

    $Errors.Add($Message) | Out-Null
}

function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    $output = & git @Arguments 2>&1
    $exitCode = $LASTEXITCODE

    return @{
        Output = @($output)
        ExitCode = $exitCode
    }
}

function Assert-RemoteTagExists {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryUrl,
        [Parameter(Mandatory = $true)][string]$Tag,
        [System.Collections.Generic.List[string]]$Errors
    )

    $result = Invoke-Git -Arguments @("ls-remote", "--tags", $RepositoryUrl, "refs/tags/$Tag")

    if ($result.ExitCode -ne 0) {
        Add-Error -Errors $Errors -Message "Unable to query remote tags for ${RepositoryUrl}: $($result.Output -join ' ')"
        return
    }

    if ((@($result.Output | Where-Object { $_ -match [regex]::Escape("refs/tags/$Tag") })).Count -eq 0) {
        Add-Error -Errors $Errors -Message "Remote repository $RepositoryUrl does not expose tag $Tag."
    }
}

function New-TemporaryClone {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryUrl,
        [Parameter(Mandatory = $true)][string]$Prefix
    )

    $clonePath = Join-Path ([System.IO.Path]::GetTempPath()) ($Prefix + "-" + [System.Guid]::NewGuid().ToString("N"))
    $result = Invoke-Git -Arguments @("clone", "--depth", "1", $RepositoryUrl, $clonePath)

    if ($result.ExitCode -ne 0) {
        throw "Unable to clone ${RepositoryUrl}: $($result.Output -join ' ')"
    }

    return $clonePath
}

function Test-OrganizationBranding {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryPath,
        [System.Collections.Generic.List[string]]$Errors
    )

    $targets = @(
        "README.md",
        "CONTRIBUTING.md",
        "CODE_OF_CONDUCT.md",
        "profile\README.md",
        ".github\ISSUE_TEMPLATE\bug-report.yml",
        ".github\ISSUE_TEMPLATE\feature-request.yml"
    )
    $legacyPattern = "FNLLA UI|fnlla/ui|fnlla-ui"

    foreach ($relativePath in $targets) {
        $absolutePath = Join-Path $RepositoryPath $relativePath

        if (-not (Test-Path -LiteralPath $absolutePath -PathType Leaf)) {
            Add-Error -Errors $Errors -Message "Organization defaults are missing expected file: $relativePath"
            continue
        }

        $matches = Select-String -Path $absolutePath -Pattern $legacyPattern

        foreach ($match in $matches) {
            Add-Error -Errors $Errors -Message ("Organization defaults still use legacy FNLLA UI naming in {0}:{1}" -f $relativePath, $match.LineNumber)
        }
    }
}

$projectRoot = Resolve-AbsolutePath -Path (Join-Path $PSScriptRoot "..")
$workspaceRoot = Split-Path -Path $projectRoot -Parent
$resolvedFnllaWebPath = if ($FnllaWebPath) { Resolve-AbsolutePath -Path $FnllaWebPath } else { Join-Path $workspaceRoot "fnlla-web" }
$resolvedFnllaOrgGithubPath = if ($FnllaOrgGithubPath) { Resolve-AbsolutePath -Path $FnllaOrgGithubPath } else { Join-Path $workspaceRoot "fnlla-org-github" }
$temporaryPaths = New-Object System.Collections.Generic.List[string]
$errors = New-Object System.Collections.Generic.List[string]

try {
    $frameworkVersion = Read-VersionFile -Path (Join-Path $projectRoot "VERSION")
    $vendoredWebVersion = Read-VersionFile -Path (Join-Path $projectRoot "public\vendor\fnlla-web\VERSION")
    $projectManifest = Read-JsonFile -Path (Join-Path $projectRoot "MANIFEST.json")

    if ($projectManifest.product.version -ne $frameworkVersion) {
        Add-Error -Errors $errors -Message "fnlla-php MANIFEST.json product.version does not match VERSION ($($projectManifest.product.version) vs $frameworkVersion)."
    }

    if ($projectManifest.ui_runtime.vendored_version -ne $vendoredWebVersion) {
        Add-Error -Errors $errors -Message "fnlla-php MANIFEST.json ui_runtime.vendored_version does not match public/vendor/fnlla-web/VERSION ($($projectManifest.ui_runtime.vendored_version) vs $vendoredWebVersion)."
    }

    if (-not $SkipLocalChecks) {
        if (Test-Path -LiteralPath $resolvedFnllaWebPath -PathType Container) {
            $localWebVersion = Read-VersionFile -Path (Join-Path $resolvedFnllaWebPath "VERSION")
            $localWebManifest = Read-JsonFile -Path (Join-Path $resolvedFnllaWebPath "MANIFEST.json")

            if ($localWebVersion -ne $vendoredWebVersion) {
                Add-Error -Errors $errors -Message "Local fnlla-web VERSION ($localWebVersion) does not match fnlla-php vendored FNLLA Web version ($vendoredWebVersion)."
            }

            if ($localWebManifest.product.version -ne $localWebVersion) {
                Add-Error -Errors $errors -Message "Local fnlla-web MANIFEST.json product.version does not match VERSION ($($localWebManifest.product.version) vs $localWebVersion)."
            }

            $tagResult = Invoke-Git -Arguments @("-C", $resolvedFnllaWebPath, "describe", "--tags", "--exact-match")
            if ($tagResult.ExitCode -eq 0) {
                Write-Host "Local fnlla-web checkout is exactly on tag $($tagResult.Output[0])."
            } else {
                Write-Host "Local fnlla-web checkout is not exactly on a release tag; treat it as unpublished work unless that is intentional."
            }
        } else {
            Write-Host "Skipping local fnlla-web check because the path does not exist: $resolvedFnllaWebPath"
        }

        if (Test-Path -LiteralPath $resolvedFnllaOrgGithubPath -PathType Container) {
            Test-OrganizationBranding -RepositoryPath $resolvedFnllaOrgGithubPath -Errors $errors
        } else {
            Write-Host "Skipping local fnlla-org-github check because the path does not exist: $resolvedFnllaOrgGithubPath"
        }
    }

    if (-not $SkipRemoteChecks) {
        Assert-RemoteTagExists -RepositoryUrl "https://github.com/fnlla/web.git" -Tag ("v" + $vendoredWebVersion) -Errors $errors

        $orgRepoPath = $resolvedFnllaOrgGithubPath
        $clonedOrgRepo = $false

        if (-not (Test-Path -LiteralPath $orgRepoPath -PathType Container)) {
            $orgRepoPath = New-TemporaryClone -RepositoryUrl "https://github.com/fnlla/.github.git" -Prefix "fnlla-org-github-audit"
            $temporaryPaths.Add($orgRepoPath) | Out-Null
            $clonedOrgRepo = $true
        }

        Test-OrganizationBranding -RepositoryPath $orgRepoPath -Errors $errors

        if ($clonedOrgRepo) {
            Write-Host "Remote fnlla/.github defaults were cloned into a temporary workspace for branding validation."
        }
    }

    Write-Host "FNLLA PHP version: $frameworkVersion"
    Write-Host "Vendored FNLLA Web version: $vendoredWebVersion"

    if ($errors.Count -gt 0) {
        Write-Host ""
        Write-Host "FNLLA ecosystem audit failed."
        foreach ($entry in $errors) {
            Write-Host "- $entry"
        }
        exit 1
    }

    Write-Host ""
    Write-Host "FNLLA ecosystem audit passed."
}
finally {
    foreach ($temporaryPath in $temporaryPaths) {
        if (Test-Path -LiteralPath $temporaryPath) {
            Remove-Item -LiteralPath $temporaryPath -Recurse -Force
        }
    }
}
