@echo off
title EnsamblaStore - traer de USB
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sincronizar.ps1" llegada
echo.
pause
