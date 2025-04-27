#!/bin/bash
set -e

# Start supervisor in the background
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf &

# Wait a moment for supervisor to start
sleep 2

# Check if supervisor is running
if ! pgrep supervisord > /dev/null; then
    echo "Error: supervisord failed to start"
    exit 1
fi

# Log the status of the queue worker
echo "Supervisor status:"
supervisorctl status

# Start FrankenPHP
exec php artisan octane:frankenphp --host=0.0.0.0 --port=80 --admin-port=2019
