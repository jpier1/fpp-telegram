#!/bin/bash
# FPP Command: Telegram - Enable Notifications
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
/usr/bin/php "${SCRIPT_DIR}/TelegramEnableNotifications.php" "$@"
