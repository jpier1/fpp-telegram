<?php

$pluginName = basename(dirname(__FILE__));
$pluginPath  = $settings['pluginDirectory'] . "/" . $pluginName . "/";

// Settings live in FPP's persistent media/config area so they survive
// plugin updates. They are only removed by fpp_uninstall.sh.
$settingsFile = $settings['mediaDirectory'] . '/config/plugin.fpp-telegram.json';

// Include shared functions (logging, send helpers)
require_once($pluginPath . 'scripts/telegramFunctions.php');

// loadTelegramSettings() was removed — use loadTelegramConfig() from
// telegramFunctions.php (already required above). It is the single source
// of truth for defaults and the empty-string fallback logic.

//-----------------------------------------------------------------------------
// Save settings
function saveTelegramSettings($settingsFile, $data) {
    $dir = dirname($settingsFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

//-----------------------------------------------------------------------------
// Handle POST - Save Settings
$saveMessage = '';
$saveError   = '';
$activeTab   = 'bot';

if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $existing = loadTelegramConfig();
    $s = array(
        'bot_token'              => trim($_POST['bot_token'] ?? ''),
        'chat_id'                => trim($_POST['chat_id'] ?? ''),
        'notify_playlist_start'  => isset($_POST['notify_playlist_start'])  ? '1' : '0',
        'notify_playlist_end'    => isset($_POST['notify_playlist_end'])    ? '1' : '0',
        'notify_sequence_start'  => isset($_POST['notify_sequence_start'])  ? '1' : '0',
        'notify_sequence_end'    => isset($_POST['notify_sequence_end'])    ? '1' : '0',
        'notify_fpp_start'       => isset($_POST['notify_fpp_start'])       ? '1' : '0',
        'notify_fpp_stop'        => isset($_POST['notify_fpp_stop'])        ? '1' : '0',
        'msg_playlist_start'     => trim($_POST['msg_playlist_start'] ?? ''),
        'msg_playlist_end'       => trim($_POST['msg_playlist_end'] ?? ''),
        'msg_sequence_start'     => trim($_POST['msg_sequence_start'] ?? ''),
        'msg_sequence_end'       => trim($_POST['msg_sequence_end'] ?? ''),
        'msg_fpp_start'          => trim($_POST['msg_fpp_start'] ?? ''),
        'msg_fpp_stop'           => trim($_POST['msg_fpp_stop'] ?? ''),
        'msg_notifications_enabled'  => trim($_POST['msg_notifications_enabled']  ?? ''),
        'msg_notifications_disabled' => trim($_POST['msg_notifications_disabled'] ?? ''),
        // notifications_enabled is only changed by the toggle handler; preserve existing value
        'notifications_enabled'  => $existing['notifications_enabled'],
        'proxy_url'              => trim($_POST['proxy_url'] ?? ''),
        'disable_web_preview'    => isset($_POST['disable_web_preview'])    ? '1' : '0',
    );
    $activeTab = in_array($_POST['active_tab'] ?? '', ['bot','events','messages','advanced'])
        ? $_POST['active_tab'] : 'bot';
    $tabLabels = [
        'bot'      => 'Bot Setup',
        'events'   => 'Event Notifications',
        'messages' => 'Message Templates',
        'advanced' => 'Advanced',
    ];
    saveTelegramSettings($settingsFile, $s);
    $saveMessage = $tabLabels[$activeTab] . ' settings saved successfully.';
    telegramLog('Settings saved via UI (' . $tabLabels[$activeTab] . ')');

    // Ensure scripts stay executable after any plugin update
    exec("chmod +x " . escapeshellarg($pluginPath . "callbacks.php") . " 2>&1");
    exec("chmod +x " . escapeshellarg($pluginPath . "scripts/postStart.sh") . " 2>&1");
    exec("chmod +x " . escapeshellarg($pluginPath . "scripts/preStop.sh") . " 2>&1");
}

// Note: the inline "Send Test" on the Bot Setup tab uses the fetch API
// (/api/plugin/fpp-telegram/test) — there is no PHP test POST handler here.

$cfg = loadTelegramConfig();

//-----------------------------------------------------------------------------
// Helper to echo checked/not
function chk($val) { return $val === '1' ? 'checked' : ''; }
function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

?>
<script>
function TelegramShowTab(tabId) {
    document.querySelectorAll('.tg-tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.tg-tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById('tab-' + tabId).style.display = 'block';
    document.getElementById('btn-' + tabId).classList.add('active');
}

// Eye icon SVGs for token show/hide toggle
var TG_EYE_OPEN   = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
var TG_EYE_CLOSED = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

function TelegramToggleToken() {
    var input = document.getElementById('bot_token');
    var btn   = document.getElementById('tg-token-toggle-btn');
    if (!input || !btn) return;
    if (input.type === 'password') {
        input.type   = 'text';
        btn.innerHTML = TG_EYE_CLOSED;
        btn.title     = 'Hide token';
    } else {
        input.type   = 'password';
        btn.innerHTML = TG_EYE_OPEN;
        btn.title     = 'Show token';
    }
}

// Per-template test button handler
var tgHostname = '<?= htmlspecialchars(gethostname(), ENT_QUOTES, 'UTF-8') ?>';

/**
 * Shared fetch helper for all test sends.
 * Uses r.text() instead of r.json() so a non-JSON response (PHP notice,
 * FPP wrapper, etc.) never throws and causes a false "Request failed".
 */
function tgDoTestFetch(message, source, btn, result, successClass, errorClass, autoHide) {
    fetch('/api/plugin/fpp-telegram/test', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ message: message, source: source })
    })
    .then(function(r) { return r.text(); })
    .then(function(text) {
        if (btn) btn.disabled = false;
        // Parse JSON if possible; fall back to empty object
        var data = {};
        try { data = JSON.parse(text); } catch(e) {
            // If full parse fails, try to extract a JSON object from within
            // the response (FPP may wrap the JSON in surrounding HTML/text)
            var m = text.match(/\{"status"\s*:\s*"[^"]*"[^}]*\}/);
            if (m) { try { data = JSON.parse(m[0]); } catch(e2) {} }
        }

        if (!result) return;
        // Accept success if parsed JSON has status:ok, OR if the raw text
        // contains the success marker (handles FPP response wrappers)
        var isOk = (data.status === 'ok') || (text.indexOf('"status":"ok"') !== -1);
        if (isOk) {
            result.textContent = '✓ Sent';
            result.className   = successClass + ' tg-test-ok';
        } else {
            var errMsg = data.message || text.trim().substring(0, 120);
            result.textContent = '✗ ' + (errMsg || 'No response');
            result.className   = errorClass + ' tg-test-error';
        }
        if (autoHide) {
            setTimeout(function() {
                result.textContent = '';
                result.className   = successClass;
            }, 6000);
        }
    })
    .catch(function() {
        if (btn) btn.disabled = false;
        if (result) {
            result.textContent = '✗ Could not reach API';
            result.className   = errorClass + ' tg-test-error';
        }
    });
}

