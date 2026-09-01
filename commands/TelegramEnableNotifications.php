#!/usr/bin/php
<?php
/**
 * FPP Command: Telegram - Enable Notifications
 * Sets notifications_enabled = '1' and sends a status message.
 * This command intentionally bypasses the master notifications gate
 * so the status-change message always reaches Telegram.
 */

require_once(dirname(__FILE__) . "/../scripts/telegramFunctions.php");

$cfg = loadTelegramConfig();

// No-op if already enabled
if ($cfg['notifications_enabled'] === '1') {
    exit(0);
}

// Enable and persist
$cfg['notifications_enabled'] = '1';
$settingsFile = getTelegramPluginSettingsFile();
file_put_contents($settingsFile, json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
telegramLog('Notifications ENABLED via FPP command');

// Send status message — no master check intentionally
$message = telegramApplyVars($cfg['msg_notifications_enabled']);
telegramSend($cfg, $message, 'notifications_enabled');
