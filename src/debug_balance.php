<?php
// Debug script for Church Balance

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;

// Check if user is authenticated
if (!AuthenticationManager::getCurrentUser()) {
    die('Authentication failed - User not logged in');
}

// Check if user has finance permissions
if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    die('Permission denied - Finance permission required');
}

echo "<h1>Church Balance Debug Information</h1>\n";

// Test database connection
echo "<h2>Database Connection Test</h2>\n";
try {
    $testQuery = "SELECT 1 as test";
    $result = RunQuery($testQuery);
    if ($result) {
        echo "✓ Database connection successful<br>\n";
    } else {
        echo "✗ Database connection failed<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>\n";
}

// Check if church_balance_cb table exists
echo "<h2>Table Existence Check</h2>\n";
try {
    $tableQuery = "SHOW TABLES LIKE 'church_balance_cb'";
    $result = RunQuery($tableQuery);
    if ($result && mysqli_num_rows($result) > 0) {
        echo "✓ Table 'church_balance_cb' exists<br>\n";
        
        // Check table structure
        $structureQuery = "DESCRIBE church_balance_cb";
        $structureResult = RunQuery($structureQuery);
        echo "<h3>Table Structure:</h3>\n";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
        while ($row = mysqli_fetch_array($structureResult)) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "✗ Table 'church_balance_cb' does not exist<br>\n";
        echo "<p><strong>Solution:</strong> You need to run the database installation script:</p>\n";
        echo "<code>mysql -u [username] -p [database_name] < church-balance-safe.sql</code><br>\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "<br>\n";
}

// Check if church_balance_categories_cbc table exists
try {
    $categoriesQuery = "SHOW TABLES LIKE 'church_balance_categories_cbc'";
    $result = RunQuery($categoriesQuery);
    if ($result && mysqli_num_rows($result) > 0) {
        echo "✓ Table 'church_balance_categories_cbc' exists<br>\n";
    } else {
        echo "✗ Table 'church_balance_categories_cbc' does not exist<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking categories table: " . $e->getMessage() . "<br>\n";
}

// Test getCurrentChurchBalance function
echo "<h2>Function Test</h2>\n";
try {
    function getCurrentChurchBalance() {
        $sSQL = "SELECT cb_Balance FROM church_balance_cb ORDER BY cb_ID DESC LIMIT 1";
        $result = RunQuery($sSQL);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_array($result);
            return floatval($row['cb_Balance']);
        }
        return 0.00;
    }
    
    $balance = getCurrentChurchBalance();
    echo "✓ getCurrentChurchBalance() function works. Current balance: €" . number_format($balance, 2) . "<br>\n";
} catch (Exception $e) {
    echo "✗ getCurrentChurchBalance() function failed: " . $e->getMessage() . "<br>\n";
}

echo "<h2>PHP Error Check</h2>\n";
echo "PHP Version: " . phpversion() . "<br>\n";
echo "Error Reporting Level: " . error_reporting() . "<br>\n";

// Display any recent PHP errors
$errors = error_get_last();
if ($errors) {
    echo "<h3>Last PHP Error:</h3>\n";
    echo "Type: " . $errors['type'] . "<br>\n";
    echo "Message: " . $errors['message'] . "<br>\n";
    echo "File: " . $errors['file'] . "<br>\n";
    echo "Line: " . $errors['line'] . "<br>\n";
}

echo "<p><a href='ChurchBalance.php'>← Back to Church Balance</a></p>\n";
?>