function TelegramTestTemplate(fieldId, sampleVars) {
    var textarea = document.getElementById(fieldId);
    var btn      = document.getElementById('test-btn-' + fieldId);
    var result   = document.getElementById('test-result-' + fieldId);
    if (!textarea) return;

    var message = textarea.value;
    var now = new Date();
    var pad = function(n) { return String(n).padStart(2, '0'); };
    var dateStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' '
                + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());

    message = message.replace(/%PLAYLIST%/g, sampleVars.playlist || 'Playlist_Title');
    message = message.replace(/%SEQUENCE%/g, sampleVars.sequence || 'Test_Sequence_Notification.fseq');
    message = message.replace(/%STATUS%/g,   sampleVars.status   || 'Testing');
    message = message.replace(/%HOSTNAME%/g, tgHostname);
    message = message.replace(/%DATETIME%/g, dateStr);

    if (btn)    btn.disabled = true;
    if (result) { result.textContent = 'Sending…'; result.className = 'tg-test-result tg-test-pending'; }

    tgDoTestFetch(message, 'template_test_' + fieldId, btn, result, 'tg-test-result', 'tg-test-result', true);
}

function TelegramToggleNotifications() {
    var btn        = document.getElementById('tg-notif-toggle-btn');
    var statusEl   = document.getElementById('tg-notif-status');
    var bannerEl   = document.getElementById('tg-disabled-banner');

    if (btn) btn.disabled = true;
    if (statusEl) statusEl.textContent = 'Updating…';

    fetch('/api/plugin/fpp-telegram/toggle-notifications', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.text(); })
    .then(function(text) {
        var data = {};
        try { data = JSON.parse(text); } catch(e) {}

        if (btn) btn.disabled = false;

        if (data.status === 'ok') {
            var enabled = (data.notifications_enabled === '1');

            // Update status label
            if (statusEl) {
                statusEl.textContent = enabled
                    ? '✓ Notifications are ENABLED'
                    : '✗ Notifications are DISABLED';
                statusEl.className = 'tg-notify-status ' + (enabled ? 'enabled' : 'disabled');
            }

            // Update button label and style
            if (btn) {
                btn.textContent = enabled ? 'Disable Notifications' : 'Enable Notifications';
                btn.className   = enabled ? 'tg-btn-secondary' : 'tg-btn-primary';
            }

            // Show or hide the disabled banner
            if (bannerEl) {
                bannerEl.style.display = enabled ? 'none' : 'flex';
            }
        } else {
            if (statusEl) statusEl.textContent = 'Error — please try again';
            if (btn) btn.disabled = false;
        }
    })
    .catch(function() {
        if (btn) btn.disabled = false;
        if (statusEl) statusEl.textContent = 'Request failed';
    });
}

function TelegramResetMessages() {
    var defaults = {
        'msg_playlist_start':         '▶️ FPP: Playlist "%PLAYLIST%" started.',
        'msg_playlist_end':           '🏁 FPP: Playlist "%PLAYLIST%" ended.',
        'msg_sequence_start':         '🎶 FPP: Sequence "%SEQUENCE%" started.',
        'msg_sequence_end':           '✖️ FPP: Sequence "%SEQUENCE%" stopped.',
        'msg_fpp_start':              '✅ FPP: Falcon Player started.',
        'msg_fpp_stop':               '🛑 FPP: Falcon Player stopped.',
        'msg_notifications_enabled':  '🚀 FPP Telegram notifications have been ENABLED.',
        'msg_notifications_disabled': '⛔ FPP Telegram notifications have been DISABLED.'
    };
    for (var k in defaults) {
        var el = document.getElementById(k);
        if (el) el.value = defaults[k];
    }
}

