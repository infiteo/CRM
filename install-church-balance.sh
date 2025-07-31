#!/bin/bash

# Church Balance Installation Script for ChurchCRM
# This script adds the church balance functionality to your ChurchCRM installation

echo "ChurchCRM Church Balance Installation Script"
echo "============================================="
echo ""

# Get the current directory (should be the ChurchCRM src directory)
CHURCHCRM_ROOT=$(pwd)

# Check if we're in the right directory
if [ ! -f "Include/Config.php" ] && [ ! -f "Include/Config.php.example" ]; then
    echo "Error: This script must be run from the ChurchCRM src directory."
    echo "Please navigate to your ChurchCRM/src directory and run this script again."
    exit 1
fi

echo "ChurchCRM installation directory: $CHURCHCRM_ROOT"
echo ""

# Check if MySQL is accessible
read -p "Please enter your MySQL/MariaDB database name: " DB_NAME
read -p "Please enter your MySQL/MariaDB username: " DB_USER
read -s -p "Please enter your MySQL/MariaDB password: " DB_PASS
echo ""
read -p "Please enter your MySQL/MariaDB host (default: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

echo ""
echo "Testing database connection..."

# Test database connection
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Error: Could not connect to the database. Please check your credentials."
    exit 1
fi

echo "Database connection successful!"
echo ""

# Run the database upgrade script
echo "Installing church balance database tables..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < mysql/upgrade/church-balance.sql

if [ $? -eq 0 ]; then
    echo "Database tables installed successfully!"
else
    echo "Error: Failed to install database tables."
    exit 1
fi

echo ""
echo "Church Balance functionality has been successfully installed!"
echo ""
echo "You can now access the Church Balance features through:"
echo "1. Main menu: Deposit > Church Balance"
echo "2. Admin menu: Deposit > Admin > Balance Categories"
echo "3. Financial Reports: Add 'Church Balance Report' to your reports"
echo ""
echo "Features included:"
echo "- Track church income and expenses"
echo "- Categorize transactions"
echo "- View current balance and transaction history"
echo "- Generate balance reports (PDF/CSV)"
echo "- Manage balance categories (Admin only)"
echo ""
echo "Installation complete! Please refresh your ChurchCRM interface."
