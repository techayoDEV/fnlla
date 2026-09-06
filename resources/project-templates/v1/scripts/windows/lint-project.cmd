@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: scripts\windows\lint-project.cmd
REM Purpose: Runs syntax lint and integrated UI surface validation for this project.
REM ============================================================================
setlocal
set "FNLLA_ROOT=%~dp0..\..\"
php "%FNLLA_ROOT%scripts\lint.php" || exit /b %ERRORLEVEL%
php "%FNLLA_ROOT%scripts\validate-fnlla-runtime.php" || exit /b %ERRORLEVEL%
php "%FNLLA_ROOT%scripts\validate-version-manifest.php" || exit /b %ERRORLEVEL%
