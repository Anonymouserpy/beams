:: AUTO IMPORT SQL IF DB DOESNT EXISTS


@echo off

:: === CONFIG ===
set MYSQL_BIN="C:\xampp\mysql\bin"
set MYSQL_USER=root
set MYSQL_PASS=
set DB_NAME=beams
set SQL_FILE=beams.sql

:: === BUILD PASSWORD PARAM ===
set MYSQL_AUTH=-u%MYSQL_USER%

if not "%MYSQL_PASS%"=="" (
    set MYSQL_AUTH=%MYSQL_AUTH% -p%MYSQL_PASS%
)

:: === CREATE DB IF NOT EXISTS ===
%MYSQL_BIN%\mysql.exe %MYSQL_AUTH% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME%;"

:: === IMPORT SQL ===
%MYSQL_BIN%\mysql.exe %MYSQL_AUTH% %DB_NAME% < %SQL_FILE%

echo Done.


:: ----------
:: RUN WEBSOCKET SERVER

start "" "http://localhost/beams/"


echo Starting Websocket Server

C:\xampp\php\php.exe Websocket_server.php




pause