<?php
/**
 * Shared functions for fpp-telegram plugin command scripts.
 * Included by all TelegramXxx.php command scripts and plugin_setup.php.
 */

//-----------------------------------------------------------------------------
// Logging

function getTelegramLogFile() {
    // FPPHOME=/home/fpp in FPP's environment; media dir is $FPPHOME/media.
    // Fallback handles the case where FPPHOME is not set (e.g. unit testing).
    $fpphome = getenv('FPPHOME') ?: '/home/fpp';
    return $fpphome . '/media/logs/fpp-telegram.log';
}

/**
 * Write a timestamped entry to the fpp-telegram log.
 * Caps the log at 512 KB by discarding the oldest half when exceeded.
 *
 * @param string $message  Log message
 * @param string $level    INFO | WARN | ERROR
 */
function telegramLog($message, $level = 'INFO') {
    $logFile = getTelegramLogFile();
    $logDir  = dirname($logFile);

    if (!is_dir($logDir)) {
        return; // FPP logs directory not present — silently skip
    }

    // Size cap: truncate to newest ~256 KB when file exceeds 512 KB
    if (file_exists($logFile) && filesize($logFile) > 524288) {
        $content = file_get_contents($logFile);
        $content = substr($content, -262144);
        // Trim to the next complete line so we don't start mid-entry
        $pos = strpos($content, "\n");
        if ($pos !== false) {
            $content = substr($content, $pos + 1);
        }
        file_put_contents($logFile, $content, LOCK_EX);
    }

    $line = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

//-----------------------------------------------------------------------------
// Settings

function getTelegramPluginSettingsFile() {
    // Store settings in FPP's persistent user-data area so they survive
    // plugin updates. $FPPHOME = /home/fpp; mediaDirectory = /home/fpp/media.
    $fpphome = getenv('FPPHOME') ?: '/home/fpp';
    return $fpphome . '/media/config/plugin.fpp-telegram.json';
}

function loadTelegramConfig() {
    $file = getTelegramPluginSettingsFile();
    $defaults = array(
        'bot_token'              => '',
        'chat_id'                => '',
        'notify_playlist_start'  => '0',
        'notify_playlist_end'    => '0',
        'notify_sequence_start'  => '0',
        'notify_sequence_end'    => '0',
        'notify_fpp_start'       => '0',
        'notify_fpp_stop'        => '0',
        'msg_playlist_start'     => 'FPP: Playlist "%PLAYLIST%" started.',
        'msg_playlist_end'       => 'FPP: Playlist "%PLAYLIST%" ended.',
        'msg_sequence_start'     => 'FPP: Sequence "%SEQUENCE%" started.',
        'msg_sequence_end'       => 'FPP: Sequence "%SEQUENCE%" stopped.',
        'msg_fpp_start'          => 'FPP: Falcon Player started.',
        'msg_fpp_stop'           => 'FPP: Falcon Player stopped.',
        'notifications_enabled'  => '1',
        'proxy_url'              => '',
        'disable_web_preview'    => '1',
        'msg_notifications_enabled'  => 'FPP Telegram notifications have been ENABLED.',
        'msg_notifications_disabled' => 'FPP Telegram notifications have been DISABLED.',
    );
    if (file_exists($file)) {
        $loaded = json_decode(file_get_contents($file), true);
        if (is_array($loaded)) {
            $merged = array_merge($defaults, $loaded);
            // If a message template was saved as an empty string, restore the default
            // so command scripts never dispatch a blank Telegram message.
            foreach (['msg_fpp_start','msg_fpp_stop',
                      'msg_playlist_start','msg_playlist_end',
                      'msg_sequence_start','msg_sequence_end',
                      'msg_notifications_enabled','msg_notifications_disabled'] as $key) {
                if (isset($merged[$key]) && trim($merged[$key]) === '') {
                    $merged[$key] = $defaults[$key];
                }
            }
            return $merged;
        }
    }
    return $defaults;
}

//-----------------------------------------------------------------------------
// Variable substitution

/**
 * Escape a single substituted value for Telegram's HTML parse mode.
 * Only the VALUE being inserted is escaped — the template itself (written
 * by the user with intentional markup) is left untouched.
 *
 * @param string $value The raw value to escape (e.g. a playlist name)
 * @return string
 */
function telegramEscapeValue($value) {
    // Telegram HTML only needs & < > escaped (not quotes — not in attributes)
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
}

/**
 * Substitute template variables and escape the inserted values for
 * Telegram's HTML parse mode so special characters in playlist/sequence
 * names never break message formatting.
 *
 * @param string $template Message template with %VAR% placeholders
 * @param array  $vars     Key→value pairs to substitute (e.g. ['playlist' => 'My Show'])
 * @return string          Ready-to-send message string
 */
function telegramApplyVars($template, $vars = array()) {
    $message = $template;
    foreach ($vars as $key => $value) {
        $message = str_replace('%' . strtoupper($key) . '%', telegramEscapeValue((string)$value), $message);
    }
    $message = str_replace('%HOSTNAME%', telegramEscapeValue(gethostname()),        $message);
    $message = str_replace('%DATETIME%', telegramEscapeValue(date('Y-m-d H:i:s')), $message);
    return $message;
}

//-----------------------------------------------------------------------------
// Send

/**
 * Send a Telegram message.
 *
 * @param array  $cfg        Plugin config from loadTelegramConfig()
 * @param string $message    Message text (variables already substituted)
 * @param string $source     Label for the log entry, e.g. 'playlist_start', 'test'
 * @param bool   $background When true, fires the send in the background and returns
 *                           immediately (used by callbacks.php so FPP is not blocked).
 *                           When false (default), waits for the result and logs success/failure.
 * @return bool
 */
function telegramSend($cfg, $message, $source = 'event', $background = false) {
    if (empty($cfg['bot_token']) || empty($cfg['chat_id'])) {
        telegramLog("Send skipped [{$source}]: bot_token or chat_id not configured", 'WARN');
        fwrite(STDERR, "fpp-telegram: bot_token or chat_id not configured.\n");
        return false;
    }

    $scriptDir  = dirname(__FILE__);
    $sendScript = $scriptDir . '/sendTelegram.sh';

    $cmd = 'bash ' . escapeshellarg($sendScript)
         . ' ' . escapeshellarg($cfg['bot_token'])
         . ' ' . escapeshellarg($cfg['chat_id'])
         . ' ' . escapeshellarg($message)
         . ' ' . escapeshellarg($cfg['proxy_url'])
         . ' ' . escapeshellarg($cfg['disable_web_preview']);

    if ($background) {
        // Fire-and-forget: exec() with trailing & returns immediately.
        // FPP callbacks use this so the daemon is never blocked by a slow network.
        exec($cmd . ' > /dev/null 2>&1 &');
        $preview = substr($message, 0, 80) . (strlen($message) > 80 ? '…' : '');
        telegramLog("Send dispatched (background) [{$source}]: {$preview}");
        return true;
    }

    $output  = shell_exec($cmd . ' 2>&1');
    $decoded = json_decode($output, true);

    if (is_array($decoded) && isset($decoded['ok']) && $decoded['ok']) {
        $preview = substr($message, 0, 80) . (strlen($message) > 80 ? '…' : '');
        telegramLog("Sent [{$source}]: {$preview}");
        return true;
    }

    $desc = isset($decoded['description']) ? $decoded['description'] : trim((string)$output);
    telegramLog("Send failed [{$source}]: {$desc}", 'ERROR');
    fwrite(STDERR, "fpp-telegram: send failed: {$desc}\n");
    return false;
}
