<style>
.tg-help-wrap { max-width: 900px; }
.tg-help-wrap h3 { border-bottom: 1px solid #ccc; padding-bottom: 4px; }
.tg-help-wrap code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; font-size: 13px; }
.tg-help-wrap pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
.tg-help-wrap table { border-collapse: collapse; width: 100%; font-size: 13px; }
.tg-help-wrap td, .tg-help-wrap th { border: 1px solid #ddd; padding: 7px 10px; }
.tg-help-wrap th { background: #f5f5f5; }
.tg-help-step { background: #e8f4fd; border-left: 4px solid #2196F3; padding: 10px 14px; margin: 10px 0; border-radius: 0 4px 4px 0; }
</style>

<div class="tg-help-wrap">
<h2>Telegram Notifications for FPP — Help</h2>

<h3>Overview</h3>
<p>
This plugin lets Falcon Player send Telegram messages when show events occur — for example, when a playlist starts,
a sequence plays, or FPP starts and stops. It uses the Telegram Bot API, which is free and requires no third-party account
beyond a Telegram account.
</p>

<h3>Quick Start</h3>
<div class="tg-help-step"><strong>Step 1.</strong> Create a Telegram Bot by messaging <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.
Send <code>/newbot</code>, follow the prompts, and copy the Bot Token it gives you.</div>

<div class="tg-help-step"><strong>Step 2.</strong> Find your Chat ID.
<ol>
    <li>Start a chat with your new bot (send it any message, e.g. <code>/start</code>).</li>
    <li>Open this URL in your browser, replacing <code>TOKEN</code> with your bot token:<br>
        <code>https://api.telegram.org/botTOKEN/getUpdates</code></li>
    <li>Find <code>"chat":{"id":XXXXXXXXX}</code> in the JSON response — that number is your Chat ID.</li>
    <li>For a group or channel: add the bot to the group, send a message there, then check getUpdates again.
        Group IDs begin with a minus sign, e.g. <code>-1001234567890</code>.</li>
</ol>
</div>

<div class="tg-help-step"><strong>Step 3.</strong> Go to <em>Content → Telegram Notifications → Bot Setup</em>, enter your Bot Token and Chat ID, and click <strong>Save Bot Settings</strong>.</div>

<div class="tg-help-step"><strong>Step 4.</strong> Use the <strong>Send Test</strong> section on the Bot Setup tab to confirm everything is working.</div>

<div class="tg-help-step"><strong>Step 5.</strong> Go to the <strong>Event Notifications</strong> tab, enable the events you want, and save.</div>

<h3>Master Notifications Switch</h3>
<p>
The <strong>Event Notifications</strong> tab has a master switch that turns all notifications on or off at once.
When notifications are disabled, no messages are sent for any event — a red banner appears at the top of the page
as a reminder. Re-enabling sends a confirmation message. You can also toggle this switch with the FPP commands
described below.
</p>

<h3>Event Notifications</h3>
<p>These events fire automatically when enabled — no playlist setup required:</p>
<table>
<tr><th>Event</th><th>When it fires</th></tr>
<tr><td>FPP Startup</td><td>When the FPPD daemon starts</td></tr>
<tr><td>FPP Shutdown</td><td>When the FPPD daemon stops or FPP reboots</td></tr>
<tr><td>Playlist Started</td><td>When any playlist begins playing</td></tr>
<tr><td>Playlist Ended</td><td>When a playlist finishes or is stopped</td></tr>
<tr><td>Sequence Started</td><td>When a sequence or media file begins</td></tr>
<tr><td>Sequence Ended</td><td>When a sequence or media file finishes</td></tr>
</table>

<h3>Message Templates &amp; Variables</h3>
<p>Customize the text sent for each event using these variables:</p>
<table>
<tr><th>Variable</th><th>Replaced with</th></tr>
<tr><td><code>%PLAYLIST%</code></td><td>The name of the current playlist</td></tr>
<tr><td><code>%SEQUENCE%</code></td><td>The name of the sequence or media file</td></tr>
<tr><td><code>%STATUS%</code></td><td>Current FPP playback status</td></tr>
<tr><td><code>%HOSTNAME%</code></td><td>Hostname of this FPP device</td></tr>
<tr><td><code>%DATETIME%</code></td><td>Current date and time</td></tr>
</table>
<p>Example: <code>🎄 Show started on %HOSTNAME%: playing %PLAYLIST% at %DATETIME%</code></p>
<p>Use the <strong>Send Sample</strong> button next to each template to preview how it will look in Telegram.</p>

<h3>FPP Commands</h3>
<p>
These commands are available in the FPP command system (e.g. as a Command step in a playlist, or from the
Scheduler / Event configuration):
</p>
<table>
<tr><th>Command</th><th>What it does</th></tr>
<tr><td>Telegram - Send Message</td><td>Sends a custom message you type into the command's Message field.</td></tr>
<tr><td>Telegram - Enable Notifications</td><td>Turns the master notifications switch on and sends a confirmation message.</td></tr>
<tr><td>Telegram - Disable Notifications</td><td>Turns the master notifications switch off and sends a final message.</td></tr>
</table>
<p>
Playlist and sequence notifications are <strong>event-driven only</strong> — they fire automatically based on your
Event Notifications settings and are not available as manual commands.
</p>

<h3>REST API</h3>
<p>The plugin exposes a REST API for integration with other tools:</p>
<table>
<tr><th>Method</th><th>Endpoint</th><th>Description</th></tr>
<tr><td>GET</td><td><code>/api/plugin/fpp-telegram/version</code></td><td>Return the installed plugin version</td></tr>
<tr><td>GET</td><td><code>/api/plugin/fpp-telegram/settings</code></td><td>Retrieve current settings (token masked)</td></tr>
<tr><td>POST</td><td><code>/api/plugin/fpp-telegram/settings</code></td><td>Update settings (JSON body)</td></tr>
<tr><td>POST</td><td><code>/api/plugin/fpp-telegram/send</code></td><td>Send a message — body: <code>{"message":"..."}</code></td></tr>
<tr><td>POST</td><td><code>/api/plugin/fpp-telegram/test</code></td><td>Send a test message — body: <code>{"message":"..."}</code></td></tr>
<tr><td>POST</td><td><code>/api/plugin/fpp-telegram/toggle-notifications</code></td><td>Toggle the master notifications switch</td></tr>
</table>

<h3>Troubleshooting</h3>
<ul>
    <li><strong>Test message fails with "Unauthorized":</strong> Your Bot Token is incorrect. Re-copy it from @BotFather.</li>
    <li><strong>"Chat not found" or "Bad Request":</strong> Your Chat ID is wrong. Re-check getUpdates or try a positive integer for personal chats.</li>
    <li><strong>No response at all:</strong> FPP cannot reach api.telegram.org. Check internet connectivity. If behind a firewall, configure a proxy in the Advanced tab.</li>
    <li><strong>Bot doesn't receive your messages:</strong> Make sure you started a conversation with the bot first (send it <code>/start</code>).</li>
    <li><strong>Group messages not arriving:</strong> Add the bot to the group and ensure it has permission to send messages. Use the negative group ID.</li>
    <li><strong>No notifications at all:</strong> Check the master switch on the Event Notifications tab — it may be disabled.</li>
</ul>
<p>Detailed activity is logged to <code>/home/fpp/media/logs/fpp-telegram.log</code> (visible under <em>Status → Logs</em>).</p>

<h3>HTML Formatting</h3>
<p>Messages are sent using Telegram's HTML format. You can use these tags in your message templates:</p>
<pre>&lt;b&gt;bold&lt;/b&gt;
&lt;i&gt;italic&lt;/i&gt;
&lt;u&gt;underline&lt;/u&gt;
&lt;code&gt;monospace&lt;/code&gt;
&lt;a href="https://example.com"&gt;link&lt;/a&gt;</pre>
<p>Variable values (playlist and sequence names) are automatically escaped, so special characters in those names will not break formatting.</p>

<h3>About</h3>
<p>
fpp-telegram plugin for Falcon Player.<br>
Inspired by the <a href="https://plugins.octoprint.org/plugins/telegram/" target="_blank">OctoPrint-Telegram</a> plugin.<br>
Source: <a href="https://github.com/jpier1/fpp-telegram" target="_blank">github.com/jpier1/fpp-telegram</a>
</p>
</div>
