#!/bin/bash
#
# fpp-telegram/scripts/fpp_uninstall.sh
#
# Called automatically by FPP when the plugin is uninstalled.
# This is the ONLY place settings are deleted — plugin updates never run this.

FPPHOME="${FPPHOME:-/home/fpp}"
MEDIA_DIR="${FPPHOME}/media"
SETTINGS_FILE="${MEDIA_DIR}/config/plugin.fpp-telegram.json"
LOG_FILE="${MEDIA_DIR}/logs/fpp-telegram.log"

log_entry() {
    if [ -d "$(dirname "$LOG_FILE")" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] [INFO] $1" >> "$LOG_FILE"
    fi
}

# Remove persistent settings so a fresh reinstall starts clean
if [ -f "$SETTINGS_FILE" ]; then
    rm -f "$SETTINGS_FILE"
    log_entry "Plugin uninstalled — settings removed (${SETTINGS_FILE})"
else
    log_entry "Plugin uninstalled — no settings file found"
fi

echo "fpp-telegram: Uninstall complete."
