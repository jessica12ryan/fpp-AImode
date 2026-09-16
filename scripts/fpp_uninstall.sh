#!/bin/bash
# fpp-AImode uninstall — reverses install side effects, safe to run twice (§2.1, §2.2)
set -e
: "${FPPDIR:=/opt/fpp}"
# shellcheck disable=SC1091
if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
fi
: "${MEDIADIR:=/home/fpp/media}"
: "${LOGDIR:=${MEDIADIR}/logs}"
mkdir -p "${LOGDIR}" 2>/dev/null || true

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGINDATA="${MEDIADIR}/plugindata/fpp-AImode"
PLUGIN_LOG="${LOGDIR}/plugin-fpp-AImode.log"

echo "$(date '+%Y-%m-%d %H:%M:%S') fpp-AImode: uninstall" >> "${PLUGIN_LOG}" 2>/dev/null || true
echo "fpp-AImode: uninstalling"

# Remove plugindata if it was created by this plugin — idempotent, no error on second run
# Do not delete logs: FPP manages plugin-*.log rotation and expects to keep it for support
if [ -d "${PLUGINDATA}" ]; then
    echo "fpp-AImode: removing plugindata ${PLUGINDATA}"
    rm -rf "${PLUGINDATA}" 2>/dev/null || true
fi

# No systemd units, timers, cron, /etc files, or apache CSP whitelists were ever created by this plugin
# (§4.4 installer never changes network exposure), so nothing else to reverse.

# The plugin directory itself will be removed by FPP after this script exits — no need to delete it here.
# Ensure we exit 0 even if items were already gone (§2.2)

echo "fpp-AImode: uninstall complete" | tee -a "${PLUGIN_LOG}" 2>/dev/null || true
exit 0
