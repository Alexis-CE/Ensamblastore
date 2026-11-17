@echo off
title EnsamblaStore - guardar y llevar a USB
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sincronizar.ps1" salida
echo.
pause
