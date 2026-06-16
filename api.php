<?php

// FPP Telegram Plugin - REST API Endpoints
// Endpoint base: /api/plugin/fpp-telegram/

// Shared functions: telegramLog(), loadTelegramConfig(), telegramApplyVars(), telegramSend()
require_once __DIR__ . '/scripts/telegramFunctions.php';

//-----------------------------------------------------------------------------
function getEndpointsfpptelegram() {
    return array(
        array(
            'method'   => 'GET',
            'endpoint' => 'version',
            'callback' => 'getTelegramVersion',
        ),
        array(
            'method'   => 'GET',
            'endpoint' => 'settings',
            'callback' => 'getTelegramSettings',
        ),
        array(
            'method'   => 'POST',
            'endpoint' => 'settings',
            'callback' => 'postTelegramSettings',
        ),
        array(
            'method'   => 'POST',
            'endpoint' => 'send',
            'callback' => 'postTelegramSend',
        ),
        array(
            'method'   => 'POST',
            'endpoint' => 'test',
            'callback' => 'postTelegramTest',
        ),
        array(
            'method'   => 'POST',
            'endpoint' => 'toggle-notifications',
            'callback' => 'postTelegramToggleNotifications',
        ),
    );
}

//-----------------------------------------------------------------------------
function getTelegramPluginPath() {
    global $settings;
    return $settings['pluginDirectory'] . "/fpp-telegram/";
}

function getTelegramSettingsFile() {
    global $settings;
    // Settings live in FPP's persistent media/config area, not inside the
    // plugin directory, so they survive plugin updates.
    return $settings['mediaDirectory'] . '/config/plugin.fpp-telegram.json';
}

//-----------------------------------------------------------------------------
function getTelegramVersion() {
    $infoFile = getTelegramPluginPath() . 'pluginInfo.json';
    $version  = '0.0.0'; // fallback if file unreadable
    if (file_exists($infoFile)) {
        $info = json_decode(file_get_contents($infoFile), true);
        if (isset($info['pluginVersion'])) {
            $version = $info['pluginVersion'];
        }
    }
    return json(array('version' => $version, 'plugin' => 'fpp-telegram'));
}

//-----------------------------------------------------------------------------
function getTelegramSettings() {
    $cfg = loadTelegramConfig();
    // Mask bot token for security — never expose it over the API
    if (!empty($cfg['bot_token'])) {
        $cfg['bot_token_configured'] = true;
        $cfg['bot_token'] = '***';
    } else {
        $cfg['bot_token_configured'] = false;
    }
    return json($cfg);
}

//-----------------------------------------------------------------------------
// Allowed setting keys — used to whitelist incoming POST data (F6)
function telegramAllowedSettingKeys() {
    return array(
        'bot_token', 'chat_id',
        'notify_playlist_start', 'notify_playlist_end',
        'notify_sequence_start', 'notify_sequence_end',
        'notify_fpp_start',      'notify_fpp_stop',
        'msg_playlist_start',    'msg_playlist_end',
        'msg_sequence_start',    'msg_sequence_end',
        'msg_fpp_start',         'msg_fpp_stop',
        'proxy_url', 'disable_web_preview',
        'notifications_enabled', 'msg_notifications_enabled', 'msg_notifications_disabled',
    );
}

//-----------------------------------------------------------------------------
function postTelegramSettings() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data)) {
        header('HTTP/1.1 400 Bad Request');
        return json(array('status' => 'error', 'message' => 'Invalid JSON body'));
    }

    $file = getTelegramSettingsFile();
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $current = loadTelegramConfig();

    // Don't overwrite token if the masked sentinel was sent back
    if (isset($data['bot_token']) && $data['bot_token'] === '***') {
        $data['bot_token'] = $current['bot_token'];
    }

    // Whitelist: only persist known setting keys (F6)
    $allowed = telegramAllowedSettingKeys();
    $filtered = array();
    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $filtered[$key] = $data[$key];
        }
    }

    $merged = array_merge($current, $filtered);
    file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT), LOCK_EX);

    telegramLog('Settings saved via API');
    return json(array('status' => 'ok'));
}

