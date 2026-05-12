#!/bin/bash

# === CONFIG ===
MYSQL_USER="root"
MYSQL_PASS=""
DB_NAME="beams"
SQL_FILE="beams.sql"
MYSQL_SOCKET="/opt/lampp/var/mysql/mysql.sock"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}BEAMS Database Setup Script${NC}"
echo -e "${BLUE}=========================================${NC}"

# === CHECK IF SQL FILE EXISTS ===
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}ERROR: $SQL_FILE not found in current directory!${NC}"
    echo "Current directory: $(pwd)"
    exit 1
fi

# === BUILD MYSQL COMMAND ===
MYSQL_CMD="mysql -u$MYSQL_USER --socket=$MYSQL_SOCKET"

if [ ! -z "$MYSQL_PASS" ]; then
    MYSQL_CMD="$MYSQL_CMD -p$MYSQL_PASS"
fi

# === CHECK IF MYSQL IS RUNNING ===
echo -e "${YELLOW}Checking MySQL connection...${NC}"
if ! $MYSQL_CMD -e "SELECT 1" > /dev/null 2>&1; then
    echo -e "${RED}ERROR: Cannot connect to MySQL. Please start XAMPP first:${NC}"
    echo "  sudo /opt/lampp/lampp start"
    exit 1
fi
echo -e "${GREEN}✓ MySQL is running${NC}"

# === CHECK IF DATABASE HAS TABLES (NOT JUST EXISTS) ===
TABLE_COUNT=$($MYSQL_CMD $DB_NAME -e "SHOW TABLES;" 2>/dev/null | wc -l)

if [ $? -eq 0 ] && [ $TABLE_COUNT -gt 1 ]; then
    # Database has tables - SKIP IMPORT
    echo -e "${GREEN}✓ Database '$DB_NAME' already has tables.${NC}"
    echo -e "${GREEN}✓ Skipping database import to avoid errors.${NC}"
    echo -e "${GREEN}✓ Database contains $((TABLE_COUNT-1)) tables${NC}"
else
    # Database doesn't exist or has no tables - IMPORT
    echo -e "${YELLOW}Setting up new database...${NC}"
    
    # Drop if exists (to ensure clean slate)
    $MYSQL_CMD -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
    
    # Create fresh database
    echo -e "${BLUE}Creating database $DB_NAME...${NC}"
    $MYSQL_CMD -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}ERROR: Failed to create database.${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Database $DB_NAME created${NC}"
    
    # Import SQL
    echo -e "${YELLOW}Importing $SQL_FILE into $DB_NAME...${NC}"
    $MYSQL_CMD $DB_NAME < $SQL_FILE
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database imported successfully!${NC}"
    else
        echo -e "${RED}ERROR: Failed to import SQL file.${NC}"
        exit 1
    fi
fi

# === OPEN BROWSER ===
echo -e "${YELLOW}Opening application in browser...${NC}"
if command -v xdg-open > /dev/null; then
    xdg-open "http://localhost/beams/" 2>/dev/null || echo "Please open http://localhost/beams/ manually"
else
    echo "Please open http://localhost/beams/ in your browser"
fi

# === CHECK AND RUN WEBSOCKET SERVER ===
# Check both current directory and Connection folder
if [ -f "Connection/websocket_server.php" ]; then
    WEBSOCKET_PATH="Connection/websocket_server.php"
    echo -e "${YELLOW}Starting WebSocket Server from Connection folder...${NC}"
elif [ -f "websocket_server.php" ]; then
    WEBSOCKET_PATH="websocket_server.php"
    echo -e "${YELLOW}Starting WebSocket Server...${NC}"
else
    WEBSOCKET_PATH=""
    echo -e "${YELLOW}⚠ websocket_server.php not found in current directory or Connection folder${NC}"
fi

if [ -n "$WEBSOCKET_PATH" ]; then
    if command -v php > /dev/null; then
        pkill -f "websocket_server.php" 2>/dev/null
        php "$WEBSOCKET_PATH" > websocket.log 2>&1 &
        echo -e "${GREEN}✓ WebSocket Server started with PID: $!${NC}"
    else
        echo -e "${YELLOW}Using XAMPP PHP...${NC}"
        /opt/lampp/bin/php "$WEBSOCKET_PATH" > websocket.log 2>&1 &
        echo -e "${GREEN}✓ WebSocket Server started with PID: $!${NC}"
    fi
fi

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ SETUP COMPLETE!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}Application: http://localhost/beams/${NC}"
echo -e "${GREEN}=========================================${NC}"    