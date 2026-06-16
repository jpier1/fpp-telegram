#!/usr/bin/php
<?php
/**
 * fpp-telegram/callbacks.php
 *
 * FPP calls this file automatically for playlist and media events.
 * Invoked by FPP's /scripts/eventScript wrapper as:
 *
 *   callbacks.php --list
 *     → print which event types this plugin handles, one per line
 *
 *   callbacks.php --type playlist --data '<JSON>'
 *     → playlist event fired (start, stop, etc.)
 *
 *   callbacks.php --type media --data '<JSON>'
 *     → media/sequence event fired (start, stop)
 *
 * FPP blocks until this script exits, so we do our work and exit quickly.
 */

require_once(dirname(__FILE__) . "/scripts/telegramFunctions.php");

//-----------------------------------------------------------------------------
// Parse command-line arguments
$type = '';
$data = '';

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--list') {
        // Tell FPP which event types we handle
        echo "playlist\n";
        echo "media\n";
        exit(0);
    }
    if ($argv[$i] === '--type' && isset($argv[$i + 1])) {
        $type = $argv[$i + 1];
        $i++;
        continue;
    }
    if ($argv[$i] === '--data' && isset($argv[$i + 1])) {
        $data = $argv[$i + 1];
        $i++;
        continue;
    }
}

if ($type === '' || $data === '') {
    exit(0); // Nothing to do
}

// Log the callback with action and name for easier troubleshooting
$_action = '';
$_name   = '';
$_rawPayload = json_decode($data, true);
if (is_array($_rawPayload)) {
    $_action = strtolower($_rawPayload['Action'] ?? $_rawPayload['action'] ?? '');
    $_name   = $type === 'playlist'
        ? ($_rawPayload['Playlist'] ?? $_rawPayload['playlist'] ?? '')
        : basename($_rawPayload['Filename'] ?? $_rawPayload['filename'] ?? $_rawPayload['Sequence'] ?? '');
}
telegramLog("Callback received: type={$type} action={$_action}" . ($_name ? " name={$_name}" : ''));

//-----------------------------------------------------------------------------
$cfg     = loadTelegramConfig();
$payload = json_decode($data, true);
if (!is_array($payload)) {
    exit(0);
}

// Master notifications gate
if ($cfg['notifications_enabled'] !== '1') {
    telegramLog("Callback suppressed [type={$type}] — notifications disabled", 'INFO');
    exit(0);
}

//-----------------------------------------------------------------------------
// PLAYLIST events
// FPP passes: Action (start|stop|playing|query_next), Playlist name, etc.
if ($type === 'playlist') {
    $action   = strtolower($payload['Action']   ?? $payload['action']   ?? '');
    $playlist = $payload['Playlist'] ?? $payload['playlist'] ?? 'Unknown';

    if ($action === 'start' && $cfg['notify_playlist_start'] === '1') {
        $msg = telegramApplyVars($cfg['msg_playlist_start'], ['playlist' => $playlist]);
        telegramSend($cfg, $msg, 'playlist_start', true);
    }

    if (($action === 'stop' || $action === 'stopping') && $cfg['notify_playlist_end'] === '1') {
        $msg = telegramApplyVars($cfg['msg_playlist_end'], ['playlist' => $playlist]);
        telegramSend($cfg, $msg, 'playlist_end', true);
    }
}

//-----------------------------------------------------------------------------
// MEDIA / SEQUENCE events
// FPP passes: Action (start|stop), Filename, etc.
if ($type === 'media') {
    $action   = strtolower($payload['Action']   ?? $payload['action']   ?? '');
    $sequence = basename($payload['Filename'] ?? $payload['filename'] ?? $payload['Sequence'] ?? 'Unknown');

    if ($action === 'start' && $cfg['notify_sequence_start'] === '1') {
        $msg = telegramApplyVars($cfg['msg_sequence_start'], ['sequence' => $sequence]);
        telegramSend($cfg, $msg, 'sequence_start', true);
    }

    if ($action === 'stop' && $cfg['notify_sequence_end'] === '1') {
        $msg = telegramApplyVars($cfg['msg_sequence_end'], ['sequence' => $sequence]);
        telegramSend($cfg, $msg, 'sequence_end', true);
    }
}

exit(0);
