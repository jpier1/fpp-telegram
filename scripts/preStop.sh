#!/bin/bash
#
# scripts/preStop.sh
#
# FPP calls this automatically before the FPPD daemon begins shutting down.
# We use preStop (not postStop) so the network is still up when we send.
# No registration needed — FPP finds it by this exact filename.

PLUGIN_PHP="$(dirname "$(readlink -f "$0")")/../commands/TelegramFPPStopped.php"

if [ -f "$PLUGIN_PHP" ]; then
    /usr/bin/php "$PLUGIN_PHP" >/dev/null 2>&1
    # Note: no & here — we wait for the message to send before FPP continues shutdown
fi
