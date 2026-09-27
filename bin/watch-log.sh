#!/usr/bin/env bash
# =====================================================================
# bin/watch-log.sh
# =====================================================================
#
# Tails the application log with level coloring. Leave this running
# in a dedicated terminal during development. Any PHP warning,
# notice, exception, or explicitly-logged message streams into view
# in real time.
#
# This catches the class of bugs that never reach the browser:
# caught exceptions, deprecation warnings, silent failures inside
# try/catch blocks, and anything the code logs but doesn't display.
#
# Usage:
#   bash bin/watch-log.sh
#
# Or from Laragon Terminal (Git Bash):
#   bash bin/watch-log.sh
#
# =====================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOG_FILE="${PROJECT_ROOT}/storage/logs/app.log"

if [ ! -d "${PROJECT_ROOT}/storage/logs" ]; then
    echo "ERROR: storage/logs/ directory does not exist." >&2
    echo "       Create it or run 'php bin/migrate.php' first." >&2
    exit 1
fi

if [ ! -f "${LOG_FILE}" ]; then
    echo "Log file does not exist yet. Creating empty file."
    touch "${LOG_FILE}"
fi

# ANSI colors. Using $'\033' (ANSI-C quoting) so bash produces the
# actual ESC byte, not the literal text \033. Without this, sed
# writes the escape sequence as text and the terminal shows it
# instead of interpreting it.
ESC=$'\033'
RED="${ESC}[0;31m"
YELLOW="${ESC}[1;33m"
CYAN="${ESC}[0;36m"
DIM="${ESC}[2m"
BOLD="${ESC}[1m"
RESET="${ESC}[0m"

echo ""
printf '%sWatching:%s %s\n' "${BOLD}" "${RESET}" "${LOG_FILE}"
printf '%sPress Ctrl+C to stop. Leave this terminal open during dev.%s\n' "${DIM}" "${RESET}"
printf '%s--------------------------------------------------------------------%s\n' "${DIM}" "${RESET}"
echo ""

# Tail the log and highlight level keywords. The sed substitutions
# use the actual ESC byte (via the shell variables above) so the
# terminal interprets the colors correctly.
tail -n 20 -f "${LOG_FILE}" | sed \
    -e "s/\(ERROR\)/${RED}\1${RESET}/g" \
    -e "s/\(WARNING\|WARN\)/${YELLOW}\1${RESET}/g" \
    -e "s/\(EXCEPTION\|FATAL\)/${RED}\1${RESET}/g" \
    -e "s/\(INFO\)/${CYAN}\1${RESET}/g" \
    -e "s/\(DEBUG\|NOTICE\)/${DIM}\1${RESET}/g"
