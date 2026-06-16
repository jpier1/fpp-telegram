#!/bin/bash
#
# sendTelegram.sh - Core Telegram message sender for fpp-telegram plugin
#
# Usage:
#   sendTelegram.sh <bot_token> <chat_id> <message> [proxy_url] [disable_preview]
#
# All args are positional. Empty strings are accepted.
# Messages are always sent with parse_mode=HTML.

BOT_TOKEN="${1}"
CHAT_ID="${2}"
MESSAGE="${3}"
PROXY_URL="${4:-}"
DISABLE_PREVIEW="${5:-1}"

if [ -z "$BOT_TOKEN" ] || [ -z "$CHAT_ID" ] || [ -z "$MESSAGE" ]; then
    echo '{"ok":false,"description":"Missing required arguments: bot_token, chat_id, message"}' >&2
    exit 1
fi

URL="https://api.telegram.org/bot${BOT_TOKEN}/sendMessage"

# Build curl args array
CURL_ARGS=(
    -s
    --max-time 15
    --connect-timeout 10
    -X POST
    "$URL"
    --data-urlencode "chat_id=${CHAT_ID}"
    --data-urlencode "text=${MESSAGE}"
)

CURL_ARGS+=(--data-urlencode "parse_mode=HTML")

if [ "$DISABLE_PREVIEW" = "1" ]; then
    CURL_ARGS+=(--data-urlencode "disable_web_page_preview=true")
fi

if [ -n "$PROXY_URL" ]; then
    CURL_ARGS+=(-x "$PROXY_URL")
fi

RESPONSE=$(curl "${CURL_ARGS[@]}" 2>&1)
EXIT_CODE=$?

echo "$RESPONSE"
exit $EXIT_CODE
