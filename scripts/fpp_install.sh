#!/bin/bash

# fpp-telegram install script
# Called by FPP after git pull during install and Update Now.
# FPP handles all git operations — this script does post-pull setup only.
# File permissions are stored in git (git update-index --chmod=+x) so no
# chmod is needed here.

set -e

BASEDIR=$(dirname "$0")
cd "$BASEDIR" || exit
cd ..

MEDIA_DIR="/home/fpp/media"
LOG_FILE="${MEDIA_DIR}/logs/fpp-telegram.log"
PLUGIN_VERSION=$(grep -o '"pluginVersion"[^,]*' pluginInfo.json \
    | grep -o '"[0-9][^"]*"' | tr -d '"' 2>/dev/null || echo "unknown")

mkdir -p "${MEDIA_DIR}/config" 2>/dev/null || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] [INFO] Plugin installed/updated — version ${PLUGIN_VERSION}" \
    >> "$LOG_FILE" 2>/dev/null || true

if [ -f "${FPPDIR}/scripts/ManageApacheContentPolicy.sh" ]; then
    "${FPPDIR}/scripts/ManageApacheContentPolicy.sh" add connect-src https://api.telegram.org 2>/dev/null || true
fi

if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
    setSetting restartFlag 1
fi
