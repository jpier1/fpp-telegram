#!/usr/bin/php
<?php
/**
 * FPP Command: Telegram - Disable Notifications
 * Sets notifications_enabled = '0' and sends a status message.
 * This command intentionally bypasses the master notifications gate
 * so the status-change message always reaches Telegram.
 */

require_once(dirname(__FILE__) . "/../scripts/telegramFunctions.php");

$cfg = loadTelegramConfig();

// No-op if already disabled
if ($cfg['notifications_enabled'] === '0') {
    exit(0);
}

// Disable and persist
$cfg['notifications_enabled'] = '0';
$settingsFile = getTelegramPluginSettingsFile();
file_put_contents($settingsFile, json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
telegramLog('Notifications DISABLED via FPP command');

// Send status message — no master check intentionally (must still reach Telegram)
$message = telegramApplyVars($cfg['msg_notifications_disabled']);
telegramSend($cfg, $message, 'notifications_disabled');
