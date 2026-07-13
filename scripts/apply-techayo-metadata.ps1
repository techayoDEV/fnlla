<#
===============================================================================
FNLLA MAINTAINER SCRIPT: OWNERSHIP AND HEADER NORMALIZATION
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

This script applies a consistent TechAyo LTD ownership banner to first-party
source files in the FNLLA repository so the framework stays aligned with
the same repository identity style used across the integrated FNLLA UI surface.
#>

[CmdletBinding()]
param()

Set-StrictMode -Version 3.0
$ErrorActionPreference = "Stop"

function Get-ProjectRoot {
    return [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot ".."))
}

function Get-RelativePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ProjectRoot,
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    $rootUri = [System.Uri]((Resolve-Path -LiteralPath $ProjectRoot).Path + [System.IO.Path]::DirectorySeparatorChar)
    $pathUri = [System.Uri](Resolve-Path -LiteralPath $Path).Path

    return [System.Uri]::UnescapeDataString($rootUri.MakeRelativeUri($pathUri).ToString()).Replace("/", "\")
}

function Get-TitleAndPurpose {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    switch -Regex ($RelativePath) {
        '^bootstrap\\' { return @("FNLLA BOOTSTRAP FILE", "Bootstraps a framework runtime stage or shared application environment boundary.") }
        '^config\\' { return @("FNLLA CONFIGURATION FILE", "Defines maintained application or framework configuration for the official FNLLA stack.") }
        '^database\\migrations\\' { return @("FNLLA DATABASE MIGRATION", "Defines a schema change for the maintained MySQL delivery contract.") }
        '^database\\seeders\\' { return @("FNLLA DATABASE SEEDER", "Provides seed data for framework demos, local setup or delivery bootstrapping.") }
        '^database\\factories\\' { return @("FNLLA DATA FACTORY", "Provides repeatable data generation for tests, seeding and local framework validation.") }
        '^lang\\' { return @("FNLLA LOCALIZATION FILE", "Provides maintained translation lines for the official FNLLA runtime.") }
        '^public\\assets\\' { return @("FNLLA PUBLIC ASSET", "Styles the delivery-facing shell that sits on top of the vendored FNLLA runtime.") }
        '^public\\' { return @("FNLLA PUBLIC ENTRYPOINT", "Handles a public web request or static file routing boundary for the maintained framework.") }
        '^routes\\' { return @("FNLLA ROUTE DEFINITION", "Registers maintained HTTP or console routes for the framework runtime.") }
        '^scripts\\' { return @("FNLLA MAINTAINER SCRIPT", "Supports framework maintenance, validation, release hygiene or repository hardening.") }
        '^src\\Auth\\' { return @("FNLLA AUTHENTICATION SOURCE", "Implements authentication, authorization or access-control primitives for the framework.") }
        '^src\\Cache\\' { return @("FNLLA CACHE SOURCE", "Implements maintained cache and rate-limiting primitives for the framework runtime.") }
        '^src\\Console\\' { return @("FNLLA CONSOLE SOURCE", "Implements the maintained CLI surface and scheduler-oriented console behavior.") }
        '^src\\Container\\' { return @("FNLLA CONTAINER SOURCE", "Implements dependency resolution for the maintained framework runtime.") }
        '^src\\Controllers\\' { return @("FNLLA CONTROLLER SOURCE", "Provides HTTP-facing controller behavior for maintained framework flows and demos.") }
        '^src\\Database\\' { return @("FNLLA DATABASE SOURCE", "Implements the maintained MySQL data access and migration runtime.") }
        '^src\\Events\\' { return @("FNLLA EVENT SOURCE", "Implements the maintained event dispatching layer for the framework runtime.") }
        '^src\\Exceptions\\' { return @("FNLLA EXCEPTION SOURCE", "Implements framework-level exception reporting and rendering behavior.") }
        '^src\\Filesystem\\' { return @("FNLLA FILESYSTEM SOURCE", "Implements maintained file storage behavior for uploads and local runtime assets.") }
        '^src\\Hashing\\' { return @("FNLLA HASHING SOURCE", "Implements password and hashing helpers for the maintained framework stack.") }
        '^src\\Http\\' { return @("FNLLA HTTP SOURCE", "Implements request, response and HTTP-facing runtime primitives.") }
        '^src\\Localization\\' { return @("FNLLA LOCALIZATION SOURCE", "Implements translation lookup for maintained framework views and flows.") }
        '^src\\Mail\\' { return @("FNLLA MAIL SOURCE", "Implements maintained mail delivery helpers for framework flows and notifications.") }
        '^src\\Middleware\\' { return @("FNLLA MIDDLEWARE SOURCE", "Implements middleware behavior for request hardening, policy and response shaping.") }
        '^src\\Providers\\' { return @("FNLLA SERVICE PROVIDER SOURCE", "Registers maintained framework services and application-level boot behavior.") }
        '^src\\Queue\\' { return @("FNLLA QUEUE SOURCE", "Implements the maintained file-backed queue runtime for asynchronous tasks.") }
        '^src\\Routing\\' { return @("FNLLA ROUTING SOURCE", "Implements maintained route registration, matching and URL generation behavior.") }
        '^src\\Session\\' { return @("FNLLA SESSION SOURCE", "Implements maintained session storage behavior for the framework runtime.") }
        '^src\\Support\\' { return @("FNLLA SUPPORT SOURCE", "Implements shared helpers, environment loading, metadata and framework support behavior.") }
        '^src\\Validation\\' { return @("FNLLA VALIDATION SOURCE", "Implements maintained validation rules and validation error handling.") }
        '^src\\View\\' { return @("FNLLA VIEW SOURCE", "Implements maintained server-rendered view composition for the framework.") }
        '^src\\Application\.php$' { return @("FNLLA APPLICATION KERNEL", "Coordinates the maintained request lifecycle for the FNLLA runtime.") }
        '^tests\\PHPUnit\\' { return @("FNLLA TEST HARNESS SOURCE", "Implements the repository-local test harness used by FNLLA without Packagist dependencies.") }
        '^tests\\' { return @("FNLLA TEST CASE", "Validates maintained framework behavior inside the repository-local test harness.") }
        '^views\\layouts\\' { return @("FNLLA VIEW LAYOUT", "Defines the shared delivery shell for server-rendered pages built on FNLLA's integrated UI surface.") }
        '^views\\pages\\' { return @("FNLLA VIEW TEMPLATE", "Defines a maintained page template for the official FNLLA demonstration surface.") }
        '^fnlla$' { return @("FNLLA REPOSITORY LAUNCHER", "Provides the maintained CLI entrypoint for the FNLLA framework repository.") }
        '^.*\.cmd$' { return @("FNLLA REPOSITORY LAUNCHER", "Provides a Windows launcher for a maintained framework or maintainer workflow command.") }
        default { return @("FNLLA SOURCE FILE", "Belongs to the maintained FNLLA framework repository and delivery toolchain.") }
    }
}

