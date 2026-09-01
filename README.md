# fpp-telegram

**Telegram Notifications plugin for [Falcon Player (FPP)](https://github.com/FalconChristmas/fpp)**

Send Telegram messages when FPP playlist and media events occur. Configure a Telegram Bot to receive real-time notifications when shows start, sequences play, or FPP itself starts and stops.

![Version](https://img.shields.io/badge/version-1.4.4-blue)
![FPP](https://img.shields.io/badge/FPP-6.0%2B-green)
![License](https://img.shields.io/badge/license-MIT-lightgrey)

---

## Features

- **Automatic event notifications** — FPP Startup/Shutdown, Playlist Start/End, Sequence/Media Start/End
- **Customizable message templates** with live variable substitution (`%PLAYLIST%`, `%SEQUENCE%`, `%HOSTNAME%`, `%DATETIME%`, `%STATUS%`)
- **HTML formatting** support with auto-escaped variable values
- **FPP Commands** — trigger messages or toggle notifications at any step in a playlist
- **Per-template Send Sample buttons** — test each message template individually from the settings page
- **REST API** — send messages programmatically from external tools or scripts
- **Activity log** — every send, skip, and error is written to `/home/fpp/media/logs/fpp-telegram.log`
- **HTTP Proxy** support for restricted networks
- **Non-blocking event delivery** — callbacks return immediately so FPP show playback is never delayed
- **Settings persist across updates** — stored in `/home/fpp/media/config/` outside the plugin directory

---

## Settings Interface

The plugin adds a **Content → Telegram Notifications** menu entry with a four-tab settings page:

| Tab | Purpose |
|---|---|
| **Bot Setup** | Enter Bot Token (masked, eye-icon toggle) and Chat ID, with step-by-step instructions for finding each; includes an inline **Send Test** section |
| **Event Notifications** | Master on/off switch plus per-event enable/disable toggles |
| **Message Templates** | Customize the message text for each event; each template has its own **Send Sample** button |
| **Advanced** | Link preview toggle, FPP Command enable, HTTP proxy |

---

## Installation

Install via the FPP Plugin Manager:

1. Go to **Content → FPP Plugins**
2. Click **Add Plugin** and paste the plugin URL:
   ```
   https://raw.githubusercontent.com/jpier1/fpp-telegram/refs/heads/master/pluginInfo.json
   ```
3. Click **Install** — the install script sets file permissions and adds `api.telegram.org` to the Apache Content Security Policy automatically
4. Go to **Content → Telegram Notifications** to configure

---

## Quick Setup

### Step 1 — Create a Telegram Bot

Message [@BotFather](https://t.me/BotFather) on Telegram and send `/newbot`. Follow the prompts and copy the **Bot Token** it returns.

Format: `123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ`

### Step 2 — Find Your Chat ID

After sending your bot any message (e.g. `/start`), open this URL in a browser:
```
https://api.telegram.org/botYOUR_TOKEN/getUpdates
```
Find `"chat":{"id": XXXXXXXXX}` in the response — that number is your Chat ID.

For a **group or channel**: add the bot to the group, send a message there, then check `getUpdates` again. Group IDs start with a minus sign (e.g. `-1001234567890`).

### Step 3 — Configure

1. Go to **Content → Telegram Notifications → Bot Setup**
2. Enter your **Bot Token** and **Chat ID**, click **Save Bot Settings**
3. Go to **Event Notifications**, enable the events you want, click **Save**
4. Use the **Send Test** section on the Bot Setup tab to confirm everything works

---

## Event Notifications

| Event | When it fires |
|---|---|
| FPP Startup | After the FPPD daemon finishes starting |
| FPP Shutdown | Before the FPPD daemon stops (while network is still up) |
| Playlist Started | When any playlist begins playing |
| Playlist Ended | When a playlist finishes or is stopped |
| Sequence Started | When a sequence or media file begins |
| Sequence Ended | When a sequence or media file finishes |

Events use FPP's built-in callback system:
- **`callbacks.php`** — auto-discovered by FPP for playlist and media events
- **`scripts/postStart.sh`** — auto-discovered by FPP for daemon startup
- **`scripts/preStop.sh`** — auto-discovered by FPP for daemon shutdown

All event sends are **non-blocking** — FPP continues immediately while the message is delivered in the background.

---

## Message Templates & Variables

Customize the text sent for each event. Variables are substituted at send time:

| Variable | Replaced with |
|---|---|
| `%PLAYLIST%` | Current playlist name |
| `%SEQUENCE%` | Sequence or media filename |
| `%STATUS%` | Current FPP playback status |
| `%HOSTNAME%` | Hostname of this FPP device |
| `%DATETIME%` | Current date and time (`YYYY-MM-DD HH:MM:SS`) |

**Example:** `🎄 Show started on %HOSTNAME%: %PLAYLIST% — %DATETIME%`

Use the **Send Sample** button on the Message Templates tab to preview how each template will look before enabling the notification.

---

## FPP Commands

The plugin registers these commands in FPP's command system, available in playlist steps:

| Command | Argument | Description |
|---|---|---|
| `Telegram - Send Message` | Message text | Send a custom message with variable substitution |
| `Telegram - Enable Notifications` | — | Turn the master notifications switch on |
| `Telegram - Disable Notifications` | — | Turn the master notifications switch off |

Playlist and sequence notifications are event-driven only — they fire automatically and are not available as manual commands.

---

## REST API

Base path: `/api/plugin/fpp-telegram/`

| Method | Endpoint | Body | Description |
|---|---|---|---|
| `GET` | `/version` | — | Returns plugin version |
| `GET` | `/settings` | — | Returns current settings (bot token masked) |
| `POST` | `/settings` | JSON settings object | Save settings |
| `POST` | `/send` | `{"message":"..."}` | Send a message (supports variable keys: `playlist`, `sequence`, `status`) |
| `POST` | `/test` | `{"message":"..."}` | Send a test message |

**Send example:**
```bash
curl -X POST http://fpp.local/api/plugin/fpp-telegram/send \
  -H "Content-Type: application/json" \
  -d '{"message":"Show starting: %PLAYLIST%", "playlist":"Christmas 2025"}'
```

---

## Logging

Every action is logged to `/home/fpp/media/logs/fpp-telegram.log`. This file is visible in the FPP UI under **Status → Logs**.

Log entries include a timestamp, severity level, and source label:
```
[2026-06-02 20:00:01] [INFO]  Plugin installed/updated (FPP version: 8.1)
[2026-06-02 20:05:14] [INFO]  Send dispatched (background) [playlist_start]: FPP: Playlist "Christmas Show" started.
[2026-06-02 20:05:58] [INFO]  Sent [api_test]: This is a test message from Falcon Player!
[2026-06-02 20:06:03] [ERROR] Send failed [api_test]: Unauthorized
[2026-06-02 20:10:00] [INFO]  Settings saved via UI
```

The log is automatically capped at 512 KB — oldest entries are discarded when the limit is reached.

---

## Settings Persistence

User settings are stored at `/home/fpp/media/config/plugin.fpp-telegram.json` — **outside the plugin directory** — so they survive plugin updates. Settings are only removed when the plugin is uninstalled.

All 18 settings persist across updates:
Bot Token, Chat ID, all six notification toggles, all six message templates, message format, link preview, proxy URL, and FPP Command enable.

---

## Requirements

- FPP **6.0 or later**
- `curl` and `php-curl` (installed automatically)
- Internet access to `api.telegram.org` (or configure an HTTP proxy in Advanced settings)

---

## Upgrading FPP

If you upgrade FPP to a new major version (e.g. FPP 9 → FPP 10), the OS is re-imaged and all plugins are removed. After the upgrade, reinstall the plugin via the Plugin Manager using the URL above. Your settings at `/home/fpp/media/config/plugin.fpp-telegram.json` are stored on the media partition and will be preserved.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "Unauthorized" error | Bot Token is wrong | Re-copy from @BotFather — no extra spaces |
| "Chat not found" | Chat ID is wrong | Re-check `getUpdates`; use negative ID for groups |
| No response at all | FPP can't reach Telegram | Check internet connectivity; set a proxy in Advanced if needed |
| Bot doesn't receive messages | Bot never started | Send your bot `/start` in Telegram first |
| Group messages not arriving | Bot lacks permission | Make bot an admin, or allow it to send messages in group settings |
| No notifications at all | Master switch is off | Check the master switch on the Event Notifications tab |

Check `/home/fpp/media/logs/fpp-telegram.log` for detailed error messages.

---

## File Structure

```
fpp-telegram/
├── pluginInfo.json              # Plugin metadata and FPP version requirements
├── menu.inc                     # Registers "Telegram Notifications" under the Content menu
├── plugin_setup.php             # Four-tab settings UI
├── api.php                      # REST API endpoints
├── callbacks.php                # FPP auto-called for playlist/media events
├── CHANGELOG.md
├── commands/
│   ├── descriptions.json              # Registers FPP commands
│   ├── TelegramSendMessage.php        # Manual send command
│   ├── TelegramEnableNotifications.php
│   ├── TelegramDisableNotifications.php
│   ├── TelegramFPPStarted.php         # Called by postStart.sh
│   └── TelegramFPPStopped.php         # Called by preStop.sh
├── scripts/
│   ├── fpp_install.sh           # Runs on install/update; migrates settings if needed
│   ├── fpp_uninstall.sh         # Runs on uninstall; removes persistent settings
│   ├── postStart.sh             # FPP daemon startup hook
│   ├── preStop.sh               # FPP daemon shutdown hook
│   ├── sendTelegram.sh          # Bash curl wrapper for Telegram API
│   └── telegramFunctions.php    # Shared PHP: logging, config, send
└── help/
    └── help.php                 # In-UI help and setup guide
```

**Persistent data** (outside plugin directory, survives updates):
- Settings: `/home/fpp/media/config/plugin.fpp-telegram.json`
- Log: `/home/fpp/media/logs/fpp-telegram.log`

---

## License

MIT

## Credits

Inspired by the [OctoPrint-Telegram](https://github.com/jacopotediosi/OctoPrint-Telegram) plugin by [@jacopotediosi](https://github.com/jacopotediosi).
