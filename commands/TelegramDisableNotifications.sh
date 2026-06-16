#!/bin/bash
# FPP Command: Telegram - Disable Notifications
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
/usr/bin/php "${SCRIPT_DIR}/TelegramDisableNotifications.php" "$@"
