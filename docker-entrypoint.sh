#!/bin/bash
set -e

# SeedDMS scheduler script path
SCHEDULER_CLI="/var/seeddms/seeddms60x/www/utils/seeddms-schedulercli"
CONFIG_FILE="/var/seeddms/seeddms60x/conf/settings.xml"

# Scheduler interval in seconds (default: 300 = 5 minutes)
# Can be overridden via SEEDDMS_SCHEDULER_INTERVAL environment variable
SCHEDULER_INTERVAL=${SEEDDMS_SCHEDULER_INTERVAL:-300}

# Function to run the scheduler
run_scheduler() {
    if [ -f "$SCHEDULER_CLI" ] && [ -f "$CONFIG_FILE" ]; then
        "$SCHEDULER_CLI" --config "$CONFIG_FILE" --mode=run 2>&1 || true
    fi
}

# Start scheduler in background loop
if [ -f "$SCHEDULER_CLI" ]; then
    echo "Starting SeedDMS scheduler (interval: ${SCHEDULER_INTERVAL}s)..."
    (
        while true; do
            sleep "$SCHEDULER_INTERVAL"
            run_scheduler
        done
    ) &
    SCHEDULER_PID=$!
    echo "Scheduler started (PID: $SCHEDULER_PID)"
else
    echo "Warning: Scheduler CLI not found at $SCHEDULER_CLI"
fi

# Start Apache in foreground (this blocks)
exec apache2-foreground

