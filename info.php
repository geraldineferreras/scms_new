<?php
echo "PHP is working!";
echo "<br>Server: " . $_SERVER['SERVER_NAME'];
echo "<br>Port: " . $_SERVER['SERVER_PORT'];
echo "<br>Request URI: " . $_SERVER['REQUEST_URI'];
echo "<br>Environment: " . (getenv('ENVIRONMENT') ?: 'not set');
echo "<br>Database Host: " . (getenv('DB_HOST') ?: 'not set');
echo "<br>Database Password: " . (getenv('DB_PASSWORD') ? 'set' : 'not set');
?> 