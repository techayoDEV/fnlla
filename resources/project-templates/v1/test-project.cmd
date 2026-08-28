@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: test-project.cmd
REM Purpose: Runs the local FNLLA project test suite for this application.
REM ============================================================================
setlocal
php "%~dp0scripts\test.php" %*
