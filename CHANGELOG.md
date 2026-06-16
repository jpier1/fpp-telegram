# Changelog

All notable changes to fpp-telegram are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.1] - 2026-06-02

### Improved
- FPP startup/shutdown now log as `fpp_start`/`fpp_stop` source labels
  instead of the generic `event`; manual Send Message command logs as `manual_send`
- Notifications suppressed by the master gate now write an INFO log entry
  so the log explains why a notification did not arrive
- Callback log entry now includes the action (start/stop) and playlist or
  sequence name for easier troubleshooting

## [1.3.0] - 2026-06-02

### Fixed
- Send Sample buttons for Playlist and Sequence message templates were
  silently broken — JSON sample vars in the onclick attribute contained
  unescaped double quotes that terminated the HTML attribute early
- Removed obsolete settings migration code from fpp_install.sh

### Changed
- Removed third-party plugin references from CHANGELOG

## [1.2.9] - 2026-06-02

### Changed
- Removed Telegram - Playlist Started/Ended and Sequence Started/Ended from
  the FPP command dropdown; these are event-only triggers via callbacks.php
  and should not be available as manual playlist commands
- Simplified "Telegram - Send Message" argument label to "Message text to send:"

## [1.2.8] - 2026-06-02

### Fixed
- `fpp_install.sh` now sets `core.fileMode false` for the plugin repository
  so `chmod +x` on plugin files is never tracked as a local modification;
  previously this caused `git pull` to fail with "changes not staged for
  commit" on every subsequent Update Now click

## [1.2.7] - 2026-06-02

### Changed
- Locked to HTML formatting only — parse_mode removed from settings,
  defaults, bash send path, and sendTelegram.sh parameter list; HTML is
  now hardcoded directly where needed
- Removed `mb_substr()`/`mb_strlen()` — replaced with `substr()`/`strlen()`
  since the mbstring PHP extension is not installed on FPP Pi devices
- Notifications toggle converted from form POST to JavaScript fetch API
  so the status updates in-place with no page reload

### Fixed
- Notifications toggle button caused blank page refresh — now handled via
  fetch API with in-place DOM update
- Fatal error on test send when mbstring extension not available

### Removed
- `send_on_command` setting (was hardcoded, never checked — fully dead)
- Orphaned `toggle_notifications` PHP POST handler (UI uses fetch API)
- Dead Markdown/MarkdownV2 escaping code paths in `telegramEscapeValue()`
- Redundant `parse_mode` argument from all `telegramApplyVars()` call sites

## [1.2.6] - 2026-06-02

### Fixed
- Notifications toggle button no longer causes a blank page refresh;
  converted from form POST to JavaScript fetch so the status label,
  button text, and disabled banner all update in-place without any reload

## [1.2.5] - 2026-06-02

### Changed
- Version bump for clean end-to-end update test

## [1.2.4] - 2026-06-02

### Fixed
- Rewrote `fpp_install.sh` following FPP plugin best practices: `set -e`, simple
  path derivation, FPP `common` sourced at the bottom with a guard (fixes
  verbose output not streaming), defensive CSP check, proper error recovery
- Added `git config --global --add safe.directory` for root so FPP's
  `sudo git pull` works correctly in the fpp-owned plugin directory
  (git 2.35.2+ security requirement)

## [1.2.3] - 2026-06-02

### Fixed
- Rewrote `fpp_install.sh` following FPP plugin conventions — removed all git
  pull and chown operations; FPP's native `sudo git pull` handles code updates
  and the plugin directory must remain fpp-owned for it to work correctly
- Added verbose `[INFO]`/`[OK]` output to the install script so Update Now in
  the FPP plugin manager shows clear progress

## [1.2.2] - 2026-06-02

### Fixed
- All plugin forms now include an explicit `action` URL so POST submissions
  always return to the correct plugin page instead of a blank page in FPP

## [1.2.1] - 2026-06-02

### Fixed
- `fpp_install.sh` now runs `git pull` at the start so clicking "Update Now"
  in the FPP Plugin Manager pulls the latest code without requiring SSH
- After the pull, `chown -R fpp:fpp` restores ownership of the plugin directory
  so the `fpp` user can also run git commands from an SSH session

## [1.1.0] - 2026-06-02

### Added
- Automatic escaping of substituted variable values (`%PLAYLIST%`, `%SEQUENCE%`,
  `%HOSTNAME%`, `%DATETIME%`) for the configured parse mode — HTML entities for HTML,
  backslash escaping for Markdown/MarkdownV2 — so playlist/sequence names with special
  characters never break message formatting or cause Telegram API parse errors
- Message Templates tab: live format cheatsheet showing the top 5 markup examples for
  the currently saved format (HTML, Markdown, MarkdownV2, or Plain Text notice)
- Message Templates tab: "change in Advanced tab" links correctly switch to the
  Advanced tab via JavaScript (previously navigated to a non-functional URL hash)
- Settings persist across plugin updates — stored at
  `/home/fpp/media/config/plugin.fpp-telegram.json` (FPP's standard persistent
  user-data area), automatically migrated from the old in-plugin location on first update
- `scripts/fpp_uninstall.sh` — removes persistent settings on uninstall
- Activity log at `/home/fpp/media/logs/fpp-telegram.log` for all send events, settings
  saves, install/uninstall, and lifecycle hooks; log is auto-capped at 512 KB
