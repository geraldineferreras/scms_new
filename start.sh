#!/bin/bash
echo "Starting PHP server on port $PORT"
echo "Working directory: $(pwd)"
echo "Files in directory:"
ls -la
echo "Starting PHP built-in server..."
php -S 0.0.0.0:$PORT -t . index.php 