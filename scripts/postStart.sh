#!/bin/bash
#
# scripts/postStart.sh
#
# FPP calls this automatically after the FPPD daemon finishes starting up.
# No registration needed — FPP finds it by this exact filename.

PLUGIN_PHP="$(dirname "$(readlink -f "$0")")/../commands/TelegramFPPStarted.php"

if [ -f "$PLUGIN_PHP" ]; then
    /usr/bin/php "$PLUGIN_PHP" >/dev/null 2>&1 &
fi
