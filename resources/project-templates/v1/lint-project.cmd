@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: lint-project.cmd
REM Purpose: Runs syntax lint and integrated UI surface validation for this project.
REM ============================================================================
setlocal
php "%~dp0scripts\lint.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-fnlla-runtime.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-version-manifest.php" || exit /b %ERRORLEVEL%
