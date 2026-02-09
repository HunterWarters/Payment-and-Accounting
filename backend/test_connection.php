<?php
/**
 * Database Connection Test File
 * 
 * Drop this file in your backend folder and access it at:
 * http://localhost/account_payment_system/backend/test_connection.php
 */

// Suppress warnings for cleaner output
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 5px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #004085;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .test-item {
            margin: 20px 0;
        }
        .test-item h3 {
            margin-top: 0;
            color: #333;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Test</h1>
        
        <div class="test-item">
            <h3>Test 1: Loading Configuration File</h3>
            <?php
            if (file_exists('config.php')) {
                echo '<div class="success">✓ config.php file found</div>';
                require_once 'config.php';
                echo '<div class="success">✓ config.php loaded successfully</div>';
            } else {
                echo '<div class="error">✗ config.php not found in ' . __DIR__ . '</div>';
                exit;
            }
            ?>
        </div>

        <div class="test-item">
            <h3>Test 2: Configuration Values</h3>
            <table>
                <tr>
                    <th>Configuration</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>DB_HOST</td>
                    <td><code><?php echo DB_HOST; ?></code></td>
                </tr>
                <tr>
                    <td>DB_USER</td>
                    <td><code><?php echo DB_USER; ?></code></td>
                </tr>
                <tr>
                    <td>DB_PASS</td>
                    <td><code><?php echo empty(DB_PASS) ? '(empty)' : '(set)'; ?></code></td>
                </tr>
                <tr>
                    <td>DB_NAME</td>
                    <td><code><?php echo DB_NAME; ?></code></td>
                </tr>
                <tr>
                    <td>DB_PORT</td>
                    <td><code><?php echo DB_PORT; ?></code></td>
                </tr>
                <tr>
                    <td>DEBUG_MODE</td>
                    <td><code><?php echo DEBUG_MODE ? 'true' : 'false'; ?></code></td>
                </tr>
            </table>
        </div>

        <div class="test-item">
            <h3>Test 3: Attempting Database Connection</h3>
            <?php
            try {
                // Create connection directly without using getDatabaseConnection()
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
                
                // Check connection
                if ($conn->connect_error) {
                    throw new Exception("Connection Error: " . $conn->connect_error);
                }
                
                echo '<div class="success">✓ Successfully connected to MySQL database!</div>';
                
                // Test 4: Check if tables exist
                echo '<div class="test-item"><h3>Test 4: Checking Database Tables</h3>';
                
                $tables = array('students', 'enrollments', 'student_assessments', 'payments');
                $found_tables = array();
                
                $result = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $found_tables[] = $row['TABLE_NAME'];
                    }
                }
                
                if (empty($found_tables)) {
                    echo '<div class="error">✗ No tables found in database. You need to create your database schema.</div>';
                } else {
                    echo '<div class="success">✓ Found ' . count($found_tables) . ' tables in database:</div>';
                    echo '<table><tr><th>Table Name</th><th>Status</th></tr>';
                    foreach ($found_tables as $table) {
                        $status = in_array($table, $tables) ? '✓ Expected' : '⚠ Other';
                        echo '<tr><td><code>' . $table . '</code></td><td>' . $status . '</td></tr>';
                    }
                    echo '</table>';
                }
                
                echo '</div>';
                
                // Test 5: Test with getDatabaseConnection() function
                echo '<div class="test-item"><h3>Test 5: Testing getDatabaseConnection() Function</h3>';
                try {
                    $test_conn = getDatabaseConnection();
                    echo '<div class="success">✓ getDatabaseConnection() works correctly!</div>';
                    $test_conn->close();
                } catch (Exception $e) {
                    echo '<div class="error">✗ getDatabaseConnection() failed: ' . $e->getMessage() . '</div>';
                }
                echo '</div>';
                
                $conn->close();
                
                echo '<div class="info"><strong>Summary:</strong> Your config.php and XAMPP MySQL connection are working correctly! ✓</div>';
                
            } catch (Exception $e) {
                echo '<div class="error"><strong>✗ Connection Failed!</strong></div>';
                echo '<div class="error"><strong>Error:</strong> ' . $e->getMessage() . '</div>';
                echo '<div class="info"><strong>Troubleshooting Tips:</strong><br>';
                echo '1. Make sure MySQL is running in XAMPP Control Panel (should be green)<br>';
                echo '2. Check your .env file for correct DB_HOST, DB_USER, DB_PASS, DB_NAME<br>';
                echo '3. Verify the database "' . DB_NAME . '" exists in MySQL<br>';
                echo '4. Default credentials are: Host=localhost, User=root, Password=(empty)<br>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="test-item">
            <h3>Test 6: PHP & Environment Info</h3>
            <table>
                <tr>
                    <th>Item</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><code><?php echo phpversion(); ?></code></td>
                </tr>
                <tr>
                    <td>MySQL Extension</td>
                    <td><code><?php echo extension_loaded('mysqli') ? '✓ Loaded' : '✗ Not Loaded'; ?></code></td>
                </tr>
                <tr>
                    <td>Current File Location</td>
                    <td><code><?php echo __DIR__; ?></code></td>
                </tr>
                <tr>
                    <td>.env File Status</td>
                    <td><code><?php echo file_exists('.env') ? '✓ Found' : '✗ Not Found'; ?></code></td>
                </tr>
            </table>
        </div>

        <hr style="margin: 30px 0;">
        <h3>Next Steps:</h3>
        <ol>
            <li>If all tests passed: Your database is connected! You can proceed with your application.</li>
            <li>If tests failed: Check the error messages above and the troubleshooting guide.</li>
            <li>Make sure your frontend is accessing the API at: <code>http://localhost/account_payment_system/backend/api.php</code></li>
            <li>You can now start building your payment system!</li>
        </ol>
    </div>
</body>
</html>
