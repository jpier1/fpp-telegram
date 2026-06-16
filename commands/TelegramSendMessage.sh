#!/bin/bash
# FPP Command: Telegram - Send Message
# Wrapper that calls the PHP implementation via the system PHP binary,
# avoiding shebang path issues across different Pi OS/PHP versions.
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
/usr/bin/php "${SCRIPT_DIR}/TelegramSendMessage.php" "$@"
