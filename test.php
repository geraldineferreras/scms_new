<?php
echo "PHP is working on Railway!";
echo "<br>Environment: " . (getenv('ENVIRONMENT') ?: 'not set');
echo "<br>Port: " . (getenv('PORT') ?: 'not set');
echo "<br>Database Host: " . (getenv('DB_HOST') ?: 'not set');
?>
