<?php
/**
 * CodeIgniter Router for PHP Built-in Server
 * This file handles routing for CodeIgniter 3 when using PHP's built-in server
 */

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Check if the file exists
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Serve the file directly
}

// For CodeIgniter routing, always serve index.php
require_once __DIR__ . '/index.php'; 