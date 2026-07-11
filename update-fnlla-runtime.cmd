@echo off
REM ============================================================================
REM FNLLA REPOSITORY LAUNCHER
REM File: update-fnlla-runtime.cmd
REM Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
REM FNLLA is produced, maintained and distributed by TechAyo LTD.
REM Purpose: Provides a Windows launcher for a maintained framework or maintainer workflow command.
REM ============================================================================
setlocal

set "SCRIPT_DIR=%~dp0"
set "SYNC_SCRIPT=%SCRIPT_DIR%scripts\sync-fnlla-runtime.ps1"

if not exist "%SYNC_SCRIPT%" (
    echo Missing sync script: "%SYNC_SCRIPT%"
    exit /b 1
)

powershell -ExecutionPolicy Bypass -File "%SYNC_SCRIPT%" %*
exit /b %ERRORLEVEL%
