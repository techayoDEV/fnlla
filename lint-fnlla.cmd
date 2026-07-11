@echo off
REM ============================================================================
REM FNLLA REPOSITORY LAUNCHER
REM File: lint-fnlla.cmd
REM Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
REM FNLLA is produced, maintained and distributed by TechAyo LTD.
REM Purpose: Provides a Windows launcher for a maintained framework or maintainer workflow command.
REM ============================================================================
setlocal
php "%~dp0scripts\lint.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-fnlla-runtime.php" || exit /b %ERRORLEVEL%
