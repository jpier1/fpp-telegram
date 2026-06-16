#!/usr/bin/php
<?php
/**
 * Called when FPP daemon stops.
 * Installed as a shutdown hook by fpp_install.sh.
 */

require_once(dirname(__FILE__) . "/../scripts/telegramFunctions.php");

$cfg = loadTelegramConfig();

if ($cfg['notifications_enabled'] !== '1') {
    telegramLog('FPP shutdown notification suppressed — notifications disabled', 'INFO');
    exit(0);
}

if ($cfg['notify_fpp_stop'] !== '1') {
    exit(0);
}

$message = telegramApplyVars($cfg['msg_fpp_stop']);
telegramSend($cfg, $message, 'fpp_stop');
