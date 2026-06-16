#!/usr/bin/php
<?php
/**
 * Called when FPP daemon starts.
 * Installed as a startup hook by fpp_install.sh.
 */

require_once(dirname(__FILE__) . "/../scripts/telegramFunctions.php");

$cfg = loadTelegramConfig();

if ($cfg['notifications_enabled'] !== '1') {
    telegramLog('FPP startup notification suppressed — notifications disabled', 'INFO');
    exit(0);
}

if ($cfg['notify_fpp_start'] !== '1') {
    exit(0);
}

$message = telegramApplyVars($cfg['msg_fpp_start']);
telegramSend($cfg, $message, 'fpp_start');