function Build-PhpBanner {
    param(
        [string]$Title,
        [string]$RelativePath,
        [string]$Purpose
    )

    return @"
/*
===============================================================================
$Title
File: $RelativePath
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- $Purpose
*/
"@
}

function Build-CssBanner {
    param(
        [string]$Title,
        [string]$RelativePath,
        [string]$Purpose
    )

    return @"
/*
===============================================================================
$Title
File: $RelativePath
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This stylesheet belongs to the maintained FNLLA delivery
shell that sits on top of the vendored FNLLA runtime.

Purpose:
- $Purpose
*/
"@
}

function Build-Ps1Banner {
    param(
        [string]$Title,
        [string]$RelativePath,
        [string]$Purpose
    )

    return @"
<#
===============================================================================
$Title
File: $RelativePath
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA framework.

Purpose:
- $Purpose
#>
"@
}

function Build-CmdBanner {
    param(
        [string]$Title,
        [string]$RelativePath,
        [string]$Purpose
    )

    return @"
REM ============================================================================
REM $Title
REM File: $RelativePath
REM Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
REM FNLLA is produced, maintained and distributed by TechAyo LTD.
REM Purpose: $Purpose
REM ============================================================================
"@
}

function Apply-PhpHeader {
    param(
        [string]$Path,
        [string]$RelativePath,
        [string]$Title,
        [string]$Purpose
    )

    $content = [System.IO.File]::ReadAllText($Path)
    $preview = ($content -split "(\r?\n)")[0..([Math]::Min(20, ($content -split "(\r?\n)").Length - 1))] -join ""

    if ($preview -match "TechAyo LTD") {
        return
    }

    $banner = Build-PhpBanner -Title $Title -RelativePath $RelativePath -Purpose $Purpose

    if ($content -match "declare\(strict_types=1\);") {
        $content = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            "declare\(strict_types=1\);\r?\n",
            {
                param($match)
                return $match.Value + "`r`n" + $banner + "`r`n"
            },
            1
        )
    } elseif ($content -match "<\?php\r?\n") {
        $content = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            "<\?php\r?\n",
            {
                param($match)
                return $match.Value + "`r`n" + $banner + "`r`n"
            },
            1
        )
    }

    [System.IO.File]::WriteAllText($Path, $content, [System.Text.UTF8Encoding]::new($false))
}

