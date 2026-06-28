<#
===============================================================================
FNLLA PHP MAINTAINER SCRIPT: OWNERSHIP AND HEADER NORMALIZATION
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

This script applies a consistent TechAyo LTD ownership banner to first-party
source files in the FNLLA PHP repository so the framework stays aligned with
the same repository identity style used in FNLLA UI.
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
        '^bootstrap\\' { return @("FNLLA PHP BOOTSTRAP FILE", "Bootstraps a framework runtime stage or shared application environment boundary.") }
        '^config\\' { return @("FNLLA PHP CONFIGURATION FILE", "Defines maintained application or framework configuration for the official FNLLA PHP stack.") }
        '^database\\migrations\\' { return @("FNLLA PHP DATABASE MIGRATION", "Defines a schema change for the maintained MySQL delivery contract.") }
        '^database\\seeders\\' { return @("FNLLA PHP DATABASE SEEDER", "Provides seed data for framework demos, local setup or delivery bootstrapping.") }
        '^database\\factories\\' { return @("FNLLA PHP DATA FACTORY", "Provides repeatable data generation for tests, seeding and local framework validation.") }
        '^lang\\' { return @("FNLLA PHP LOCALIZATION FILE", "Provides maintained translation lines for the official FNLLA PHP runtime.") }
        '^public\\assets\\' { return @("FNLLA PHP PUBLIC ASSET", "Styles the delivery-facing shell that sits on top of the vendored FNLLA UI runtime.") }
        '^public\\' { return @("FNLLA PHP PUBLIC ENTRYPOINT", "Handles a public web request or static file routing boundary for the maintained framework.") }
        '^routes\\' { return @("FNLLA PHP ROUTE DEFINITION", "Registers maintained HTTP or console routes for the framework runtime.") }
        '^scripts\\' { return @("FNLLA PHP MAINTAINER SCRIPT", "Supports framework maintenance, validation, release hygiene or repository hardening.") }
        '^src\\Auth\\' { return @("FNLLA PHP AUTHENTICATION SOURCE", "Implements authentication, authorization or access-control primitives for the framework.") }
        '^src\\Cache\\' { return @("FNLLA PHP CACHE SOURCE", "Implements maintained cache and rate-limiting primitives for the framework runtime.") }
        '^src\\Console\\' { return @("FNLLA PHP CONSOLE SOURCE", "Implements the maintained CLI surface and scheduler-oriented console behavior.") }
        '^src\\Container\\' { return @("FNLLA PHP CONTAINER SOURCE", "Implements dependency resolution for the maintained framework runtime.") }
        '^src\\Controllers\\' { return @("FNLLA PHP CONTROLLER SOURCE", "Provides HTTP-facing controller behavior for maintained framework flows and demos.") }
        '^src\\Database\\' { return @("FNLLA PHP DATABASE SOURCE", "Implements the maintained MySQL data access and migration runtime.") }
        '^src\\Events\\' { return @("FNLLA PHP EVENT SOURCE", "Implements the maintained event dispatching layer for the framework runtime.") }
        '^src\\Exceptions\\' { return @("FNLLA PHP EXCEPTION SOURCE", "Implements framework-level exception reporting and rendering behavior.") }
        '^src\\Filesystem\\' { return @("FNLLA PHP FILESYSTEM SOURCE", "Implements maintained file storage behavior for uploads and local runtime assets.") }
        '^src\\Hashing\\' { return @("FNLLA PHP HASHING SOURCE", "Implements password and hashing helpers for the maintained framework stack.") }
        '^src\\Http\\' { return @("FNLLA PHP HTTP SOURCE", "Implements request, response and HTTP-facing runtime primitives.") }
        '^src\\Localization\\' { return @("FNLLA PHP LOCALIZATION SOURCE", "Implements translation lookup for maintained framework views and flows.") }
        '^src\\Mail\\' { return @("FNLLA PHP MAIL SOURCE", "Implements maintained mail delivery helpers for framework flows and notifications.") }
        '^src\\Middleware\\' { return @("FNLLA PHP MIDDLEWARE SOURCE", "Implements middleware behavior for request hardening, policy and response shaping.") }
        '^src\\Providers\\' { return @("FNLLA PHP SERVICE PROVIDER SOURCE", "Registers maintained framework services and application-level boot behavior.") }
        '^src\\Queue\\' { return @("FNLLA PHP QUEUE SOURCE", "Implements the maintained file-backed queue runtime for asynchronous tasks.") }
        '^src\\Routing\\' { return @("FNLLA PHP ROUTING SOURCE", "Implements maintained route registration, matching and URL generation behavior.") }
        '^src\\Session\\' { return @("FNLLA PHP SESSION SOURCE", "Implements maintained session storage behavior for the framework runtime.") }
        '^src\\Support\\' { return @("FNLLA PHP SUPPORT SOURCE", "Implements shared helpers, environment loading, metadata and framework support behavior.") }
        '^src\\Validation\\' { return @("FNLLA PHP VALIDATION SOURCE", "Implements maintained validation rules and validation error handling.") }
        '^src\\View\\' { return @("FNLLA PHP VIEW SOURCE", "Implements maintained server-rendered view composition for the framework.") }
        '^src\\Application\.php$' { return @("FNLLA PHP APPLICATION KERNEL", "Coordinates the maintained request lifecycle for the FNLLA PHP runtime.") }
        '^tests\\PHPUnit\\' { return @("FNLLA PHP TEST HARNESS SOURCE", "Implements the repository-local test harness used by FNLLA PHP without Packagist dependencies.") }
        '^tests\\' { return @("FNLLA PHP TEST CASE", "Validates maintained framework behavior inside the repository-local test harness.") }
        '^views\\layouts\\' { return @("FNLLA PHP VIEW LAYOUT", "Defines the shared delivery shell for server-rendered pages built on FNLLA UI.") }
        '^views\\pages\\' { return @("FNLLA PHP VIEW TEMPLATE", "Defines a maintained page template for the official FNLLA PHP demonstration surface.") }
        '^fnlla$' { return @("FNLLA PHP REPOSITORY LAUNCHER", "Provides the maintained CLI entrypoint for the FNLLA PHP framework repository.") }
        '^.*\.cmd$' { return @("FNLLA PHP REPOSITORY LAUNCHER", "Provides a Windows launcher for a maintained framework or maintainer workflow command.") }
        default { return @("FNLLA PHP SOURCE FILE", "Belongs to the maintained FNLLA PHP framework repository and delivery toolchain.") }
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

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
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

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This stylesheet belongs to the maintained FNLLA PHP delivery
shell that sits on top of the vendored FNLLA UI runtime.

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

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This script belongs to the maintained repository workflow for
the public MIT-licensed FNLLA PHP framework.

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
REM FNLLA PHP is produced, maintained and distributed by TechAyo LTD.
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
