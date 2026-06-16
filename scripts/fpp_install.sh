#!/bin/bash

# fpp-telegram install script
# Called by FPP after git pull during install and Update Now.
# FPP handles all git operations — this script does post-pull setup only.

set -e

BASEDIR=$(dirname "$0")
cd "$BASEDIR" || exit
cd ..
PLUGIN_DIR="$(pwd)"

MEDIA_DIR="/home/fpp/media"
SETTINGS_FILE="${MEDIA_DIR}/config/plugin.fpp-telegram.json"
LOG_FILE="${MEDIA_DIR}/logs/fpp-telegram.log"

log_entry() {
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [INFO] $1" >> "$LOG_FILE" 2>/dev/null || true
}

echo ""
echo "============================================================"
echo " fpp-telegram Install/Update"
echo "============================================================"
echo ""
echo "[INFO] Plugin directory : ${PLUGIN_DIR}"
echo "[INFO] Settings file    : ${SETTINGS_FILE}"
PLUGIN_VERSION=$(grep -o '"pluginVersion"[^,]*' "${PLUGIN_DIR}/pluginInfo.json" \
    | grep -o '"[0-9][^"]*"' | tr -d '"' 2>/dev/null || echo "unknown")
echo "[INFO] Plugin version   : ${PLUGIN_VERSION}"
echo "[INFO] FPP directory    : ${FPPDIR}"
echo ""

# Configure git for this repository:
# 1. safe.directory — allows root to run git in this fpp-owned directory
# 2. core.fileMode false — prevents chmod +x from being tracked as a
#    modification, which would block future git pulls after the install
#    script runs chmod on plugin files
echo "[INFO] Configuring git for FPP upgrades..."
git config --global --add safe.directory "${PLUGIN_DIR}" 2>/dev/null || {
    echo "[WARN] Could not configure git safe.directory (non-fatal)"
}
git -C "${PLUGIN_DIR}" config core.fileMode false 2>/dev/null || {
    echo "[WARN] Could not set core.fileMode (non-fatal)"
}
echo "[OK]   git configuration set."

echo "[INFO] Creating persistent config directory..."
mkdir -p "${MEDIA_DIR}/config" || {
    echo "[ERROR] Could not create ${MEDIA_DIR}/config"
    exit 1
}
chmod 755 "${MEDIA_DIR}/config"
echo "[OK]   Config directory ready: ${MEDIA_DIR}/config"

if [ -f "$SETTINGS_FILE" ]; then
    echo "[OK]   Existing settings preserved: ${SETTINGS_FILE}"
else
    echo "[INFO] No existing settings — defaults will apply on first use."
fi

echo "[INFO] Setting file permissions..."
chmod +x "${PLUGIN_DIR}/callbacks.php"            2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/postStart.sh"     2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/preStop.sh"       2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/sendTelegram.sh"  2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/fpp_uninstall.sh" 2>/dev/null || true
chmod +x "${PLUGIN_DIR}/commands/"*.php           2>/dev/null || true
chmod +x "${PLUGIN_DIR}/commands/"*.sh            2>/dev/null || true
echo "[OK]   File permissions set."

echo "[INFO] Configuring Apache Content Security Policy..."
if [ -f "${FPPDIR}/scripts/ManageApacheContentPolicy.sh" ]; then
    "${FPPDIR}/scripts/ManageApacheContentPolicy.sh" add connect-src https://api.telegram.org || {
        echo "[WARN] CSP configuration failed (non-fatal)"
    }
    echo "[OK]   CSP configured for api.telegram.org"
else
    echo "[WARN] ManageApacheContentPolicy.sh not found — skipping CSP (FPP < 9)"
fi

log_entry "Plugin installed/updated — version ${PLUGIN_VERSION}"

# Source common scripts and set restart flag
if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
    setSetting restartFlag 1
fi

echo ""
echo "============================================================"
echo " fpp-telegram installation complete."
echo " Restart FPPD when prompted to activate all changes."
echo " Configure at: Content > Telegram Notifications"
echo "============================================================"
echo ""