function Apply-CssHeader {
    param(
        [string]$Path,
        [string]$RelativePath,
        [string]$Title,
        [string]$Purpose
    )

    $content = [System.IO.File]::ReadAllText($Path)

    if (($content -split "(\r?\n)")[0..([Math]::Min(20, ($content -split "(\r?\n)").Length - 1))] -join "" -match "TechAyo LTD") {
        return
    }

    $banner = Build-CssBanner -Title $Title -RelativePath $RelativePath -Purpose $Purpose
    $newContent = $banner + "`r`n`r`n" + $content
    [System.IO.File]::WriteAllText($Path, $newContent, [System.Text.UTF8Encoding]::new($false))
}

function Apply-Ps1Header {
    param(
        [string]$Path,
        [string]$RelativePath,
        [string]$Title,
        [string]$Purpose
    )

    $content = [System.IO.File]::ReadAllText($Path)

    if (($content -split "(\r?\n)")[0..([Math]::Min(20, ($content -split "(\r?\n)").Length - 1))] -join "" -match "TechAyo LTD") {
        return
    }

    $banner = Build-Ps1Banner -Title $Title -RelativePath $RelativePath -Purpose $Purpose
    $newContent = $banner + "`r`n`r`n" + $content
    [System.IO.File]::WriteAllText($Path, $newContent, [System.Text.UTF8Encoding]::new($false))
}

function Apply-CmdHeader {
    param(
        [string]$Path,
        [string]$RelativePath,
        [string]$Title,
        [string]$Purpose
    )

    $content = [System.IO.File]::ReadAllText($Path)
    $preview = ($content -split "(\r?\n)")[0..([Math]::Min(20, ($content -split "(\r?\n)").Length - 1))] -join ""

    if ($preview -match "TechAyo LTD") {
        return
    }

    $banner = Build-CmdBanner -Title $Title -RelativePath $RelativePath -Purpose $Purpose

    if ($content -match "^@echo off\r?\n") {
        $content = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            "^@echo off\r?\n",
            {
                param($match)
                return $match.Value + $banner + "`r`n"
            },
            1
        )
    } else {
        $content = $banner + "`r`n" + $content
    }

    [System.IO.File]::WriteAllText($Path, $content, [System.Text.UTF8Encoding]::new($false))
}

$projectRoot = Get-ProjectRoot
$files = Get-ChildItem -LiteralPath $projectRoot -Recurse -File | Where-Object {
    $_.Extension -in @(".php", ".css", ".ps1", ".cmd") -and
    $_.FullName -notmatch "\\vendor\\" -and
    $_.FullName -notmatch "\\public\\vendor\\" -and
    $_.FullName -notmatch "\\storage\\"
}

foreach ($file in $files) {
    $relativePath = Get-RelativePath -ProjectRoot $projectRoot -Path $file.FullName
    $titleAndPurpose = Get-TitleAndPurpose -RelativePath $relativePath
    $title = $titleAndPurpose[0]
    $purpose = $titleAndPurpose[1]

    switch ($file.Extension.ToLowerInvariant()) {
        ".php" { Apply-PhpHeader -Path $file.FullName -RelativePath $relativePath -Title $title -Purpose $purpose }
        ".css" { Apply-CssHeader -Path $file.FullName -RelativePath $relativePath -Title $title -Purpose $purpose }
        ".ps1" { Apply-Ps1Header -Path $file.FullName -RelativePath $relativePath -Title $title -Purpose $purpose }
        ".cmd" { Apply-CmdHeader -Path $file.FullName -RelativePath $relativePath -Title $title -Purpose $purpose }
    }
}

Write-Host "Applied TechAyo LTD metadata headers to first-party source files."