//-----------------------------------------------------------------------------
function postTelegramSend() {
    $cfg  = loadTelegramConfig();
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    $message = isset($data['message']) ? trim($data['message']) : '';
    if ($message === '') {
        header('HTTP/1.1 400 Bad Request');
        return json(array('status' => 'error', 'message' => 'message is required'));
    }

    // Master notifications gate — block API sends when notifications are disabled
    if ($cfg['notifications_enabled'] !== '1') {
        telegramLog('API send blocked — notifications disabled', 'INFO');
        return json(array('status' => 'error', 'message' => 'Notifications are currently disabled'));
    }

    // Substitute template variables with proper escaping for the configured parse mode
    $vars = array();
    if (isset($data['playlist'])) $vars['playlist'] = $data['playlist'];
    if (isset($data['sequence'])) $vars['sequence']  = $data['sequence'];
    if (isset($data['status']))   $vars['status']    = $data['status'];
    $message = telegramApplyVars($message, $vars);

    $result = telegramSendMessage($cfg, $message, 'api_send');
    return json($result);
}

//-----------------------------------------------------------------------------
function postTelegramTest() {
    $cfg     = loadTelegramConfig();
    $body    = file_get_contents('php://input');
    $data    = json_decode($body, true);
    $message = isset($data['message']) ? trim($data['message']) : 'Test message from Falcon Player!';

    // Sanitise source label to prevent log injection (F5)
    $rawSource = isset($data['source']) ? (string)$data['source'] : 'api_test';
    $source    = substr(preg_replace('/[^\w_-]/', '', $rawSource), 0, 40);
    if ($source === '') {
        $source = 'api_test';
    }

    $result = telegramSendMessage($cfg, $message, $source);
    return json($result);
}

//-----------------------------------------------------------------------------
function postTelegramToggleNotifications() {
    $file    = getTelegramSettingsFile();
    $current = loadTelegramConfig();

    $oldState = $current['notifications_enabled'];
    $newState = ($oldState === '1') ? '0' : '1';

    $current['notifications_enabled'] = $newState;

    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($current, JSON_PRETTY_PRINT), LOCK_EX);

    $label = ($newState === '1') ? 'ENABLED' : 'DISABLED';
    telegramLog("Notifications {$label} via UI toggle");

    // Send status-change message regardless of new state (bypasses master gate)
    if (!empty($current['bot_token']) && !empty($current['chat_id'])) {
        $template = ($newState === '1')
            ? $current['msg_notifications_enabled']
            : $current['msg_notifications_disabled'];
        $message = telegramApplyVars($template);
        telegramSendMessage($current, $message, 'notifications_toggle');
    }

    return json(array(
        'status'                => 'ok',
        'notifications_enabled' => $newState,
    ));
}

//-----------------------------------------------------------------------------
// Core send via PHP cURL.
// Distinct from telegramSend() in telegramFunctions.php which shells out to bash.
// Used exclusively by the REST API endpoints (web context, cURL always available).
function telegramSendMessage($cfg, $message, $source = 'api') {
    if (empty($cfg['bot_token']) || empty($cfg['chat_id'])) {
        telegramLog("Send skipped [{$source}]: bot_token or chat_id not configured", 'WARN');
        return array('status' => 'error', 'message' => 'Bot token or chat ID not configured');
    }

    $url  = 'https://api.telegram.org/bot' . urlencode($cfg['bot_token']) . '/sendMessage';
    $post = array(
        'chat_id' => $cfg['chat_id'],
        'text'    => $message,
    );
    // HTML is the only supported format
    $post['parse_mode'] = 'HTML';
    if ($cfg['disable_web_preview'] === '1') {
        $post['disable_web_page_preview'] = true;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if (!empty($cfg['proxy_url'])) {
        curl_setopt($ch, CURLOPT_PROXY, $cfg['proxy_url']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        telegramLog("Send failed [{$source}]: cURL error: {$curlErr}", 'ERROR');
        return array('status' => 'error', 'message' => 'cURL error: ' . $curlErr);
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['ok']) && $decoded['ok']) {
        $preview = substr($message, 0, 80) . (strlen($message) > 80 ? '…' : '');
        telegramLog("Sent [{$source}]: {$preview}");
        return array('status' => 'ok', 'message_id' => $decoded['result']['message_id'] ?? null);
    }

    $errDesc = isset($decoded['description']) ? $decoded['description'] : 'Unknown error (HTTP ' . $httpCode . ')';
    telegramLog("Send failed [{$source}]: {$errDesc}", 'ERROR');
    return array('status' => 'error', 'message' => $errDesc, 'http_code' => $httpCode);
}
