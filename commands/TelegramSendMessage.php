#!/usr/bin/php
<?php
/**
 * FPP Command: Telegram - Send Message
 * Args: $argv[1] = message text
 */

require_once(dirname(__FILE__) . "/../scripts/telegramFunctions.php");

$cfg = loadTelegramConfig();

// Master notifications gate
if ($cfg['notifications_enabled'] !== '1') {
    telegramLog('Send Message command suppressed — notifications disabled', 'INFO');
    exit(0);
}

$message = isset($argv[1]) ? trim($argv[1]) : '';
if ($message === '') {
    fwrite(STDERR, "fpp-telegram: No message provided.\n");
    exit(1);
}

$message = telegramApplyVars($message);
telegramSend($cfg, $message, 'manual_send');
