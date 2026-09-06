@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: scripts\windows\test-project.cmd
REM Purpose: Runs the local FNLLA project test suite for this application.
REM ============================================================================
setlocal
set "FNLLA_ROOT=%~dp0..\..\"
php "%FNLLA_ROOT%scripts\test.php" %*
