@echo off
title BEAMS Database Setup Script

:: === CONFIG ===
set MYSQL_BIN="C:\xampp\mysql\bin"
set MYSQL_USER=root
set MYSQL_PASS=
set DB_NAME=beams
set SQL_FILE=beams.sql
set WEBSOCKET_FILE=Connection\websocket_server.php

:: Colors for output (Windows 10+)
set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "BLUE=[94m"
set "NC=[0m"

echo =========================================
echo BEAMS Database Setup Script
echo =========================================

:: === CHECK IF SQL FILE EXISTS ===
if not exist "%SQL_FILE%" (
    echo %RED%ERROR: %SQL_FILE% not found in current directory!%NC%
    echo Current directory: %cd%
    pause
    exit /b 1
)

:: === BUILD MYSQL COMMAND ===
set MYSQL_CMD=%MYSQL_BIN%\mysql.exe -u%MYSQL_USER%
if not "%MYSQL_PASS%"=="" set MYSQL_CMD=%MYSQL_CMD% -p%MYSQL_PASS%

:: === CHECK IF MYSQL IS RUNNING ===
echo %YELLOW%Checking MySQL connection...%NC%
%MYSQL_CMD% -e "SELECT 1" > nul 2>&1
if errorlevel 1 (
    echo %RED%ERROR: Cannot connect to MySQL. Please start XAMPP first:%NC%
    echo   Start XAMPP Control Panel and start MySQL
    pause
    exit /b 1
)
echo %GREEN%✓ MySQL is running%NC%

:: === CHECK IF DATABASE HAS TABLES (NOT JUST EXISTS) ===
set TABLE_COUNT=0
for /f "skip=1" %%i in ('%MYSQL_CMD% %DB_NAME% -e "SHOW TABLES;" 2^>nul ^| find /c /v ""') do set TABLE_COUNT=%%i

if %TABLE_COUNT% gtr 0 (
    :: Database has tables - SKIP IMPORT
    echo %GREEN%✓ Database '%DB_NAME%' already has tables.%NC%
    echo %GREEN%✓ Skipping database import to avoid errors.%NC%
    echo %GREEN%✓ Database contains %TABLE_COUNT% tables%NC%
) else (
    :: Database doesn't exist or has no tables - IMPORT
    echo %YELLOW%Setting up new database...%NC%
    
    :: Drop if exists (to ensure clean slate)
    %MYSQL_CMD% -e "DROP DATABASE IF EXISTS %DB_NAME%;" 2>nul
    
    :: Create fresh database
    echo %BLUE%Creating database %DB_NAME%...%NC%
    %MYSQL_CMD% -e "CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    if errorlevel 1 (
        echo %RED%ERROR: Failed to create database.%NC%
        pause
        exit /b 1
    )
    echo %GREEN%✓ Database %DB_NAME% created%NC%
    
    :: Import SQL
    echo %YELLOW%Importing %SQL_FILE% into %DB_NAME%...%NC%
    %MYSQL_CMD% %DB_NAME% < %SQL_FILE%
    
    if errorlevel 1 (
        echo %RED%ERROR: Failed to import SQL file.%NC%
        pause
        exit /b 1
    )
    echo %GREEN%✓ Database imported successfully!%NC%
)

:: === OPEN BROWSER ===
echo %YELLOW%Opening application in browser...%NC%
start "" "http://localhost/beams/"

:: === CHECK AND RUN WEBSOCKET SERVER ===
if exist "%WEBSOCKET_FILE%" (
    echo %YELLOW%Starting WebSocket Server...%NC%
    
    :: Kill any existing websocket server processes
    taskkill /F /IM php.exe /FI "WINDOWTITLE eq websocket_server*" 2>nul
    
    :: Start websocket server in new window
    start "WebSocket Server" cmd /c "C:\xampp\php\php.exe %WEBSOCKET_FILE% & pause"
    
    echo %GREEN%✓ WebSocket Server started%NC%
) else if exist "websocket_server.php" (
    echo %YELLOW%Starting WebSocket Server...%NC%
    
    :: Kill any existing websocket server processes
    taskkill /F /IM php.exe /FI "WINDOWTITLE eq websocket_server*" 2>nul
    
    :: Start websocket server in new window
    start "WebSocket Server" cmd /c "C:\xampp\php\php.exe websocket_server.php & pause"
    
    echo %GREEN%✓ WebSocket Server started%NC%
) else (
    echo %YELLOW%⚠ websocket_server.php not found in Connection folder or current directory%NC%
)

echo.
echo =========================================
echo %GREEN%✅ SETUP COMPLETE!%NC%
echo =========================================
echo %GREEN%Application: http://localhost/beams/%NC%
echo =========================================
echo.
pause