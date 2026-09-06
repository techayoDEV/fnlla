@echo off
REM ============================================================================
REM FNLLA REPOSITORY LAUNCHER
REM File: scripts\windows\test-fnlla.cmd
REM Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
REM FNLLA is produced, maintained and distributed by TechAyo LTD.
REM Purpose: Provides a Windows launcher for a maintained framework or maintainer workflow command.
REM ============================================================================
setlocal
set "FNLLA_ROOT=%~dp0..\..\"
php "%FNLLA_ROOT%scripts\test.php" %*
