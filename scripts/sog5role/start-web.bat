@echo off
title Smart SOG5 Web Sunucu
cd /d "%~dp0"
C:\xampp\node-v24.15.0-win-x64\node.exe "%~dp0web-server.js"
pause
