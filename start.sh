#!/bin/bash
echo "Environment variables:"
env | grep PORT
echo "PORT variable: $PORT"
echo "Starting PHP server on port ${PORT:-8080}"
echo "Working directory: $(pwd)"
echo "Files in directory:"
ls -la
echo "Starting PHP built-in server..."
php -S 0.0.0.0:${PORT:-8080} -t . index.php 