- Per-event **Send Sample** buttons on the Message Templates tab with inline ✓/✗ feedback
- Eye icon (SVG) toggle on the Bot Token field; hidden by default on page load
- Inline **Send Test** section on the Bot Setup tab (separate Test tab removed)
- `pluginVersion` field in `pluginInfo.json` as the single source of truth for version

### Changed
- Settings UI redesigned: modern four-tab layout with Telegram blue accent, pill tabs,
  card panel with shadow, focus-ring inputs, and instructional placeholder text
- Menu entry renamed from "Telegram" to "Telegram Notifications"
- Page title broken image removed; replaced with inline SVG Telegram icon
- All event sends in `callbacks.php` are non-blocking (fire-and-forget via `exec &`) so
  FPP playlist/media callbacks return immediately, never delaying show playback
- `fpp_install.sh` `PLUGIN_DIR` now derived from the script's own location (`readlink -f`)
  rather than hardcoded `$FPPDIR` (was pointing to wrong `/opt/fpp/plugins/` path)
- `api.php` requires `telegramFunctions.php`; duplicate `loadTelegramSettingsAPI()` and
  `telegramApiLog()` removed — single implementations used throughout
- `loadTelegramConfig()` now restores default message templates when a template was
  saved as an empty string, preventing blank Telegram messages from command scripts

### Fixed
- `getTelegramLogFile()` used `FPPHOME` directly as base path — when `FPPHOME=/home/fpp`
  (standard FPP), CLI-context log writes went to non-existent `/home/fpp/logs/`; fixed
  to `/home/fpp/media/logs/fpp-telegram.log`
- Bot Setup tab form was missing hidden fields for all non-displayed settings; saving
  Bot Setup wiped all message templates with empty strings on every save
- Fetch-based test sends showed "Request failed" despite message being delivered because
  `r.json()` threw on non-pure-JSON responses; replaced with `r.text()` + manual parse
- Settings file writes now use `LOCK_EX` to prevent JSON corruption under concurrent saves
- `postTelegramSettings()` whitelists 18 known setting keys before persisting
- `postTelegramTest()` sanitises the user-supplied `source` label to prevent log injection
- FPP plugin manager now correctly clears "Update Available" after update (sha pinning)

## [1.0.0] - 2026-06-01

### Added
- Telegram Bot API integration via `curl` — no third-party libraries required
- Automatic event notifications: FPP startup, FPP shutdown, playlist start/end, sequence/media start/end
- `callbacks.php` — FPP auto-discovery hook for playlist and media events (`--list`, `--type`, `--data`)
- `scripts/postStart.sh` — lifecycle hook called automatically by FPP after daemon starts
- `scripts/preStop.sh` — lifecycle hook called automatically by FPP before daemon stops (while network is still up)
- Five FPP Commands for playlist use: `Telegram - Send Message`, `Telegram - Playlist Started/Ended`, `Telegram - Sequence Started/Ended`
- Message template variables: `%PLAYLIST%`, `%SEQUENCE%`, `%STATUS%`, `%HOSTNAME%`, `%DATETIME%`
- Tabbed settings UI: Bot Setup, Event Notifications, Message Templates, Advanced, Test
- Bot Token masked (show/hide toggle) on settings page
- Chat ID finder instructions built into the Bot Setup tab
- Send Test Message from the UI with live API response feedback
- Current configuration status panel on Test tab
- HTML, Markdown, MarkdownV2, and Plain Text parse mode selection
- HTTP proxy support for restricted networks
- Disable link preview option
- REST API endpoints: `GET /version`, `GET /settings`, `POST /settings`, `POST /send`, `POST /test`
- `scripts/sendTelegram.sh` — standalone bash Telegram sender (usable independently)
- `scripts/telegramFunctions.php` — shared PHP functions used by all command scripts
- `pluginVersion` field in `pluginInfo.json` as the single source of truth for the plugin version
- Version badge displayed in the settings page footer
- Help page with step-by-step setup guide, variable reference, and troubleshooting section
- Apache CSP policy update for `api.telegram.org` in `fpp_install.sh`
- `.gitattributes` enforcing LF line endings for all shell scripts and PHP files

[Unreleased]: https://github.com/jpier1/fpp-telegram/compare/v1.3.1...HEAD
[1.3.1]: https://github.com/jpier1/fpp-telegram/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/jpier1/fpp-telegram/compare/v1.2.9...v1.3.0
[1.2.9]: https://github.com/jpier1/fpp-telegram/compare/v1.2.8...v1.2.9
[1.2.8]: https://github.com/jpier1/fpp-telegram/compare/v1.2.7...v1.2.8
[1.2.7]: https://github.com/jpier1/fpp-telegram/compare/v1.2.6...v1.2.7
[1.2.6]: https://github.com/jpier1/fpp-telegram/compare/v1.2.5...v1.2.6
[1.2.5]: https://github.com/jpier1/fpp-telegram/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/jpier1/fpp-telegram/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/jpier1/fpp-telegram/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/jpier1/fpp-telegram/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/jpier1/fpp-telegram/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/jpier1/fpp-telegram/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/jpier1/fpp-telegram/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/jpier1/fpp-telegram/releases/tag/v1.0.0
