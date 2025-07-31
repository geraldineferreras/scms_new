#!/bin/bash
echo "=== RAILWAY DEPLOYMENT DEBUG ==="
echo "Environment variables:"
env | sort
echo ""
echo "PORT variable: $PORT"
echo "Starting PHP server on port ${PORT:-8080}"
echo "Working directory: $(pwd)"
echo "Files in directory:"
ls -la
echo ""
echo "Starting PHP built-in server..."
echo "Command: php -S 0.0.0.0:${PORT:-8080} -t . index.php"
exec php -S 0.0.0.0:${PORT:-8080} -t . index.php 