function TelegramSendTest() {
    var msgEl  = document.getElementById('tg-inline-test-msg');
    var btn    = document.getElementById('tg-inline-test-btn');
    var result = document.getElementById('tg-inline-test-result');
    var message = msgEl ? msgEl.value.trim() : '';
    if (!message) message = 'Test message from Falcon Player!';

    if (btn)    btn.disabled = true;
    if (result) { result.textContent = 'Sending…'; result.className = 'tg-inline-result tg-test-pending'; }

    tgDoTestFetch(message, 'bot_setup_test', btn, result, 'tg-inline-result', 'tg-inline-result', false);
}
</script>

<style>
/* ── Wrapper ── */
.tg-wrap { max-width: 900px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

/* ── Page title ── */
.tg-page-title {
    font-size: 22px; font-weight: 700; color: #1a1a2e;
    margin: 0 0 18px; display: flex; align-items: center; gap: 10px;
}
.tg-page-title svg { flex-shrink: 0; }

/* ── Alert banners ── */
.tg-alert {
    padding: 10px 16px; border-radius: 8px; margin-bottom: 16px;
    font-size: 13px; font-weight: 500;
}
.tg-alert-success { background: #d1f2eb; color: #0e6655; border: 1px solid #a9dfcf; }
.tg-alert-error   { background: #fde8e8; color: #922b21; border: 1px solid #f5b7b1; }

/* ── Tab bar ── */
.tg-tabs {
    display: flex; gap: 6px; flex-wrap: wrap;
    margin-bottom: -1px; position: relative; z-index: 1;
}
.tg-tab-btn {
    padding: 9px 20px; cursor: pointer; border: 1px solid #d0d7de;
    border-bottom: none; background: #f6f8fa;
    border-radius: 8px 8px 0 0; font-weight: 600; font-size: 13px;
    color: #57606a; transition: background 0.15s, color 0.15s;
    user-select: none;
}
.tg-tab-btn:hover { background: #eaeef2; color: #1a1a2e; }
.tg-tab-btn.active {
    background: #fff; color: #229ED9;
    border-color: #d0d7de; border-bottom-color: #fff;
}

/* ── Tab panel (card) ── */
.tg-panel {
    background: #fff; border: 1px solid #d0d7de;
    border-radius: 0 8px 8px 8px;
    padding: 24px 28px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.tg-tab-content { display: none; }

/* ── Section headings ── */
.tg-section-head {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: #229ED9;
    margin: 22px 0 12px; padding-bottom: 6px;
    border-bottom: 2px solid #e8f4fb;
}
.tg-section-head:first-child { margin-top: 0; }

/* ── Field rows ── */
.tg-field-row { display: flex; align-items: flex-start; margin-bottom: 14px; gap: 12px; }
.tg-field-row label {
    width: 180px; flex-shrink: 0; font-weight: 600;
    font-size: 13px; color: #3d4451; padding-top: 8px;
}
.tg-field-row input[type=text],
.tg-field-row input[type=password],
.tg-field-row textarea,
.tg-field-row select {
    flex: 1; padding: 8px 12px; font-size: 13px;
    border: 1px solid #d0d7de; border-radius: 6px;
    background: #fff; color: #1a1a2e;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
}
.tg-field-row input:focus,
.tg-field-row textarea:focus,
.tg-field-row select:focus {
    border-color: #229ED9;
    box-shadow: 0 0 0 3px rgba(34,158,217,.15);
}
.tg-field-row input::placeholder,
.tg-field-row textarea::placeholder { color: #b0b8c4; font-style: italic; }
.tg-field-row textarea { height: 58px; resize: vertical; line-height: 1.5; }

/* ── Token field with eye button ── */
.tg-token-wrap { position: relative; flex: 1; display: flex; align-items: center; }
.tg-token-wrap input { padding-right: 38px; width: 100%; }
.tg-token-toggle {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; padding: 2px; cursor: pointer;
    color: #8b949e; line-height: 0; display: flex; align-items: center;
    transition: color 0.15s;
}
.tg-token-toggle:hover { color: #229ED9; }

/* ── Help text ── */
.tg-help {
    font-size: 12px; color: #6e7681; margin: -6px 0 12px 192px;
    line-height: 1.5;
}
.tg-help code { background: #f0f6ff; padding: 1px 5px; border-radius: 4px; font-size: 11px; color: #1a6aab; }
.tg-help a    { color: #229ED9; }

/* ── Intro paragraph ── */
.tg-intro { font-size: 13px; color: #57606a; margin: 0 0 18px; line-height: 1.6; }
.tg-intro a { color: #229ED9; }

/* ── Variables reference box ── */
.tg-vars-box {
    background: #f0f6ff; border: 1px solid #cce0f5;
    border-radius: 8px; padding: 10px 14px; font-size: 12px;
    color: #3d5a80; margin-bottom: 18px;
}
.tg-vars-box code {
    background: #dceeff; padding: 2px 6px; border-radius: 4px;
    font-size: 11px; color: #1a6aab; margin-right: 4px;
}

/* ── Event notifications table ── */
.tg-events-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.tg-events-table th {
    background: #f0f6ff; color: #3d5a80; font-weight: 700;
    padding: 10px 12px; text-align: left; border-bottom: 2px solid #cce0f5;
}
.tg-events-table td { padding: 10px 12px; border-bottom: 1px solid #eaeef2; color: #3d4451; }
.tg-events-table tr:last-child td { border-bottom: none; }
.tg-events-table tr:hover td { background: #f9fbff; }
.tg-events-table td:nth-child(2) { text-align: center; }
.tg-events-table input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: #229ED9; }
.tg-events-table td:nth-child(3) { color: #6e7681; }

/* ── Template test row ── */
.tg-template-test-row {
    display: flex; align-items: center; gap: 10px;
    margin: -6px 0 16px 192px;
}
.tg-test-btn {
    font-size: 12px; padding: 4px 12px; border-radius: 5px;
    background: #f0f6ff; border: 1px solid #cce0f5; color: #1a6aab;
    cursor: pointer; font-weight: 600; transition: background 0.15s;
}
.tg-test-btn:hover { background: #dceeff; }
.tg-test-btn:disabled { opacity: .5; cursor: default; }
.tg-test-result { font-size: 12px; font-weight: 600; }
.tg-test-ok      { color: #0e6655; }
.tg-test-error   { color: #922b21; }
.tg-test-pending { color: #8b949e; }
.tg-test-note    { font-size: 11px; color: #b0b8c4; }

/* ── Inline test section (Bot Setup tab) ── */
.tg-inline-test {
    background: #f6f8fa; border: 1px solid #d0d7de;
    border-radius: 8px; padding: 16px 18px; margin-top: 24px;
}
.tg-inline-test-title { font-weight: 700; font-size: 13px; color: #3d4451; margin-bottom: 10px; }
.tg-inline-test-row   { display: flex; gap: 10px; align-items: center; }
.tg-inline-test-row input[type=text] {
    flex: 1; padding: 8px 12px; font-size: 13px;
    border: 1px solid #d0d7de; border-radius: 6px;
    background: #fff; outline: none; transition: border-color 0.15s, box-shadow 0.15s;
}
.tg-inline-test-row input:focus {
    border-color: #229ED9; box-shadow: 0 0 0 3px rgba(34,158,217,.15);
}
.tg-inline-test-row input::placeholder { color: #b0b8c4; font-style: italic; }
.tg-inline-result { font-size: 13px; font-weight: 600; white-space: nowrap; }

/* ── Primary / secondary buttons ── */
.tg-btn-primary {
    padding: 9px 22px; background: #229ED9; color: #fff;
    border: none; border-radius: 6px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
}
.tg-btn-primary:hover { background: #1a87bc; }
.tg-btn-primary:disabled { background: #90cce8; cursor: default; }
.tg-btn-secondary {
    padding: 9px 22px; background: #fff; color: #3d4451;
    border: 1px solid #d0d7de; border-radius: 6px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
}
.tg-btn-secondary:hover { background: #f6f8fa; }

/* ── How-to steps list ── */
.tg-steps { font-size: 13px; line-height: 1.9; color: #3d4451; padding-left: 20px; margin: 8px 0 0; }
.tg-steps code { background: #f0f6ff; padding: 1px 5px; border-radius: 4px; font-size: 11px; color: #1a6aab; }
.tg-steps a    { color: #229ED9; }

/* ── Version footer ── */
.tg-footer { margin-top: 14px; text-align: right; font-size: 11px; color: #b0b8c4; }

/* ── Notifications disabled banner ── */
.tg-disabled-banner {
    display: flex; align-items: center; justify-content: space-between;
    background: #fde8e8; border: 1px solid #f5b7b1; color: #922b21;
    padding: 10px 16px; border-radius: 8px; margin-bottom: 14px;
    font-weight: 600; font-size: 13px;
}
.tg-disabled-banner span { display: flex; align-items: center; gap: 8px; }

/* ── Notifications toggle block (Event Notifications tab) ── */
.tg-notify-toggle-block {
    display: flex; align-items: center; justify-content: space-between;
    background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 8px;
    padding: 14px 18px; margin-bottom: 20px;
}
.tg-notify-status { font-size: 15px; font-weight: 700; }
.tg-notify-status.enabled  { color: #0e6655; }
.tg-notify-status.disabled { color: #922b21; }
</style>

<?php
$_tgInfoFile = $pluginPath . 'pluginInfo.json';
$_tgVersion  = '?';
if (file_exists($_tgInfoFile)) {
    $_tgInfo = json_decode(file_get_contents($_tgInfoFile), true);
    if (isset($_tgInfo['pluginVersion'])) $_tgVersion = $_tgInfo['pluginVersion'];
}
?>

<div class="tg-wrap">

<div class="tg-page-title">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#229ED9">
        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.14 13.67l-2.98-.924c-.648-.203-.662-.648.135-.961l11.64-4.489c.537-.194 1.006.131.959.925z"/>
    </svg>
    Telegram Notifications
</div>

<?php if ($saveMessage): ?>
<div class="tg-alert tg-alert-success"><?= esc($saveMessage) ?></div>
<script>TelegramShowTab('<?= esc($activeTab) ?>');</script>
<?php endif; ?>
<?php if ($saveError): ?>
<div class="tg-alert tg-alert-error"><?= esc($saveError) ?></div>
<?php endif; ?>

<div id="tg-disabled-banner" class="tg-disabled-banner" style="<?= $cfg['notifications_enabled'] === '1' ? 'display:none;' : '' ?>">
    <span>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Notifications are DISABLED — no Telegram messages will be sent
    </span>
    <button class="tg-btn-primary" onclick="TelegramToggleNotifications()" style="padding:6px 16px;font-size:12px;">Enable Notifications</button>
</div>

<div class="tg-tabs">
    <div class="tg-tab-btn active" id="btn-bot"      onclick="TelegramShowTab('bot')">Bot Setup</div>
    <div class="tg-tab-btn"        id="btn-events"   onclick="TelegramShowTab('events')">Event Notifications</div>
    <div class="tg-tab-btn"        id="btn-messages" onclick="TelegramShowTab('messages')">Message Templates</div>
    <div class="tg-tab-btn"        id="btn-advanced" onclick="TelegramShowTab('advanced')">Advanced</div>
</div>

<div class="tg-panel">

<!-- ===== BOT SETUP TAB ===== -->
<div class="tg-tab-content" id="tab-bot">
    <form method="POST" action="plugin.php?plugin=fpp-telegram&page=plugin_setup.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="active_tab" value="bot">
    <!-- Preserve all settings this tab does not display -->
    <?php if ($cfg['notify_fpp_start']      === '1'): ?><input type="hidden" name="notify_fpp_start"      value="1"><?php endif; ?>
    <?php if ($cfg['notify_fpp_stop']       === '1'): ?><input type="hidden" name="notify_fpp_stop"       value="1"><?php endif; ?>
    <?php if ($cfg['notify_playlist_start'] === '1'): ?><input type="hidden" name="notify_playlist_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_playlist_end']   === '1'): ?><input type="hidden" name="notify_playlist_end"   value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_start'] === '1'): ?><input type="hidden" name="notify_sequence_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_end']   === '1'): ?><input type="hidden" name="notify_sequence_end"   value="1"><?php endif; ?>
    <?php if ($cfg['disable_web_preview']   === '1'): ?><input type="hidden" name="disable_web_preview"   value="1"><?php endif; ?>
    <input type="hidden" name="proxy_url"          value="<?= esc($cfg['proxy_url']) ?>">
    <input type="hidden" name="msg_fpp_start"      value="<?= esc($cfg['msg_fpp_start']) ?>">
    <input type="hidden" name="msg_fpp_stop"       value="<?= esc($cfg['msg_fpp_stop']) ?>">
    <input type="hidden" name="msg_playlist_start" value="<?= esc($cfg['msg_playlist_start']) ?>">
    <input type="hidden" name="msg_playlist_end"   value="<?= esc($cfg['msg_playlist_end']) ?>">
    <input type="hidden" name="msg_sequence_start" value="<?= esc($cfg['msg_sequence_start']) ?>">
    <input type="hidden" name="msg_sequence_end"            value="<?= esc($cfg['msg_sequence_end']) ?>">
    <input type="hidden" name="msg_notifications_enabled"  value="<?= esc($cfg['msg_notifications_enabled']) ?>">
    <input type="hidden" name="msg_notifications_disabled" value="<?= esc($cfg['msg_notifications_disabled']) ?>">
    <input type="hidden" name="notifications_enabled"      value="<?= esc($cfg['notifications_enabled']) ?>">

    <div class="tg-section-head">Bot Configuration</div>
    <p class="tg-intro">You need a Telegram Bot to use this plugin. Create one by messaging
        <a href="https://t.me/BotFather" target="_blank">@BotFather</a> on Telegram — send
        <code style="background:#f0f6ff;padding:1px 5px;border-radius:4px;font-size:12px;color:#1a6aab;">/newbot</code>
        and follow the prompts to receive your Bot Token.</p>

    <div class="tg-field-row">
        <label for="bot_token">Bot Token:</label>
        <div class="tg-token-wrap">
            <input type="password" id="bot_token" name="bot_token"
                   value="<?= esc($cfg['bot_token']) ?>"
                   placeholder="Enter token from @BotFather, e.g. 123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ"
                   autocomplete="off">
            <button type="button" id="tg-token-toggle-btn" class="tg-token-toggle"
                    onclick="TelegramToggleToken()" title="Show token">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
    </div>

    <div class="tg-field-row">
        <label for="chat_id">Chat ID:</label>
        <input type="text" id="chat_id" name="chat_id"
               value="<?= esc($cfg['chat_id']) ?>"
               placeholder="Enter your Chat ID, e.g. -1001234567890 (groups) or 123456789 (personal)">
    </div>
    <div class="tg-help">
        Groups and channels use a negative ID (e.g. <code>-1001234567890</code>).
        Personal chats use a positive integer.
    </div>

    <div class="tg-section-head">How to Find Your Chat ID</div>
    <ol class="tg-steps">
        <li>Send your bot any message (e.g. <code>/start</code>) to start a conversation.</li>
        <li>Open in your browser: <code>https://api.telegram.org/bot<strong>YOUR_TOKEN</strong>/getUpdates</code></li>
        <li>Find <code>"chat":{"id": XXXXXXXXX}</code> — that number is your Chat ID.</li>
        <li>For groups: add the bot to the group, send a message there, then check getUpdates again.</li>
    </ol>

    <!-- Inline Test Section -->
    <div class="tg-inline-test">
        <div class="tg-inline-test-title">Send a Test Message</div>
        <div class="tg-inline-test-row">
            <input type="text" id="tg-inline-test-msg"
                   placeholder="Enter a test message, e.g. Hello from FPP!"
                   value="Test message from Falcon Player! 🎄">
            <button type="button" id="tg-inline-test-btn" class="tg-btn-primary"
                    onclick="TelegramSendTest()">Send Test</button>
            <span id="tg-inline-test-result" class="tg-inline-result"></span>
        </div>
        <div style="font-size:11px;color:#8b949e;margin-top:8px;">
            Uses your saved Bot Token and Chat ID. Save first if you have just entered them.
        </div>
    </div>

    <div style="margin-top:20px;">
        <button type="submit" class="tg-btn-primary">Save Bot Settings</button>
    </div>
    </form>
</div>

<!-- ===== EVENT NOTIFICATIONS TAB ===== -->
<div class="tg-tab-content" id="tab-events">
    <form method="POST" action="plugin.php?plugin=fpp-telegram&page=plugin_setup.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="active_tab" value="events">
    <input type="hidden" name="bot_token"          value="<?= esc($cfg['bot_token']) ?>">
    <input type="hidden" name="chat_id"            value="<?= esc($cfg['chat_id']) ?>">
    <input type="hidden" name="proxy_url"          value="<?= esc($cfg['proxy_url']) ?>">
    <?php if ($cfg['disable_web_preview'] === '1'): ?><input type="hidden" name="disable_web_preview" value="1"><?php endif; ?>
    <input type="hidden" name="msg_playlist_start" value="<?= esc($cfg['msg_playlist_start']) ?>">
    <input type="hidden" name="msg_playlist_end"   value="<?= esc($cfg['msg_playlist_end']) ?>">
    <input type="hidden" name="msg_sequence_start" value="<?= esc($cfg['msg_sequence_start']) ?>">
    <input type="hidden" name="msg_sequence_end"   value="<?= esc($cfg['msg_sequence_end']) ?>">
    <input type="hidden" name="msg_fpp_start"      value="<?= esc($cfg['msg_fpp_start']) ?>">
    <input type="hidden" name="msg_fpp_stop"       value="<?= esc($cfg['msg_fpp_stop']) ?>">
    <input type="hidden" name="msg_notifications_enabled"  value="<?= esc($cfg['msg_notifications_enabled']) ?>">
    <input type="hidden" name="msg_notifications_disabled" value="<?= esc($cfg['msg_notifications_disabled']) ?>">
    <input type="hidden" name="notifications_enabled"      value="<?= esc($cfg['notifications_enabled']) ?>">

    <!-- Master notifications toggle -->
    <div class="tg-notify-toggle-block">
        <div>
            <div style="font-size:12px;color:#6e7681;margin-bottom:4px;">Master Notifications Switch</div>
            <div id="tg-notif-status" class="tg-notify-status <?= $cfg['notifications_enabled'] === '1' ? 'enabled' : 'disabled' ?>">
                <?= $cfg['notifications_enabled'] === '1' ? '&#10003; Notifications are ENABLED' : '&#10007; Notifications are DISABLED' ?>
            </div>
        </div>
        <button id="tg-notif-toggle-btn"
                class="<?= $cfg['notifications_enabled'] === '1' ? 'tg-btn-secondary' : 'tg-btn-primary' ?>"
                onclick="TelegramToggleNotifications()">
            <?= $cfg['notifications_enabled'] === '1' ? 'Disable Notifications' : 'Enable Notifications' ?>
        </button>
    </div>

    <div class="tg-section-head">Automatic Notifications</div>
    <p class="tg-intro">Choose which FPP events trigger a Telegram message. Make sure your Bot Token and Chat ID are saved first.</p>

    <table class="tg-events-table">
        <tr>
            <th>Event</th>
            <th style="width:70px;">Enable</th>
            <th>When it fires</th>
        </tr>
        <tr>
            <td>FPP Startup</td>
            <td><input type="checkbox" name="notify_fpp_start" <?= chk($cfg['notify_fpp_start']) ?>></td>
            <td>After the Falcon Player daemon finishes starting up</td>
        </tr>
        <tr>
            <td>FPP Shutdown</td>
            <td><input type="checkbox" name="notify_fpp_stop" <?= chk($cfg['notify_fpp_stop']) ?>></td>
            <td>Before the Falcon Player daemon stops (network still up)</td>
        </tr>
        <tr>
            <td>Playlist Started</td>
            <td><input type="checkbox" name="notify_playlist_start" <?= chk($cfg['notify_playlist_start']) ?>></td>
            <td>When any playlist begins playing</td>
        </tr>
        <tr>
            <td>Playlist Ended</td>
            <td><input type="checkbox" name="notify_playlist_end" <?= chk($cfg['notify_playlist_end']) ?>></td>
            <td>When a playlist finishes or is stopped</td>
        </tr>
        <tr>
            <td>Sequence / Media Started</td>
            <td><input type="checkbox" name="notify_sequence_start" <?= chk($cfg['notify_sequence_start']) ?>></td>
            <td>When a sequence or media file begins playing</td>
        </tr>
        <tr>
            <td>Sequence / Media Ended</td>
            <td><input type="checkbox" name="notify_sequence_end" <?= chk($cfg['notify_sequence_end']) ?>></td>
            <td>When a sequence or media file finishes playing</td>
        </tr>
    </table>

    <p style="font-size:12px;color:#6e7681;margin-top:12px;">
        You can also trigger messages at any playlist step using the FPP Command <em>"Telegram - Send Message"</em>.
    </p>

    <div style="margin-top:20px;">
        <button type="submit" class="tg-btn-primary">Save Event Settings</button>
    </div>
    </form>
</div>

<!-- ===== MESSAGE TEMPLATES TAB ===== -->
<div class="tg-tab-content" id="tab-messages">
    <form method="POST" action="plugin.php?plugin=fpp-telegram&page=plugin_setup.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="active_tab" value="messages">
    <input type="hidden" name="bot_token"              value="<?= esc($cfg['bot_token']) ?>">
    <input type="hidden" name="chat_id"                value="<?= esc($cfg['chat_id']) ?>">
    <input type="hidden" name="proxy_url"              value="<?= esc($cfg['proxy_url']) ?>">
    <?php if ($cfg['disable_web_preview'] === '1'): ?><input type="hidden" name="disable_web_preview" value="1"><?php endif; ?>
    <?php if ($cfg['notify_playlist_start'] === '1'): ?><input type="hidden" name="notify_playlist_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_playlist_end']   === '1'): ?><input type="hidden" name="notify_playlist_end"   value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_start'] === '1'): ?><input type="hidden" name="notify_sequence_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_end']   === '1'): ?><input type="hidden" name="notify_sequence_end"   value="1"><?php endif; ?>
    <?php if ($cfg['notify_fpp_start']      === '1'): ?><input type="hidden" name="notify_fpp_start"      value="1"><?php endif; ?>
    <?php if ($cfg['notify_fpp_stop']       === '1'): ?><input type="hidden" name="notify_fpp_stop"       value="1"><?php endif; ?>
    <input type="hidden" name="notifications_enabled" value="<?= esc($cfg['notifications_enabled']) ?>">

    <div class="tg-section-head">Message Templates</div>

    <div class="tg-vars-box" style="margin-bottom:10px;">
        <strong>HTML Formatting</strong> — messages support these HTML tags:
        <table style="margin-top:8px;border-collapse:collapse;font-size:12px;width:100%;">
            <tr><td style="padding:2px 12px 2px 0;color:#6e7681;">Bold</td>        <td><code>&lt;b&gt;text&lt;/b&gt;</code></td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#6e7681;">Italic</td>      <td><code>&lt;i&gt;text&lt;/i&gt;</code></td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#6e7681;">Underline</td>   <td><code>&lt;u&gt;text&lt;/u&gt;</code></td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#6e7681;">Monospace</td>   <td><code>&lt;code&gt;text&lt;/code&gt;</code></td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#6e7681;">Link</td>        <td><code>&lt;a href="https://..."&gt;text&lt;/a&gt;</code></td></tr>
        </table>
    </div>

    <div class="tg-vars-box">
        <strong>Available variables:</strong> &nbsp;
        <code>%PLAYLIST%</code> Playlist name &nbsp;
        <code>%SEQUENCE%</code> Sequence/media filename &nbsp;
        <code>%STATUS%</code> FPP status &nbsp;
        <code>%HOSTNAME%</code> FPP hostname &nbsp;
        <code>%DATETIME%</code> Date &amp; time
    </div>

    <?php
    $tgTemplateRow = function($fieldId, $label, $value, $sampleVarsJson) { ?>
    <div class="tg-field-row">
        <label for="<?= $fieldId ?>"><?= $label ?>:</label>
        <textarea id="<?= $fieldId ?>" name="<?= $fieldId ?>"><?= esc($value) ?></textarea>
    </div>
    <div class="tg-template-test-row">
        <button type="button" id="test-btn-<?= $fieldId ?>" class="tg-test-btn"
                onclick="TelegramTestTemplate('<?= $fieldId ?>', <?= htmlspecialchars($sampleVarsJson, ENT_COMPAT, 'UTF-8') ?>)">Send Sample</button>
        <span id="test-result-<?= $fieldId ?>" class="tg-test-result"></span>
        <span class="tg-test-note">Uses saved Bot Settings</span>
    </div>
    <?php }; ?>

    <?php $tgTemplateRow('msg_fpp_start',      'FPP Startup',               $cfg['msg_fpp_start'],               '{}') ?>
    <?php $tgTemplateRow('msg_fpp_stop',       'FPP Shutdown',              $cfg['msg_fpp_stop'],                '{}') ?>
    <?php $tgTemplateRow('msg_playlist_start', 'Playlist Started',          $cfg['msg_playlist_start'],          '{"playlist":"Playlist_Title"}') ?>
    <?php $tgTemplateRow('msg_playlist_end',   'Playlist Ended',            $cfg['msg_playlist_end'],            '{"playlist":"Playlist_Title"}') ?>
    <?php $tgTemplateRow('msg_sequence_start', 'Sequence Started',          $cfg['msg_sequence_start'],          '{"sequence":"Test_Sequence_Notification.fseq"}') ?>
    <?php $tgTemplateRow('msg_sequence_end',   'Sequence Ended',            $cfg['msg_sequence_end'],            '{"sequence":"Test_Sequence_Notification.fseq"}') ?>
    <?php $tgTemplateRow('msg_notifications_enabled',  'Notifications Enabled',  $cfg['msg_notifications_enabled'],  '{}') ?>
    <?php $tgTemplateRow('msg_notifications_disabled', 'Notifications Disabled', $cfg['msg_notifications_disabled'], '{}') ?>

    <div style="margin-top:20px; display:flex; gap:10px;">
        <button type="submit" class="tg-btn-primary">Save Templates</button>
        <button type="button" class="tg-btn-secondary" onclick="TelegramResetMessages()">Reset to Defaults</button>
    </div>
    </form>
</div>

<!-- ===== ADVANCED TAB ===== -->
<div class="tg-tab-content" id="tab-advanced">
    <form method="POST" action="plugin.php?plugin=fpp-telegram&page=plugin_setup.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="active_tab" value="advanced">
    <input type="hidden" name="bot_token"              value="<?= esc($cfg['bot_token']) ?>">
    <input type="hidden" name="chat_id"                value="<?= esc($cfg['chat_id']) ?>">
    <input type="hidden" name="msg_playlist_start"     value="<?= esc($cfg['msg_playlist_start']) ?>">
    <input type="hidden" name="msg_playlist_end"       value="<?= esc($cfg['msg_playlist_end']) ?>">
    <input type="hidden" name="msg_sequence_start"     value="<?= esc($cfg['msg_sequence_start']) ?>">
    <input type="hidden" name="msg_sequence_end"       value="<?= esc($cfg['msg_sequence_end']) ?>">
    <input type="hidden" name="msg_fpp_start"          value="<?= esc($cfg['msg_fpp_start']) ?>">
    <input type="hidden" name="msg_fpp_stop"           value="<?= esc($cfg['msg_fpp_stop']) ?>">
    <?php if ($cfg['notify_playlist_start'] === '1'): ?><input type="hidden" name="notify_playlist_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_playlist_end']   === '1'): ?><input type="hidden" name="notify_playlist_end"   value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_start'] === '1'): ?><input type="hidden" name="notify_sequence_start" value="1"><?php endif; ?>
    <?php if ($cfg['notify_sequence_end']   === '1'): ?><input type="hidden" name="notify_sequence_end"   value="1"><?php endif; ?>
    <?php if ($cfg['notify_fpp_start']      === '1'): ?><input type="hidden" name="notify_fpp_start"      value="1"><?php endif; ?>
    <?php if ($cfg['notify_fpp_stop']       === '1'): ?><input type="hidden" name="notify_fpp_stop"       value="1"><?php endif; ?>
    <input type="hidden" name="msg_notifications_enabled"  value="<?= esc($cfg['msg_notifications_enabled']) ?>">
    <input type="hidden" name="msg_notifications_disabled" value="<?= esc($cfg['msg_notifications_disabled']) ?>">
    <input type="hidden" name="notifications_enabled"      value="<?= esc($cfg['notifications_enabled']) ?>">

    <div class="tg-field-row">
        <label>Link Previews:</label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;width:auto;">
            <input type="checkbox" name="disable_web_preview" <?= chk($cfg['disable_web_preview']) ?> style="width:16px;height:16px;accent-color:#229ED9;">
            Disable web page previews in messages
        </label>
    </div>

    <div class="tg-section-head">Network</div>

    <div class="tg-field-row">
        <label for="proxy_url">HTTP Proxy:</label>
        <input type="text" id="proxy_url" name="proxy_url"
               value="<?= esc($cfg['proxy_url']) ?>"
               placeholder="Optional — e.g. http://user:pass@proxy.host:3128">
    </div>
    <div class="tg-help">Only needed if your FPP device cannot reach api.telegram.org directly.</div>

    <div style="margin-top:20px;">
        <button type="submit" class="tg-btn-primary">Save Advanced Settings</button>
    </div>
    </form>
</div>

</div><!-- /tg-panel -->

<div class="tg-footer">fpp-telegram v<?= htmlspecialchars($_tgVersion, ENT_QUOTES, 'UTF-8') ?></div>

</div><!-- /tg-wrap -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    TelegramShowTab('bot');
});
</script>
