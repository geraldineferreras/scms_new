<?php
// Simple test to debug login endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Login Endpoint Debug Test</h2>";

// Test 1: Check if the file exists and is accessible
echo "<h3>Test 1: File Accessibility</h3>";
$auth_file = 'application/controllers/api/Auth.php';
if (file_exists($auth_file)) {
    echo "✓ Auth.php file exists<br>";
} else {
    echo "✗ Auth.php file not found<br>";
}

// Test 2: Check database configuration
echo "<h3>Test 2: Database Configuration</h3>";
$db_config = 'application/config/database.php';
if (file_exists($db_config)) {
    echo "✓ Database config file exists<br>";
    include $db_config;
    if (isset($db['default'])) {
        echo "✓ Database configuration loaded<br>";
        echo "Host: " . $db['default']['hostname'] . "<br>";
        echo "Database: " . $db['default']['database'] . "<br>";
        echo "Username: " . $db['default']['username'] . "<br>";
    } else {
        echo "✗ Database configuration not found<br>";
    }
} else {
    echo "✗ Database config file not found<br>";
}

// Test 3: Check if CodeIgniter can be loaded
echo "<h3>Test 3: CodeIgniter Loading</h3>";
$index_file = 'index.php';
if (file_exists($index_file)) {
    echo "✓ index.php exists<br>";
} else {
    echo "✗ index.php not found<br>";
}

// Test 4: Check environment
echo "<h3>Test 4: Environment</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Directory: " . getcwd() . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";

// Test 5: Check if we can make a simple request
echo "<h3>Test 5: Simple Request Test</h3>";
$url = 'https://scmsnew-production.up.railway.app/api/login';
$data = json_encode([
    'email' => 'dhvsuadmin@example.com',
    'password' => '123456789'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $http_code . "<br>";
if ($error) {
    echo "CURL Error: " . $error . "<br>";
}
echo "Response: " . $response . "<br>";

// Test 6: Check for common issues
echo "<h3>Test 6: Common Issues Check</h3>";

// Check if .htaccess is working
$htaccess_file = '.htaccess';
if (file_exists($htaccess_file)) {
    echo "✓ .htaccess file exists<br>";
    $htaccess_content = file_get_contents($htaccess_file);
    if (strpos($htaccess_content, 'RewriteEngine On') !== false) {
        echo "✓ RewriteEngine is enabled<br>";
    } else {
        echo "✗ RewriteEngine not found in .htaccess<br>";
    }
} else {
    echo "✗ .htaccess file not found<br>";
}

// Check if required directories exist
$required_dirs = [
    'application',
    'system',
    'application/controllers/api',
    'application/models',
    'application/config'
];

foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ Directory exists: $dir<br>";
    } else {
        echo "✗ Directory missing: $dir<br>";
    }
}

echo "<h3>Recommendations:</h3>";
echo "1. Check Railway logs for detailed error messages<br>";
echo "2. Verify database connection settings<br>";
echo "3. Ensure all required files are present<br>";
echo "4. Check if the environment variables are properly set<br>";
echo "5. Verify the API endpoint URL is correct<br>";
?> 