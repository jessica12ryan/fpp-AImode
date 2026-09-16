#!/bin/bash
set -e
# fpp-AImode install — idempotent, no sudo (already root), no network side effects

: "${FPPDIR:=/opt/fpp}"
# shellcheck disable=SC1091
if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
fi
# common sets LOGDIR/MEDIADIR per §1.1 when on FPP

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
: "${MEDIADIR:=/home/fpp/media}"
: "${LOGDIR:=${MEDIADIR}/logs}"
mkdir -p "${LOGDIR}" 2>/dev/null || true
PLUGINDATA="${MEDIADIR}/plugindata/fpp-AImode"
PLUGIN_LOG="${LOGDIR}/plugin-fpp-AImode.log"

echo "$(date '+%Y-%m-%d %H:%M:%S') fpp-AImode: install to ${PLUGIN_DIR} plugindata ${PLUGINDATA}" >> "${PLUGIN_LOG}" 2>/dev/null || true
echo "fpp-AImode: installing to ${PLUGIN_DIR} (plugindata ${PLUGINDATA})"

# plugindata for credentials per §14.11 — 0600 files, not config/
mkdir -p "${PLUGINDATA}"
chmod 775 "${PLUGINDATA}" 2>/dev/null || true

# Migrate legacy config/settings.json -> plugindata if present
if [ -f "${PLUGIN_DIR}/config/settings.json" ] && [ ! -f "${PLUGINDATA}/settings.json" ]; then
    echo "fpp-AImode: migrating legacy config/settings.json to plugindata"
    cp "${PLUGIN_DIR}/config/settings.json" "${PLUGINDATA}/settings.json" 2>/dev/null || true
    chmod 600 "${PLUGINDATA}/settings.json" 2>/dev/null || true
fi
if [ -f "${PLUGIN_DIR}/config/history.json" ] && [ ! -f "${PLUGINDATA}/history.json" ]; then
    cp "${PLUGIN_DIR}/config/history.json" "${PLUGINDATA}/history.json" 2>/dev/null || true
    chmod 600 "${PLUGINDATA}/history.json" 2>/dev/null || true
fi

# Ensure perms are correct (idempotent) — never 0777
chmod 775 "${PLUGIN_DIR}" 2>/dev/null || true
if [ -d "${PLUGIN_DIR}/config" ]; then chmod 775 "${PLUGIN_DIR}/config" 2>/dev/null || true; fi
if [ -f "${PLUGINDATA}/settings.json" ]; then chmod 600 "${PLUGINDATA}/settings.json" 2>/dev/null || true; fi
if [ -f "${PLUGINDATA}/history.json" ]; then chmod 600 "${PLUGINDATA}/history.json" 2>/dev/null || true; fi
# Legacy files if still present — tighten but don't delete on install (migration keeps them until save)
if [ -f "${PLUGIN_DIR}/config/settings.json" ]; then chmod 600 "${PLUGIN_DIR}/config/settings.json" 2>/dev/null || true; fi
if [ -f "${PLUGIN_DIR}/config/history.json" ]; then chmod 600 "${PLUGIN_DIR}/config/history.json" 2>/dev/null || true; fi

# php-curl is declared in pluginInfo dependencies (FPP 10+ handles it). For older FPP or manual installs,
# install ad-hoc via apt without sudo — already root. Idempotent: check first, don't fail on second run.
if ! php -m 2>/dev/null | grep -qi curl; then
    echo "fpp-AImode: php-curl not found, installing via apt-get"
    apt-get update 2>&1 | tee -a "${PLUGIN_LOG}" 2>/dev/null || true
    apt-get install -y php-curl 2>&1 | tee -a "${PLUGIN_LOG}" 2>/dev/null || true
fi

# No extra services, cron, or network changes (§4.4 installer may not change exposure)
echo "fpp-AImode: install complete" | tee -a "${PLUGIN_LOG}" 2>/dev/null || true
exit 0
