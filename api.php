<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## api.php                                                 ##
 * #############################################################
 */

define('AIM_PLUGIN_DIR', __DIR__);
define('AIM_MAX_HISTORY', 40);
define('AIM_MAX_TOKENS_DEFAULT', 2048);
// The Config page never receives stored API keys (§14.4). When the key field is
// left blank for a provider that already has one, the browser sends this
// sentinel and the server restores the stored key instead of overwriting it.
define('AIM_KEEP_KEY_SENTINEL', '__AIM_KEEP_KEY__');
// Conversation history and chat messages are kept for at most this many days.
// This is the retention promised in pluginInfo.json's privacy.collects block.
define('AIM_HISTORY_KEEP_DAYS', 30);

function aimResolveKeepKey($key, $settings, $provider) {
    if (trim((string)$key) === AIM_KEEP_KEY_SENTINEL) {
        return aimGetProviderConfig($settings, $provider)['api_key'] ?? '';
    }
    return trim((string)$key);
}

// FPP's /api answers every origin and has no CSRF token, so a page on another
// site could otherwise drive this plugin's state-changing routes (§14.12).
// Browsers always send Origin on POST/PUT/DELETE; refuse a mismatched one.
// Requests with neither header are non-browser API clients, which are not a
// CSRF vector.
function aimSameOriginRequest() {
    $host = strtolower((string)(parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?? ''));
    if ($host === '') return true;
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $oh = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
        return $oh === $host;
    }
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $rh = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
        return $rh === $host;
    }
    return true;
}
function aimDenyCrossOrigin() {
    return json(['success' => false, 'error' => 'Cross-origin request refused — use the AI Mode pages in FPP.']);
}

// FPP's eight operator-owned privacy settings are off limits to plugins
// entirely (§14.9), and core credentials must never be forwarded to an AI
// provider without an explicit reads-core-credentials disclosure (§14.11).
// Match by name pattern as well as exact key: FPP's password-typed settings
// include compound names such as emailpass, gitHubPAT, osPassword,
// passwordVerify and TetherPSK, all of which would otherwise leak through
// the get_settings tool.
function aimIsProtectedSettingKey($key) {
    $k = strtolower((string)$key);
    static $privacySettings = [
        'statspublish','statspublishurl','sharecrashdata','fetchvendorlogos',
        'sendvendorserial','sendvendorlogos','privacyconsent','legaljurisdiction',
    ];
    if (in_array($k, $privacySettings, true)) return true;
    // Crash-bundler style redaction (§14.4): these substrings always mean a secret.
    if (preg_match('/(?:pass|pwd|psk|secret|token|credential|apikey|api_key)/', $k)) return true;
    // 'pat', 'key' and 'auth' only when not followed by a lowercase letter
    // (so keyframe, monkey, authentic... are not mistaken for credentials).
    if (preg_match('/(?:pat|key|auth)(?![a-z])/', $k)) return true;
    return false;
}

/* ── Logging — exactly one file: <logdir>/plugin-fpp-AImode.log ── */
/* Resolves log dir the FPP-provided way: PHP $settings['logDirectory'] first, then LOGDIR env */

function aimGetLogFile() {
    // GUIDELINE §1.1 — resolve log dir the FPP-provided way: PHP $settings['logDirectory'] first, then LOGDIR env
    if (isset($GLOBALS['settings']['logDirectory']) && $GLOBALS['settings']['logDirectory'] !== '') {
        return rtrim($GLOBALS['settings']['logDirectory'], '/') . '/plugin-fpp-AImode.log';
    }
    $env = getenv('LOGDIR');
    if ($env && $env !== '') {
        return rtrim($env, '/') . '/plugin-fpp-AImode.log';
    }
    $media = $GLOBALS['settings']['mediaDirectory'] ?? getenv('MEDIADIR') ?: null;
    if ($media && $media !== '') {
        return rtrim($media, '/') . '/logs/plugin-fpp-AImode.log';
    }
    // No FPP context (CLI lint outside FPP) — no file to write, caller will no-op
    return null;
}



function aimLog($msg) {
    // Redact the actual stored credentials as well as known key shapes —
    // Mistral and Azure keys have no recognisable prefix (§1.3, §14.4).
    static $redacting = false;
    if (!$redacting) {
        $redacting = true;
        $s = @aimLoadSettings();
        if (is_array($s)) {
            $secrets = [];
            foreach (($s['providers'] ?? []) as $c) {
                if (!empty($c['api_key'])) $secrets[] = (string)$c['api_key'];
            }
            if (!empty($s['api_key'])) $secrets[] = (string)$s['api_key'];
            foreach (array_unique($secrets) as $secret) {
                if (strlen($secret) >= 4) $msg = str_replace($secret, '***REDACTED***', $msg);
            }
        }
        $redacting = false;
    }
    $msg = preg_replace('/(sk-[A-Za-z0-9_\-]{8,}|sk-ant-[A-Za-z0-9_\-]{8,}|sk-or-[A-Za-z0-9_\-]{8,}|oc_sk_[A-Za-z0-9_\-]{8,}|xai-[A-Za-z0-9]{8,}|AIza[0-9A-Za-z_\-]{20,})/', '***REDACTED***', $msg);
    $msg = preg_replace('/("api_key"\s*:\s*")[^"]+(")/', '$1***$2', $msg);
    $logFile = aimGetLogFile();
    if ($logFile === null || $logFile === '') return;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' fpp-AImode api: ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/* ── Provider catalog ── */

function aimGetProviders() {
    return [
        'openai' => [
            'label' => 'OpenAI',
            'defaultBase' => 'https://api.openai.com/v1',
            'defaultModel' => 'gpt-4o-mini',
            'models' => ['gpt-4o','gpt-4o-mini','gpt-4-turbo','o1','o1-mini','o3-mini','o3','gpt-5','gpt-5-mini','gpt-5-nano','gpt-5.6-sol','gpt-5.6-terra','gpt-5.6-luna','gpt-4.1','gpt-4.1-mini'],
            'auth' => 'bearer',
            'needsKey' => true,
        ],
        'anthropic' => [
            'label' => 'Anthropic (Claude)',
            'defaultBase' => 'https://api.anthropic.com',
            'defaultModel' => 'claude-3-5-sonnet-20241022',
            'models' => ['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-3-opus-20240229','claude-3-haiku-20240307','claude-sonnet-4-6','claude-opus-4-8','claude-opus-5','claude-sonnet-5','claude-haiku-4-5'],
            'auth' => 'x-api-key',
            'needsKey' => true,
        ],
        'google' => [
            'label' => 'Google Gemini',
            'defaultBase' => 'https://generativelanguage.googleapis.com',
            'defaultModel' => 'gemini-3.6-flash',
            'models' => ['gemini-3.6-flash','gemini-3.8-flash','gemini-3.7-flash','gemini-3.5-flash','gemini-3.5-flash-lite','gemini-2.5-flash','gemini-2.5-pro','gemini-1.5-flash','gemini-1.5-pro'],
            'auth' => 'query',
            'needsKey' => true,
        ],
        'mistral' => [
            'label' => 'Mistral',
            'defaultBase' => 'https://api.mistral.ai/v1',
            'defaultModel' => 'mistral-large-latest',
            'models' => ['mistral-large-latest','mistral-small-latest','mistral-nemo','open-mistral-7b','mistral-large-2407','codestral-latest'],
            'auth' => 'bearer',
            'needsKey' => true,
        ],
        'grok' => [
            'label' => 'Grok (xAI)',
            'defaultBase' => 'https://api.x.ai/v1',
            'defaultModel' => 'grok-3',
            'models' => ['grok-3','grok-3-mini','grok-3-fast','grok-2','grok-beta','grok-2-mini'],
            'auth' => 'bearer',
            'needsKey' => true,
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'defaultBase' => 'https://openrouter.ai/api/v1',
            'defaultModel' => 'openai/gpt-4o-mini',
            'models' => ['openai/gpt-4o','openai/gpt-4o-mini','openai/gpt-5','openai/gpt-5-mini','anthropic/claude-3.5-sonnet','anthropic/claude-opus-4','google/gemini-2.5-flash','google/gemini-3.6-flash','mistralai/mistral-large','x-ai/grok-3'],
            'auth' => 'bearer',
            'needsKey' => true,
        ],
        'ollama' => [
            'label' => 'Ollama (Local — not installed)',
            'defaultBase' => '',
            'defaultModel' => 'llama3.1',
            'models' => ['llama3.1','llama3.3','qwen2.5','qwen3','mistral','gemma2','gemma3','phi3','phi4','codellama','deepseek-r1'],
            'auth' => 'none',
            'needsKey' => false,
        ],
        'azure' => [
            'label' => 'Azure OpenAI',
            'defaultBase' => 'https://{your-endpoint}.openai.azure.com',
            'defaultModel' => 'gpt-4o',
            'models' => ['gpt-4o','gpt-4o-mini','gpt-35-turbo','gpt-5','gpt-5-mini','o3','o4-mini'],
            'auth' => 'api-key',
            'needsKey' => true,
        ],
        'opencode' => [
            'label' => 'OpenCode (Console)',
            'defaultBase' => 'https://opencode.ai/inference',
            'defaultModel' => 'glm-5.3',
            'models' => ['glm-5.3','glm-5.3-flash','deepseek-v4-pro','deepseek-v4.1-flash','claude-haiku-4-5','claude-sonnet-4-6','claude-opus-4-8','gpt-5','gpt-5-nano','gemini-3.6-flash','gemini-3.8-flash'],
            'auth' => 'bearer',
            'needsKey' => true,
        ],
        'opencode-server' => [
            'label' => 'OpenCode Server (experimental)',
            'defaultBase' => 'http://localhost:4096',
            'defaultModel' => '',
            'models' => [],
            'auth' => 'bearer-optional',
            'needsKey' => false,
        ],
    ];
}

/* ── OpenCode model metadata (Console + self-hosted server) ── */

define('AIM_OPENCODE_CACHE_TTL', 43200); // 12 hours

function aimModelCacheFile($provider) {
    return aimGetDataDir() . '/' . ($provider === 'opencode' ? 'opencode-models.json' : 'opencode-server-models.json');
}
function aimModelCacheLoad($provider) {
    $file = aimModelCacheFile($provider);
    if (!file_exists($file)) return null;
    $raw = @file_get_contents($file);
    $c = json_decode($raw ?: '', true);
    return is_array($c) ? $c : null;
}
function aimModelCacheSave($provider, $meta) {
    $file = aimModelCacheFile($provider);
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $meta['fetched'] = time();
    @file_put_contents($file, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    @chmod($file, 0600);
}
function aimModelCacheFresh($provider) {
    $c = aimModelCacheLoad($provider);
    return is_array($c) && isset($c['fetched']) && (time() - (int)$c['fetched'] < AIM_OPENCODE_CACHE_TTL) && !empty($c['models']);
}
function aimOpencodeModelFamily($settings, $model) {
    if (!aimModelCacheFresh('opencode')) {
        $base = aimEffectiveBaseUrl($settings, 'opencode');
        aimFetchOpencodeModels($base, aimGetProviderConfig($settings, 'opencode')['api_key'] ?? '');
    }
    $c = aimModelCacheLoad('opencode');
    return $c['models'][$model]['api'] ?? ($c['models'][$model] ?? null);
}
function aimServerModelRef($model) {
    if (!aimModelCacheFresh('opencode-server')) return null;
    $c = aimModelCacheLoad('opencode-server');
    if (!isset($c['models'][$model])) return null;
    $r = $c['models'][$model];
    if (is_array($r) && isset($r['id'], $r['providerID'])) return ['id'=>$r['id'],'providerID'=>$r['providerID']];
    if (is_string($r) && strpos($r, '/') !== false) {
        list($p, $id) = explode('/', $r, 2);
        return ['id'=>$id,'providerID'=>$p];
    }
    return null;
}

function aimProviderExists($provider) {
    return isset(aimGetProviders()[$provider]);
}

/* ── Settings ── */

function aimDefaultSettings() {
    return [
        'provider' => 'openai', // legacy single-provider compat
        'api_key' => '',
        'model' => 'gpt-4o-mini',
        'base_url' => '',
        'providers' => [], // per-provider configs: provider => [api_key, model, base_url]
        'defaultProvider' => 'openai',
        'system_prompt' => '',
        'temperature' => 0.7,
        'max_tokens' => AIM_MAX_TOKENS_DEFAULT,
        'auto_approve' => 1,
        'dry_run' => 0,
        'include_fpp_context' => 1,
        'history_enabled' => 1,
    ];
}
function aimMigrateSingleToProviders(&$s) {
    // Migrate legacy top-level provider/api_key/model/base_url into providers map if needed
    $provider = $s['provider'] ?? 'openai';
    $apiKey = $s['api_key'] ?? '';
    $model = $s['model'] ?? '';
    $base = $s['base_url'] ?? '';
    if (!isset($s['providers']) || !is_array($s['providers'])) $s['providers'] = [];
    if (!isset($s['defaultProvider']) || !$s['defaultProvider']) $s['defaultProvider'] = $provider;
    // If legacy has key/model and providers[provider] is empty, seed it
    if (($apiKey !== '' || $model !== '' || $base !== '') && empty($s['providers'][$provider])) {
        $s['providers'][$provider] = ['api_key'=>$apiKey,'model'=>$model,'base_url'=>$base];
    }
    // Ensure every known provider has an entry (at least empty) for UI convenience
    foreach (array_keys(aimGetProviders()) as $k) {
        if (!isset($s['providers'][$k]) || !is_array($s['providers'][$k])) {
            $def = aimGetProviders()[$k];
            $s['providers'][$k] = ['api_key'=>'','model'=>$def['defaultModel'] ?? '','base_url'=>''];
        } else {
            // Ensure keys exist
            if (!isset($s['providers'][$k]['api_key'])) $s['providers'][$k]['api_key'] = '';
            if (!isset($s['providers'][$k]['model'])) $s['providers'][$k]['model'] = aimGetProviders()[$k]['defaultModel'] ?? '';
            if (!isset($s['providers'][$k]['base_url'])) $s['providers'][$k]['base_url'] = '';
        }
    }
    // Keep legacy top-level in sync with defaultProvider for backward compat
    $defProv = $s['defaultProvider'] ?? $provider;
    if (isset($s['providers'][$defProv])) {
        $s['provider'] = $defProv;
        $s['api_key'] = $s['providers'][$defProv]['api_key'] ?? '';
        $s['model'] = $s['providers'][$defProv]['model'] ?? '';
        $s['base_url'] = $s['providers'][$defProv]['base_url'] ?? '';
    }
}
function aimGetProviderConfig($settings, $provider = null) {
    if (!$provider) $provider = $settings['defaultProvider'] ?? $settings['provider'] ?? 'openai';
    $provider = strtolower($provider);
    if (isset($settings['providers'][$provider]) && is_array($settings['providers'][$provider])) {
        $c = $settings['providers'][$provider];
        return [
            'provider'=>$provider,
            'api_key'=>trim((string)($c['api_key'] ?? '')),
            'model'=>trim((string)($c['model'] ?? '')),
            'base_url'=>rtrim(trim((string)($c['base_url'] ?? '')), '/')
        ];
    }
    // Fallback to legacy top-level if provider matches legacy provider
    $legacyProv = $settings['provider'] ?? 'openai';
    if ($provider === $legacyProv) {
        return [
            'provider'=>$provider,
            'api_key'=>trim((string)($settings['api_key'] ?? '')),
            'model'=>trim((string)($settings['model'] ?? '')),
            'base_url'=>rtrim(trim((string)($settings['base_url'] ?? '')), '/')
        ];
    }
    $def = aimGetProviders()[$provider] ?? null;
    return [
        'provider'=>$provider,
        'api_key'=>'',
        'model'=>$def['defaultModel'] ?? '',
        'base_url'=>''
    ];
}

/* ── Settings storage — credentials in plugindata per §14.11 / §5 ── */
/* Sensitive data (api_key) must be in plugindata, not config, so crash bundles and backups don't carry it in clear.
   Legacy path config/settings.json inside plugin dir is migrated on read. */
function aimGetDataDir() {
    // GUIDELINE §5 — data in <mediadir>/plugindata; resolve mediadir the FPP way
    $media = $GLOBALS['settings']['mediaDirectory'] ?? getenv('MEDIADIR') ?: null;
    if ($media && $media !== '') return rtrim($media, '/') . '/plugindata/fpp-AImode';
    // Fallback for CLI/lint outside FPP — use plugin dir plugindata subdir for dev
    return AIM_PLUGIN_DIR . '/plugindata';
}
function aimGetSettingsFile() {
    return aimGetDataDir() . '/settings.json';
}
function aimGetHistoryFile() {
    return aimGetDataDir() . '/history.json';
}
function aimGetLegacySettingsFile() {
    return AIM_PLUGIN_DIR . '/config/settings.json';
}
function aimGetLegacyHistoryFile() {
    return AIM_PLUGIN_DIR . '/config/history.json';
}
function aimMigrateLegacyIfNeeded() {
    $new = aimGetSettingsFile();
    $legacy = aimGetLegacySettingsFile();
    if (!file_exists($new) && file_exists($legacy)) {
        $dir = dirname($new);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @copy($legacy, $new);
        @chmod($new, 0600);
    }
    $newH = aimGetHistoryFile();
    $legacyH = aimGetLegacyHistoryFile();
    if (!file_exists($newH) && file_exists($legacyH)) {
        $dir = dirname($newH);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @copy($legacyH, $newH);
        @chmod($newH, 0600);
    }
}

function aimLoadSettings() {
    aimMigrateLegacyIfNeeded();
    $defaults = aimDefaultSettings();
    $file = aimGetSettingsFile();
    if (!file_exists($file)) {
        $legacy = aimGetLegacySettingsFile();
        if (file_exists($legacy)) $file = $legacy;
        else {
            $d = $defaults;
            aimMigrateSingleToProviders($d);
            return $d;
        }
    }
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        $d = $defaults;
        aimMigrateSingleToProviders($d);
        return $d;
    }
    $s = json_decode($raw, true);
    if (!is_array($s)) {
        aimLog('WARNING settings.json corrupt, using defaults');
        $d = $defaults;
        aimMigrateSingleToProviders($d);
        return $d;
    }
    $merged = array_merge($defaults, $s);
    // Ensure providers map exists and is migrated
    aimMigrateSingleToProviders($merged);
    // Clamp
    $merged['temperature'] = max(0, min(2, (float)$merged['temperature']));
    $merged['max_tokens'] = max(64, min(16384, (int)$merged['max_tokens']));
    if (!aimProviderExists($merged['provider'])) {
        $merged['provider'] = 'openai';
    }
    if (!aimProviderExists($merged['defaultProvider'] ?? '')) {
        $merged['defaultProvider'] = $merged['provider'];
    }
    return $merged;
}

function aimSaveSettings($arr) {
    $defaults = aimDefaultSettings();
    // Stored state is used to resolve the "keep existing key" sentinel. Loaded
    // before writing so a blank key field never clobbers a saved credential.
    $stored = aimLoadSettings();
    // Merge with defaults, but handle providers map specially to preserve per-provider configs
    $data = $arr;
    // If incoming has providers map, merge it deeply
    if (isset($arr['providers']) && is_array($arr['providers'])) {
        $data['providers'] = $arr['providers'];
        // Sanitize each provider entry
        foreach ($data['providers'] as $k => &$v) {
            $k2 = preg_replace('/[^a-z_]/', '', strtolower($k));
            if (!aimProviderExists($k2)) continue;
            $v['api_key'] = aimResolveKeepKey($v['api_key'] ?? '', $stored, $k2);
            $v['model'] = trim((string)($v['model'] ?? ''));
            if ($v['model'] === '') $v['model'] = aimGetProviders()[$k2]['defaultModel'] ?? '';
            $v['base_url'] = rtrim(trim((string)($v['base_url'] ?? '')), '/');
            // Display-only hints sent by the Config page are not persisted.
            unset($v['key_set'], $v['key_hint']);
        }
        unset($v);
    }
    $data = array_merge($defaults, $data);
    // Ensure providers map is migrated/sanitized
    aimMigrateSingleToProviders($data);
    // Sanitize top-level provider/defaultProvider and sync legacy fields
    $data['provider'] = preg_replace('/[^a-z_]/', '', strtolower($data['provider'] ?? $data['defaultProvider'] ?? 'openai'));
    $data['defaultProvider'] = preg_replace('/[^a-z_]/', '', strtolower($data['defaultProvider'] ?? $data['provider'] ?? 'openai'));
    if (!aimProviderExists($data['provider'])) $data['provider'] = 'openai';
    if (!aimProviderExists($data['defaultProvider'])) $data['defaultProvider'] = $data['provider'];
    // Sync legacy top-level from default provider's config
    $defProv = $data['defaultProvider'];
    if (isset($data['providers'][$defProv])) {
        // If incoming top-level api_key/model/base_url are explicitly set, treat them as update for default provider
        $hasExplicitTop = isset($arr['api_key']) || isset($arr['model']) || isset($arr['base_url']);
        if ($hasExplicitTop) {
            if (isset($arr['api_key'])) $data['providers'][$defProv]['api_key'] = aimResolveKeepKey($arr['api_key'], $stored, $defProv);
            if (isset($arr['model'])) $data['providers'][$defProv]['model'] = trim((string)$arr['model']);
            if (isset($arr['base_url'])) $data['providers'][$defProv]['base_url'] = rtrim(trim((string)$arr['base_url']), '/');
            if ($data['providers'][$defProv]['model'] === '') $data['providers'][$defProv]['model'] = aimGetProviders()[$defProv]['defaultModel'] ?? '';
        }
        $data['api_key'] = $data['providers'][$defProv]['api_key'] ?? '';
        $data['model'] = $data['providers'][$defProv]['model'] ?? '';
        $data['base_url'] = $data['providers'][$defProv]['base_url'] ?? '';
    }
    $data['system_prompt'] = (string)($data['system_prompt'] ?? '');
    $data['temperature'] = max(0, min(2, (float)($data['temperature'] ?? 0.7)));
    $data['max_tokens'] = max(64, min(16384, (int)($data['max_tokens'] ?? AIM_MAX_TOKENS_DEFAULT)));
    $data['auto_approve'] = !empty($data['auto_approve']) ? 1 : 0;
    $data['dry_run'] = !empty($data['dry_run']) ? 1 : 0;
    $data['include_fpp_context'] = !empty($data['include_fpp_context']) ? 1 : 0;
    $data['history_enabled'] = !empty($data['history_enabled']) ? 1 : 0;

    $file = aimGetSettingsFile();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        aimLog('Save mkdir failed for ' . $dir . ' — trying parent plugindata');
        // Try to create via plugindata parent as fallback
        $parent = dirname($dir);
        if (!is_dir($parent)) @mkdir($parent, 0775, true);
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            aimLog('Save mkdir still failed for ' . $dir . ' parent writable=' . (is_writable($parent) ? 'yes' : 'no'));
            return false;
        }
    }
    // Ensure dir is writable by fpp — install created as root, PHP runs as fpp
    if (!is_writable($dir)) {
        @chmod($dir, 0775);
        if (function_exists('chown')) @chown($dir, 'fpp');
        if (function_exists('chgrp')) @chgrp($dir, 'fpp');
        // Ensure parent plugindata also writable
        $parentPlug = dirname($dir);
        if (!is_writable($parentPlug)) {
            @chmod($parentPlug, 0775);
            if (function_exists('chown')) @chown($parentPlug, 'fpp');
            if (function_exists('chgrp')) @chgrp($parentPlug, 'fpp');
        }
    }
    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0600);
        if (function_exists('chown')) @chown($file, 'fpp');
        if (function_exists('chgrp')) @chgrp($file, 'fpp');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $ok = @file_put_contents($file, $json . "\n", LOCK_EX);
    if ($ok === false) {
        // Try to fix perms and retry once
        @chmod($dir, 0775);
        if (function_exists('chown')) @chown($dir, 'fpp');
        aimLog('Save retry after chmod/chown for ' . $file . ' dir writable=' . (is_writable($dir) ? 'yes' : 'no') . ' file exists=' . (file_exists($file) ? 'yes' : 'no'));
        $ok = @file_put_contents($file, $json . "\n", LOCK_EX);
        if ($ok === false) {
            aimLog('Save file_put_contents still failed for ' . $file);
            return false;
        }
    }
    @chmod($file, 0600);
    if (function_exists('chown')) @chown($file, 'fpp');
    if (function_exists('chgrp')) @chgrp($file, 'fpp');
    // Best-effort: remove legacy file after successful migration to avoid duplicate secrets
    if ($ok !== false) @unlink(aimGetLegacySettingsFile());
    return $ok !== false;
}

function aimEffectiveBaseUrl($settings, $provider = null) {
    if (!$provider) $provider = $settings['defaultProvider'] ?? $settings['provider'] ?? 'openai';
    $providers = aimGetProviders();
    $default = $providers[$provider]['defaultBase'] ?? '';
    $cfg = aimGetProviderConfig($settings, $provider);
    $custom = trim($cfg['base_url'] ?? '');
    return $custom !== '' ? $custom : $default;
}

/* ── History ── */

function aimLoadHistory() {
    aimMigrateLegacyIfNeeded();
    $file = aimGetHistoryFile();
    if (!file_exists($file)) {
        $legacy = aimGetLegacyHistoryFile();
        if (file_exists($legacy)) $file = $legacy;
        else return [];
    }
    $raw = @file_get_contents($file);
    $arr = json_decode($raw ?: '[]', true);
    if (!is_array($arr)) return [];
    // Automatic purge: the privacy block promises 30 days, so drop older
    // entries on read and persist the result (§14.5, §14.8).
    $cutoff = time() - AIM_HISTORY_KEEP_DAYS * 86400;
    $kept = [];
    $changed = false;
    foreach ($arr as $e) {
        $ts = isset($e['ts']) ? strtotime((string)$e['ts']) : false;
        if ($ts !== false && $ts < $cutoff) { $changed = true; continue; }
        $kept[] = $e;
    }
    if ($changed) aimSaveHistory($kept);
    return $kept;
}

function aimSaveHistory($history) {
    $file = aimGetHistoryFile();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    // Keep last N — retention 30 days is enforced on read; here just cap count
    $history = array_slice($history, -AIM_MAX_HISTORY);
    @file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    @chmod($file, 0600);
}

function aimAppendHistory($role, $content, $meta = []) {
    $settings = aimLoadSettings();
    if (empty($settings['history_enabled'])) return;
    $h = aimLoadHistory();
    $entry = ['role' => $role, 'content' => $content, 'ts' => date('Y-m-d H:i:s')];
    if (!empty($meta)) $entry['meta'] = $meta;
    $h[] = $entry;
    aimSaveHistory($h);
}

function aimClearHistory() {
    @unlink(aimGetHistoryFile());
    @unlink(aimGetLegacyHistoryFile());
    // A "clear history" control must really clear what the assistant shows:
    // conversations are the primary store, history.json is only a mirror.
    $dir = aimGetConversationsDir();
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.json') as $f) @unlink($f);
    }
    @file_put_contents(aimGetConversationsIndexFile(), "[]\n", LOCK_EX);
    @chmod(aimGetConversationsIndexFile(), 0600);
}

/* ── Conversations (multiple, background-aware) ── */
function aimGetConversationsDir() {
    return aimGetDataDir() . '/conversations';
}
function aimGetConversationsIndexFile() {
    return aimGetConversationsDir() . '/_index.json';
}
function aimEnsureConversationsMigrated() {
    // Reentrancy guard: aimSaveConversation() -> aimListConversations() ->
    // aimEnsureConversationsMigrated(). Without the guard (and with the index
    // written first below), a legacy history.json migrates recursively until
    // the stack overflows.
    static $migrating = false;
    if ($migrating) return;
    $dir = aimGetConversationsDir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $idxFile = aimGetConversationsIndexFile();
    if (file_exists($idxFile)) return;
    $migrating = true;
    // Migrate legacy single history.json if present and no conversations yet
    $history = aimLoadHistory();
    if (!empty($history)) {
        $id = 'conv_' . substr(md5(uniqid('', true)), 0, 8);
        $title = 'Imported history';
        // Try to derive title from first user message
        foreach ($history as $m) {
            if (($m['role'] ?? '') === 'user' && !empty($m['content'])) {
                $title = (function_exists('mb_substr') ? mb_substr(trim($m['content']), 0, 40) : substr(trim($m['content']), 0, 40));
                if ((function_exists('mb_strlen') ? mb_strlen(trim($m['content'])) : strlen(trim($m['content']))) > 40) $title .= '…';
                break;
            }
        }
        $conv = [
            'id' => $id,
            'title' => $title,
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s'),
            'status' => 'idle',
            'messages' => $history
        ];
        // Write the index FIRST so aimSaveConversation() -> aimListConversations()
        // sees a completed migration instead of restarting it. The subsequent
        // aimSaveConversation() just refreshes this entry.
        $idx = [['id'=>$id,'title'=>$title,'created'=>$conv['created'],'updated'=>$conv['updated'],'status'=>'idle','messageCount'=>count($history)]];
        @file_put_contents($idxFile, json_encode($idx, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
        @chmod($idxFile, 0600);
        aimSaveConversation($conv);
    } else {
        // Create empty index
        @file_put_contents($idxFile, "[]\n", LOCK_EX);
        @chmod($idxFile, 0600);
    }
    $migrating = false;
}
function aimPurgeOldConversations() {
    static $purging = false;
    if ($purging) return;
    $purging = true;
    $dir = aimGetConversationsDir();
    $idxFile = aimGetConversationsIndexFile();
    $cutoff = time() - AIM_HISTORY_KEEP_DAYS * 86400;
    $raw = @file_get_contents($idxFile);
    $list = json_decode($raw ?: '[]', true);
    if (!is_array($list)) $list = [];
    $kept = [];
    $changed = false;
    foreach ($list as $e) {
        $id = $e['id'] ?? '';
        $ts = isset($e['updated']) ? strtotime((string)$e['updated']) : false;
        if ($id && $ts !== false && $ts < $cutoff) {
            @unlink($dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) . '.json');
            $changed = true;
        } else {
            $kept[] = $e;
        }
    }
    if ($changed) {
        @file_put_contents($idxFile, json_encode(array_values($kept), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
        @chmod($idxFile, 0600);
    }
    $purging = false;
}
function aimListConversations() {
    aimEnsureConversationsMigrated();
    aimPurgeOldConversations();
    $idxFile = aimGetConversationsIndexFile();
    $raw = @file_get_contents($idxFile);
    $list = json_decode($raw ?: '[]', true);
    if (!is_array($list)) $list = [];
    // Enrich with actual file existence and sort by updated desc
    $out = [];
    foreach ($list as $e) {
        $id = $e['id'] ?? '';
        if (!$id) continue;
        $file = aimGetConversationsDir() . '/' . $id . '.json';
        if (!file_exists($file)) continue;
        $out[] = $e;
    }
    usort($out, function($a,$b){ return strcmp($b['updated'] ?? '', $a['updated'] ?? ''); });
    return $out;
}
function aimGetConversation($id) {
    aimEnsureConversationsMigrated();
    aimPurgeOldConversations();
    $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    if (!$id) return null;
    $file = aimGetConversationsDir() . '/' . $id . '.json';
    if (!file_exists($file)) return null;
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : null;
}
function aimCreateConversation($title = null, $provider = null, $model = null) {
    aimEnsureConversationsMigrated();
    $id = 'conv_' . substr(md5(uniqid('', true) . microtime()), 0, 12);
    if (!$title || trim($title)==='') $title = 'Chat ' . date('Y-m-d, g:i:s A');
    $title = (function_exists('mb_substr') ? mb_substr(trim($title), 0, 80) : substr(trim($title), 0, 80));
    $now = date('Y-m-d H:i:s');
    $settings = aimLoadSettings();
    $defProv = $settings['defaultProvider'] ?? $settings['provider'] ?? 'openai';
    if (!$provider) $provider = $defProv;
    $cfg = aimGetProviderConfig($settings, $provider);
    if (!$model) $model = $cfg['model'] ?? '';
    $conv = ['id'=>$id,'title'=>$title,'created'=>$now,'updated'=>$now,'status'=>'idle','provider'=>$provider,'model'=>$model,'messages'=>[]];
    aimSaveConversation($conv);
    return $conv;
}
function aimSaveConversation($conv) {
    $dir = aimGetConversationsDir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $id = $conv['id'] ?? '';
    if (!$id) return false;
    $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    $conv['id'] = $id;
    $conv['updated'] = date('Y-m-d H:i:s');
    $file = $dir . '/' . $id . '.json';
    $ok = @file_put_contents($file, json_encode($conv, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
    if ($ok !== false) @chmod($file, 0600);
    // Update index
    $idxFile = aimGetConversationsIndexFile();
    $list = aimListConversations();
    // Remove existing entry for this id
    $found = false;
    foreach ($list as &$e) {
        if (($e['id']??'') === $id) {
            $e['title'] = $conv['title'] ?? $e['title'];
            $e['updated'] = $conv['updated'];
            $e['status'] = $conv['status'] ?? 'idle';
            $e['messageCount'] = count($conv['messages'] ?? []);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $list[] = ['id'=>$id,'title'=>$conv['title'],'created'=>$conv['created'],'updated'=>$conv['updated'],'status'=>$conv['status'] ?? 'idle','messageCount'=>count($conv['messages'] ?? [])];
    }
    // Keep sorted by updated desc
    usort($list, function($a,$b){ return strcmp($b['updated'] ?? '', $a['updated'] ?? ''); });
    @file_put_contents($idxFile, json_encode(array_values($list), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
    @chmod($idxFile, 0600);
    // Also update history file for backward compat (mirror active conversation)
    // Keep history.json as last conversation's messages for legacy callers
    $historyFile = aimGetHistoryFile();
    if (is_dir(dirname($historyFile))) {
        @file_put_contents($historyFile, json_encode($conv['messages'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
        @chmod($historyFile, 0600);
    }
    return $ok !== false;
}
function aimDeleteConversation($id) {
    $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    $file = aimGetConversationsDir() . '/' . $id . '.json';
    @unlink($file);
    $idxFile = aimGetConversationsIndexFile();
    $list = aimListConversations();
    $list = array_values(array_filter($list, function($e) use ($id){ return ($e['id']??'') !== $id; }));
    @file_put_contents($idxFile, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
    @chmod($idxFile, 0600);
    return true;
}
function aimGetOrCreateActiveConversation($preferredId = null) {
    if ($preferredId) {
        $c = aimGetConversation($preferredId);
        if ($c) return $c;
    }
    $list = aimListConversations();
    if (!empty($list)) {
        $mostRecent = $list[0];
        $c = aimGetConversation($mostRecent['id']);
        if ($c) return $c;
    }
    return aimCreateConversation();
}

/* ── FPP context helpers ── */

function aimFppGet($path, $timeout = 2) {
    // GUIDELINE §3.3 — use the proxied Apache path http://localhost/api/*, NEVER the raw fppd port :32322
    $urls = [
        'http://localhost' . $path,
        'http://127.0.0.1' . $path,
    ];
    foreach ($urls as $url) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if ($decoded !== null) return $decoded;
            return $raw;
        }
        // curl fallback
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $tmp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
            if ($tmp !== false && $code >= 200 && $code < 300 && $tmp !== '') {
                $decoded = json_decode($tmp, true);
                if ($decoded !== null) return $decoded;
                return $tmp;
            }
        }
    }
    return null;
}

function aimFppRequest($method, $path, $body = null, $timeout = 5) {
    $url = 'http://localhost' . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $content = $body !== null ? json_encode($body) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($content !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $resp = @curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
        if ($err) return ['success' => false, 'error' => $err, 'code' => 0];
        $decoded = json_decode($resp, true);
        return ['success' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $decoded !== null ? $decoded : $resp];
    }
    $opts = ['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'timeout' => $timeout, 'ignore_errors' => true]];
    if ($content !== null) $opts['http']['content'] = $content;
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $decoded = json_decode($resp ?: '', true);
    // file_get_contents doesn't give status code easily; treat non-false as success if JSON
    if ($resp === false) return ['success' => false, 'error' => 'file_get_contents failed', 'code' => 0];
    return ['success' => true, 'code' => 200, 'body' => $decoded !== null ? $decoded : $resp];
}

function aimGetFppContext($short = false) {
    $status = aimFppGet('/api/fppd/status', 2);
    $settings = aimFppGet('/api/settings', 2);
    $playlists = aimFppGet('/api/playlists', 2);
    $schedules = aimFppGet('/api/schedule', 2);

    // Summarize to keep token usage reasonable
    $ctx = [
        'fpp_status' => $status,
        'settings_summary' => is_array($settings) ? array_intersect_key($settings, array_flip(['HostName','HostDescription','Volume','AudioOutput','TimeZone'])) : $settings,
        'playlists' => is_array($playlists) ? array_map(function($p){ return is_array($p) ? ($p['name'] ?? $p) : $p; }, array_slice($playlists, 0, 20)) : $playlists,
        'schedule_count' => is_array($schedules) ? count($schedules) : null,
    ];
    if ($short) {
        return json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
    }
    // Full-ish: include first few playlists detail and outputs
    $outputs = aimFppGet('/api/channel/output', 2);
    $ctx['outputs_summary'] = $outputs;

    $json = json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
    // Truncate to ~6000 chars to avoid exceeding model context
    if (strlen($json) > 6000) $json = substr($json, 0, 6000) . "\n...[truncated]";
    return $json;
}

/* ── Tool definitions (FPP actions the AI can propose) ── */

function aimGetTools() {
    return [
        [
            'name' => 'get_status',
            'description' => 'Get current FPPD status (playing, playlist, schedule, volume). No arguments.',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
        [
            'name' => 'get_settings',
            'description' => 'Get FPP system settings. Optionally filter by key. Use to inspect before changing.',
            'parameters' => ['type' => 'object', 'properties' => ['key' => ['type' => 'string', 'description' => 'Optional single setting key to fetch']], 'required' => []],
        ],
        [
            'name' => 'update_settings',
            'description' => 'Update one or more FPP settings. Example: {"Volume": 80, "HostDescription": "My Show"}',
            'parameters' => ['type' => 'object', 'properties' => ['settings' => ['type' => 'object', 'description' => 'Key-value map of settings to set']], 'required' => ['settings']],
        ],
        [
            'name' => 'list_playlists',
            'description' => 'List all playlists on this FPP.',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
        [
            'name' => 'get_playlist',
            'description' => 'Get a specific playlist by name.',
            'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']],
        ],
        [
            'name' => 'create_playlist',
            'description' => 'Create or overwrite a playlist. entries is array of playlist items. Each entry MUST have a valid type and required fields: type "sequence" needs sequenceName (e.g. {"type":"sequence","sequenceName":"test.fseq","enabled":1}), type "media" needs mediaName, type "both" needs both sequenceName and mediaName, type "command" needs exact FPP command name in "command" plus args array (e.g. {"type":"command","command":"Effects Stop","args":[],"enabled":1} for all effects vs {"type":"command","command":"Effect Stop","args":["MyEffect"],"enabled":1} for a single effect - these are DIFFERENT commands, use the exact name the user requested), type "effect" needs effectName, type "branch" needs branch details, type "pause" needs duration. Do NOT normalize "Effects Stop" to "Effect Stop" or vice versa - preserve pluralization exactly. Use list_playlists/get_playlist first to see existing files. Examples: effects stop -> {"name":"mine","entries":[{"type":"command","command":"Effects Stop","args":[],"enabled":1}]} , effect stop -> {"name":"mine","entries":[{"type":"command","command":"Effect Stop","args":[],"enabled":1}]}.',
            'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'description' => 'Playlist name'], 'entries' => ['type' => 'array', 'description' => 'Array of playlist entries, each with type and required fields per type - preserve exact command names', 'items' => ['type' => 'object']], 'shuffle' => ['type' => 'boolean', 'description' => 'Random shuffle (does not change repeat)'], 'repeat' => ['type' => 'boolean', 'description' => 'Loop the playlist when it finishes']], 'required' => ['name','entries']],
        ],
        [
            'name' => 'delete_playlist',
            'description' => 'Delete a playlist by name.',
            'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']],
        ],
        [
            'name' => 'list_schedules',
            'description' => 'List scheduler entries.',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
        [
            'name' => 'create_schedule',
            'description' => 'Create a schedule. Provide playlistName, startTime, endTime, days (e.g. MTWThFSaSu or 127), repeat, enabled.',
            'parameters' => ['type' => 'object', 'properties' => ['playlistName' => ['type' => 'string'], 'startTime' => ['type' => 'string', 'description' => 'HH:MM:SS'], 'endTime' => ['type' => 'string', 'description' => 'HH:MM:SS'], 'days' => ['type' => 'string', 'description' => 'e.g. MTWThFSaSu or bitmask'], 'repeat' => ['type' => 'boolean'], 'enabled' => ['type' => 'boolean']], 'required' => ['playlistName','startTime','endTime']],
        ],
        [
            'name' => 'delete_schedule',
            'description' => 'Delete a schedule by index.',
            'parameters' => ['type' => 'object', 'properties' => ['index' => ['type' => 'integer']], 'required' => ['index']],
        ],
        [
            'name' => 'list_outputs',
            'description' => 'List channel outputs (E1.31, GPIO, etc).',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
        [
            'name' => 'get_system_info',
            'description' => 'Get FPP system info (version, Pi model, storage, network).',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
        [
            'name' => 'restart_fppd',
            'description' => 'Set the FPPD restart flag. Use after config changes that require restart.',
            'parameters' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
        ],
    ];
}

function aimExecuteTool($name, $args) {
    if (!is_array($args)) $args = [];
    switch ($name) {
        case 'get_status':
            $data = aimFppGet('/api/fppd/status', 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Could not reach FPPD status'];
        case 'get_settings':
            if (!empty($args['key'])) {
                $key = $args['key'];
                if (aimIsProtectedSettingKey($key)) {
                    return ['success'=>false,'error'=>'That setting is protected and cannot be read by this plugin.'];
                }
                // Volume is runtime state, not a plain setting — fetch via fppd status for real value
                if (strtolower($key) === 'volume') {
                    $status = aimFppGet('/api/fppd/status', 3);
                    if (is_array($status) && isset($status['volume'])) {
                        return ['success'=>true,'result'=>['volume'=>$status['volume'], 'Volume'=>$status['volume']]];
                    }
                    $v = aimFppGet('/api/settings/volume', 3);
                    if ($v !== null && $v !== [] && $v !== '') {
                        return ['success'=>true,'result'=>['volume'=>$v, 'Volume'=>$v]];
                    }
                    $v = aimFppGet('/api/settings/' . urlencode($key), 3);
                    // FPP returns [] for unknown key, treat as not found but still return
                    return ['success'=>true,'result'=>[$key=>$v]];
                }
                $v = aimFppGet('/api/settings/' . urlencode($key), 3);
                return ['success'=>true,'result'=>[$key=>$v]];
            }
            $data = aimFppGet('/api/settings', 3);
            if ($data === null) return ['success'=>false,'error'=>'Could not fetch settings'];
            if (is_array($data)) {
                foreach ($data as $k => $unused) {
                    if (aimIsProtectedSettingKey($k)) unset($data[$k]);
                }
            }
            return ['success'=>true,'result'=>$data];
        case 'update_settings':
            if (empty($args['settings']) || !is_array($args['settings'])) {
                return ['success'=>false,'error'=>'settings must be an object'];
            }
            $settingsIn = $args['settings'];
            foreach (array_keys($settingsIn) as $k) {
                if (aimIsProtectedSettingKey($k)) {
                    return ['success'=>false,'error'=>'Setting "' . $k . '" is protected and cannot be changed by this plugin.'];
                }
            }
            $volumeResult = null;
            // Handle Volume specially via FPP command API for immediate effect (§3.2 command, not raw settings file)
            $volKey = null;
            if (isset($settingsIn['Volume'])) $volKey = 'Volume';
            elseif (isset($settingsIn['volume'])) $volKey = 'volume';
            if ($volKey !== null) {
                $volRaw = $settingsIn[$volKey];
                unset($settingsIn[$volKey]);
                // Also handle "80%" string
                $volInt = intval(trim(str_replace(['%',' '], '', strval($volRaw))));
                $volInt = max(0, min(100, $volInt));
                // Try FPP command API — PRIMARY way to set runtime volume (see forum: curl http://127.0.0.1/api/command/Volume%20Set/100)
                $volRes = aimFppRequest('POST', '/api/command', ['command'=>'Volume Set','args'=>[strval($volInt)]], 4);
                if (!$volRes['success']) {
                    $volRes = aimFppRequest('GET', '/api/command/Volume%20Set/' . $volInt, null, 4);
                }
                if (!$volRes['success']) {
                    $volRes = aimFppRequest('POST', '/api/command/Volume%20Set/' . $volInt, null, 4);
                }
                // Also persist to settings for next boot (some FPP versions store volume as setting)
                $persist = aimFppRequest('PUT', '/api/settings/volume', $volInt, 4);
                if (!$persist['success']) {
                    $persist = aimFppRequest('PUT', '/api/settings/Volume', $volInt, 4);
                }
                $volumeResult = ['volume'=>$volInt, 'command'=>$volRes, 'persist'=>$persist];
                // If no other settings to update, return volume result directly
                if (empty($settingsIn)) {
                    if (!empty($volRes['success'])) {
                        return ['success'=>true,'result'=>['volume'=>$volInt, 'message'=>"Volume set to $volInt% via Volume Set command"]];
                    }
                    return ['success'=>false,'error'=>$volRes['error'] ?? 'Volume Set failed', 'code'=>$volRes['code'] ?? 0, 'volume'=>$volInt];
                }
            }
            // Remaining settings via bulk PUT (for non-volume keys)
            if (!empty($settingsIn)) {
                $res = aimFppRequest('PUT', '/api/settings', $settingsIn, 4);
                if (!$res['success']) {
                    // If volume was also requested, include its result in error
                    $err = $res['error'] ?? 'update failed';
                    if ($volumeResult) $err .= ' (volume: ' . json_encode($volumeResult) . ')';
                    return ['success'=>false,'error'=>$err, 'code'=>$res['code']];
                }
                $combined = $res['body'];
                if ($volumeResult) {
                    $combined['volume'] = $volumeResult['volume'] ?? null;
                    $combined['_volumeCommand'] = $volumeResult;
                }
                return ['success'=>true,'result'=>$combined];
            }
            // Only volume was requested and succeeded
            return ['success'=>true,'result'=>$volumeResult];
        case 'list_playlists':
            $data = aimFppGet('/api/playlists', 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Could not list playlists'];
        case 'get_playlist':
            if (empty($args['name'])) return ['success'=>false,'error'=>'name required'];
            $data = aimFppGet('/api/playlist/' . urlencode($args['name']), 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Playlist not found'];
        case 'create_playlist':
            if (empty($args['name'])) return ['success'=>false,'error'=>'name required'];
            if (!isset($args['entries']) || !is_array($args['entries'])) return ['success'=>false,'error'=>'entries array required']; // empty array allowed for empty playlist
            // Validate entries - catch incomplete command/effect entries that would create empty playlists
            foreach ($args['entries'] as $idx => $e) {
                if (!is_array($e)) return ['success'=>false,'error'=>"entries[$idx] must be an object"];
                if (empty($e['type'])) return ['success'=>false,'error'=>"entries[$idx] missing type - must be one of: sequence, media, both, command, effect, pause, branch, etc. Example for effects stop: {\"type\":\"command\",\"command\":\"Effect Stop\",\"args\":[],\"enabled\":1}"];
                $t = strtolower(trim($e['type']));
                if ($t === 'command') {
                    if (empty($e['command'])) return ['success'=>false,'error'=>"entries[$idx] type command missing required 'command' field - e.g. {\"type\":\"command\",\"command\":\"Effect Stop\",\"args\":[],\"enabled\":1} or \"Stop Effects\". Provide args array even if empty."];
                } elseif ($t === 'effect') {
                    if (empty($e['effectName']) && empty($e['effect']) && empty($e['command'])) return ['success'=>false,'error'=>"entries[$idx] type effect missing effectName/command - e.g. {\"type\":\"effect\",\"effectName\":\"Stop\",\"enabled\":1} or use command type with Effect Stop"];
                } elseif ($t === 'sequence') {
                    if (empty($e['sequenceName']) && empty($e['sequence'])) return ['success'=>false,'error'=>"entries[$idx] type sequence missing sequenceName - e.g. {\"type\":\"sequence\",\"sequenceName\":\"my.fseq\",\"enabled\":1}"];
                } elseif ($t === 'media') {
                    if (empty($e['mediaName']) && empty($e['media'])) return ['success'=>false,'error'=>"entries[$idx] type media missing mediaName - e.g. {\"type\":\"media\",\"mediaName\":\"song.mp3\",\"enabled\":1}"];
                } elseif ($t === 'both') {
                    if ((empty($e['sequenceName']) && empty($e['sequence'])) || (empty($e['mediaName']) && empty($e['media']))) return ['success'=>false,'error'=>"entries[$idx] type both requires both sequenceName and mediaName"];
                }
                // Prevent the exact bug reported: {type:command} with no command name creates empty entry
                if ($t === 'command' && isset($e['command']) && trim($e['command']) === '' ) return ['success'=>false,'error'=>"entries[$idx] command name is empty - use exact FPP command like 'Effects Stop' (all effects) or 'Effect Stop' (single) - they are different, preserve pluralization"];
            }
            // Normalize entries for FPP compatibility - preserve exact command names, do NOT conflate Effects Stop vs Effect Stop
            $normalized = [];
            foreach ($args['entries'] as $e) {
                $ne = $e;
                if (!isset($ne['enabled'])) $ne['enabled'] = 1;
                if (strtolower(trim($ne['type'] ?? '')) === 'command') {
                    // Preserve exact command name as provided (effects vs effect are different commands)
                    if (!isset($ne['args']) || !is_array($ne['args'])) $ne['args'] = [];
                    // Trim whitespace but keep original pluralization/casing for FPP to match exactly
                    if (isset($ne['command'])) $ne['command'] = trim($ne['command']);
                }
                $normalized[] = $ne;
            }
            // FPP expects mainPlaylist (and sometimes entries) - send both for compatibility, plus version/repeat
            $playlistObj = [
                'name' => $args['name'],
                'version' => 3,
                'repeat' => !empty($args['repeat']) ? 1 : 0,
                'mainPlaylist' => $normalized,
                'entries' => $normalized,
                'playlistInfo' => ['total_items' => count($normalized)],
                'leadIn' => [],
                'leadOut' => [],
            ];
            if (isset($args['shuffle'])) $playlistObj['shuffle'] = (bool)$args['shuffle'];
            // Try multiple endpoints/methods for FPP version compatibility
            $res = aimFppRequest('POST', '/api/playlist/' . urlencode($args['name']), $playlistObj, 5);
            if (!$res['success']) $res = aimFppRequest('PUT', '/api/playlist/' . urlencode($args['name']), $playlistObj, 5);
            if (!$res['success']) $res = aimFppRequest('POST', '/api/playlists', $playlistObj, 5);
            // Fallback: try raw file write via media directory if API fails (for testing)
            if (!$res['success']) {
                aimLog('create_playlist API failed for ' . $args['name'] . ': ' . ($res['error'] ?? 'unknown') . ' - validated entries: ' . json_encode($normalized));
            } else {
                aimLog('create_playlist success for ' . $args['name'] . ' entries=' . count($normalized) . ' type=' . ($normalized[0]['type'] ?? 'none'));
            }
            return $res['success'] ? ['success'=>true,'result'=>$res['body']] : ['success'=>false,'error'=>$res['error'] ?? 'create failed. Tried normalized payload with mainPlaylist. Ensure entries have required fields: command needs command+args, sequence needs sequenceName, etc.'];
        case 'delete_playlist':
            if (empty($args['name'])) return ['success'=>false,'error'=>'name required'];
            $res = aimFppRequest('DELETE', '/api/playlist/' . urlencode($args['name']), null, 4);
            return $res['success'] ? ['success'=>true,'result'=>$res['body']] : ['success'=>false,'error'=>$res['error'] ?? 'delete failed'];
        case 'list_schedules':
            $data = aimFppGet('/api/schedule', 3);
            // Fallback path
            if ($data === null) $data = aimFppGet('/api/scheduler', 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Could not list schedules'];
        case 'create_schedule':
            if (empty($args['playlistName'])) return ['success'=>false,'error'=>'playlistName required'];
            $entry = [
                'playlist' => $args['playlistName'],
                'startTime' => $args['startTime'] ?? '18:00:00',
                'endTime' => $args['endTime'] ?? '22:00:00',
                'days' => $args['days'] ?? 'MTWThFSaSu',
                'repeat' => !empty($args['repeat']) ? 1 : 0,
                'enabled' => isset($args['enabled']) ? (int)(bool)$args['enabled'] : 1,
            ];
            $res = aimFppRequest('POST', '/api/schedule', $entry, 5);
            if (!$res['success']) $res = aimFppRequest('POST', '/api/scheduler', $entry, 5);
            return $res['success'] ? ['success'=>true,'result'=>$res['body']] : ['success'=>false,'error'=>$res['error'] ?? 'create schedule failed'];
        case 'delete_schedule':
            if (!isset($args['index'])) return ['success'=>false,'error'=>'index required'];
            $res = aimFppRequest('DELETE', '/api/schedule/' . (int)$args['index'], null, 4);
            if (!$res['success']) $res = aimFppRequest('DELETE', '/api/scheduler/' . (int)$args['index'], null, 4);
            return $res['success'] ? ['success'=>true,'result'=>$res['body']] : ['success'=>false,'error'=>$res['error'] ?? 'delete failed'];
        case 'list_outputs':
            $data = aimFppGet('/api/channel/output', 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Could not fetch outputs'];
        case 'get_system_info':
            $data = aimFppGet('/api/system/info', 3);
            if ($data === null) $data = aimFppGet('/api/fppd/status', 3);
            return $data !== null ? ['success'=>true,'result'=>$data] : ['success'=>false,'error'=>'Could not fetch system info'];
        case 'restart_fppd':
            $res = aimFppRequest('PUT', '/api/settings/restartFlag', 1, 3);
            return $res['success'] ? ['success'=>true,'result'=>'restart flag set'] : ['success'=>false,'error'=>$res['error'] ?? 'restartFlag failed'];
        default:
            return ['success'=>false,'error'=>'Unknown tool: ' . $name];
    }
}

/* ── Provider calls (normalized to reply + tool_calls) ── */

function aimCallProvider($settings, $messages, $tools, $timeout = 30) {
    $provider = $settings['provider'] ?? 'openai';
    $apiKey = $settings['api_key'] ?? '';
    $model = $settings['model'] ?? '';
    $base = aimEffectiveBaseUrl($settings);
    $temperature = (float)($settings['temperature'] ?? 0.7);
    $maxTokens = (int)($settings['max_tokens'] ?? AIM_MAX_TOKENS_DEFAULT);
    $providers = aimGetProviders();
    if (!isset($providers[$provider])) return ['success'=>false,'error'=>'Unknown provider: ' . $provider];
    $meta = $providers[$provider];
    if ($meta['needsKey'] && $apiKey === '' && $provider !== 'ollama' && $provider !== 'opencode-server') {
        return ['success'=>false,'error'=>'API key not configured for ' . $meta['label']];
    }
    if ($model === '') $model = $meta['defaultModel'];
    // Provider/model sanity check — give friendly error before HTTP 404
    if ($provider === 'google' && stripos($model, 'gemini') === false && stripos($model, 'gemma') === false && stripos($model, 'learnlm') === false) {
        return ['success'=>false,'error'=>'Model "' . $model . '" not valid for Google Gemini. Use a gemini-* model — current provider is google. Use the live model picker in Config to see available models.'];
    }
    if ($provider === 'openai' && stripos($model, 'gemini') === 0) {
        return ['success'=>false,'error'=>'Model "' . $model . '" is a Gemini model, not valid for OpenAI provider. Switch provider to Google or choose a gpt-*/o1* model.'];
    }

    // Build provider-specific request. $shape selects the response normalizer
    // (OpenCode Console models use several provider families behind one API key).
    $url = '';
    $headers = [];
    $body = null;
    $shape = $provider;

    if ($provider === 'opencode') {
        $family = aimOpencodeModelFamily($settings, $model);
        $bearer = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $cb = rtrim($base, '/');
        if ($family === 'chat-completions') {
            $url = $cb . '/openai/v1/chat/completions';
            $headers = $bearer;
            $payload = ['model'=>$model,'messages'=>$messages,'temperature'=>$temperature,'max_tokens'=>$maxTokens];
            if (!empty($tools)) {
                $payload['tools'] = array_map(function($t){
                    return ['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']]];
                }, $tools);
                $payload['tool_choice'] = 'auto';
            }
            $body = json_encode($payload);
            $shape = 'openai';
        } elseif ($family === 'messages') {
            $url = $cb . '/anthropic/v1/messages';
            $headers = array_merge($bearer, ['anthropic-version: 2023-06-01']);
            $sys = ''; $converted = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') { $sys .= ($sys ? "\n" : "") . $m['content']; continue; }
                $converted[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
            }
            $payload = ['model'=>$model,'max_tokens'=>$maxTokens,'temperature'=>$temperature,'messages'=>$converted];
            if ($sys !== '') $payload['system'] = $sys;
            if (!empty($tools)) {
                $payload['tools'] = array_map(function($t){
                    return ['name'=>$t['name'],'description'=>$t['description'],'input_schema'=>$t['parameters']];
                }, $tools);
            }
            $body = json_encode($payload);
            $shape = 'anthropic';
        } elseif ($family === 'generate-content' || $family === 'interactions') {
            $url = $cb . '/google/v1beta/models/' . rawurlencode($model) . ':generateContent';
            $headers = $bearer;
            $sysText = ''; $contents = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') { $sysText .= ($sysText ? "\n" : "") . $m['content']; continue; }
                $role = $m['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = ['role'=>$role,'parts'=>[['text'=>$m['content']]]];
            }
            if ($sysText !== '') array_unshift($contents, ['role'=>'user','parts'=>[['text'=>'System instructions: ' . $sysText]]]);
            $payload = ['contents'=>$contents,'generationConfig'=>['temperature'=>$temperature,'maxOutputTokens'=>$maxTokens]];
            if (!empty($tools)) {
                $payload['tools'] = [['functionDeclarations'=> array_map(function($t){
                    return ['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']];
                }, $tools)]];
            }
            $body = json_encode($payload);
            $shape = 'google';
        } elseif ($family === 'responses') {
            $url = $cb . '/openai/v1/responses';
            $headers = $bearer;
            $instructions = ''; $input = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') { $instructions .= ($instructions ? "\n" : "") . $m['content']; continue; }
                $input[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                            'content' => [['type'=>'input_text','text'=>(string)$m['content']]]];
            }
            $payload = ['model'=>$model,'input'=>$input,'max_output_tokens'=>$maxTokens];
            if ($instructions !== '') $payload['instructions'] = $instructions;
            if (!empty($tools)) {
                $payload['tools'] = array_map(function($t){
                    return ['type'=>'function','name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']];
                }, $tools);
                $payload['tool_choice'] = 'auto';
            }
            $body = json_encode($payload);
            $shape = 'responses';
        } else {
            return ['success'=>false,'error'=>'OpenCode model "' . $model . '" uses unsupported API family "' . (string)$family . '". Choose another model in Config.'];
        }
    } elseif ($provider === 'opencode-server') {
        // Self-hosted OpenCode has no native FPP tool-call API. Send one composed
        // prompt and ask for a strict JSON tool-call block, then parse it here.
        $parts = [];
        foreach ($messages as $m) {
            $label = $m['role'] === 'system' ? 'SYSTEM' : ($m['role'] === 'assistant' ? 'ASSISTANT' : 'USER');
            $parts[] = $label . ":\n" . $m['content'];
        }
        $toolDesc = array_map(function($t){ return $t['name'] . ': ' . $t['description']; }, $tools);
        $prompt = implode("\n\n", $parts)
            . "\n\nFPP TOOL CALLING:\nYou may propose FPP tool calls. To do so reply with ONLY a JSON object of this exact shape and no other text:\n"
            . '{"tool_calls":[{"name":"tool_name","arguments":{}}]}' . "\n"
            . 'Available tools: ' . implode('; ', $toolDesc) . "\n"
            . "Use an empty tool_calls array [] if no tool is needed, or answer normally as plain text if no tools are required.";
        $payload = ['prompt'=>$prompt];
        $ref = aimServerModelRef($model);
        if ($ref) $payload['model'] = $ref; // otherwise the server uses its own default
        $r = aimServerRequest($base, '/api/experimental/generate/text', $apiKey, json_encode($payload), $timeout);
        if (!$r['success']) return ['success'=>false,'error'=>'OpenCode server: ' . $r['error']];
        $reply = trim((string)($r['body']['data']['text'] ?? ''));
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $reply, $mm)) $reply = trim($mm[1]);
        $parsed = json_decode($reply, true);
        $toolCalls = [];
        if (is_array($parsed) && isset($parsed['tool_calls']) && is_array($parsed['tool_calls'])) {
            foreach ($parsed['tool_calls'] as $tc) {
                $args = $tc['arguments'] ?? [];
                if (is_string($args)) $args = json_decode($args, true) ?: [];
                if (empty($tc['name'])) continue;
                $toolCalls[] = ['id'=>uniqid('call_'), 'name'=>(string)$tc['name'], 'arguments'=>is_array($args)?$args:[]];
            }
            $reply = isset($parsed['reply']) && is_string($parsed['reply']) ? $parsed['reply'] : '';
        }
        return ['success'=>true,'reply'=>$reply,'tool_calls'=>$toolCalls,'raw'=>$r['body'],'model'=>$model,'provider'=>$provider];
    } elseif ($provider === 'openai' || $provider === 'mistral' || $provider === 'grok' || $provider === 'openrouter') {
        $url = rtrim($base, '/') . '/chat/completions';
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        if ($provider === 'openrouter') {
            $headers[] = 'HTTP-Referer: https://github.com/jessica12ryan/fpp-AImode';
            $headers[] = 'X-Title: FPP AI Mode';
        }
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        if (!empty($tools)) {
            $payload['tools'] = array_map(function($t){
                return ['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']]];
            }, $tools);
            $payload['tool_choice'] = 'auto';
        }
        $body = json_encode($payload);
    } elseif ($provider === 'azure') {
        // Azure: base is like https://xxx.openai.azure.com ; deployment = model ; need api-version
        $url = rtrim($base, '/') . '/openai/deployments/' . rawurlencode($model) . '/chat/completions?api-version=2024-02-15-preview';
        $headers = ['Content-Type: application/json', 'api-key: ' . $apiKey];
        $payload = [
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        if (!empty($tools)) {
            $payload['tools'] = array_map(function($t){
                return ['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']]];
            }, $tools);
            $payload['tool_choice'] = 'auto';
        }
        $body = json_encode($payload);
    } elseif ($provider === 'anthropic') {
        // Anthropic uses different shape; convert messages
        $url = rtrim($base, '/') . '/v1/messages';
        $headers = ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
        $sys = '';
        $converted = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') { $sys .= ($sys ? "\n" : "") . $m['content']; continue; }
            // Anthropic expects content as string or array; keep string
            $converted[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
        }
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $converted,
        ];
        if ($sys !== '') $payload['system'] = $sys;
        if (!empty($tools)) {
            $payload['tools'] = array_map(function($t){
                return ['name'=>$t['name'],'description'=>$t['description'],'input_schema'=>$t['parameters']];
            }, $tools);
        }
        $body = json_encode($payload);
    } elseif ($provider === 'google') {
        // Gemini: key goes in a header, never in the URL (§14.4 — access logs
        // and proxies record query strings).
        $url = rtrim($base, '/') . '/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
        // Convert messages to Gemini format: system + user/model contents
        $sysText = '';
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') { $sysText .= ($sysText ? "\n" : "") . $m['content']; continue; }
            $role = $m['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = ['role'=>$role,'parts'=>[['text'=>$m['content']]]];
        }
        // Inject system as first user instruction if present
        if ($sysText !== '') {
            array_unshift($contents, ['role'=>'user','parts'=>[['text'=>'System instructions: ' . $sysText]]]);
        }
        $payload = ['contents'=>$contents, 'generationConfig'=>['temperature'=>$temperature,'maxOutputTokens'=>$maxTokens]];
        if (!empty($tools)) {
            $payload['tools'] = [['functionDeclarations'=> array_map(function($t){
                return ['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']];
            }, $tools)]];
        }
        $body = json_encode($payload);
    } elseif ($provider === 'ollama') {
        $url = rtrim($base, '/') . '/api/chat';
        $headers = ['Content-Type: application/json'];
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature'=>$temperature, 'num_predict'=>$maxTokens],
        ];
        if (!empty($tools)) {
            $payload['tools'] = array_map(function($t){
                return ['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description'],'parameters'=>$t['parameters']]];
            }, $tools);
        }
        $body = json_encode($payload);
    } else {
        return ['success'=>false,'error'=>'Provider not implemented: ' . $provider];
    }

    // Curl — with Google v1beta->v1 fallback for deprecated model/version combos
    $doCurl = function($tryUrl, $tryHeaders, $tryBody) use ($provider, $timeout) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $tryHeaders);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $tryBody);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $r = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $e = curl_error($ch);
        if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
        return [$r, $c, $e];
    };
    list($resp, $code, $err) = $doCurl($url, $headers, $body);
    if ($err) return ['success'=>false,'error'=>'Curl error: ' . $err];
    // If Google v1beta returns 404 model not found, retry with v1
    if ($provider === 'google' && $code === 404 && strpos($url, '/v1beta/') !== false && (stripos($resp, 'models/') !== false || stripos($resp, 'NOT_FOUND') !== false)) {
        $altUrl = str_replace('/v1beta/', '/v1/', $url);
        list($altResp, $altCode, $altErr) = $doCurl($altUrl, $headers, $body);
        if (!$altErr && $altCode >= 200 && $altCode < 300) {
            $resp = $altResp; $code = $altCode; $err = $altErr;
        } else if (!$altErr) {
            // Keep the more informative error (prefer alt if it has hint)
            $resp = $altResp; $code = $altCode;
        }
    }
    // If Google still 404 with "Please update your code to use models/gemini-X" — auto-retry with suggested model once
    if ($provider === 'google' && $code === 404 && preg_match('/use models\/([a-z0-9._\-]+)/i', $resp ?? '', $sugMatch)) {
        $suggested = $sugMatch[1];
        if ($suggested && $suggested !== $model) {
            aimLog('Google model deprecated, auto-retry with suggested: ' . $suggested);
            $retryUrl = rtrim($base, '/') . '/v1beta/models/' . rawurlencode($suggested) . ':generateContent';
            list($retryResp, $retryCode, $retryErr) = $doCurl($retryUrl, $headers, $body);
            // Also try v1 with suggested if v1beta still 404
            if (!$retryErr && $retryCode === 404 && (stripos($retryResp, 'models/') !== false)) {
                $retryUrlV1 = str_replace('/v1beta/', '/v1/', $retryUrl);
                list($retryResp2, $retryCode2, $retryErr2) = $doCurl($retryUrlV1, $headers, $body);
                if (!$retryErr2 && $retryCode2 >= 200 && $retryCode2 < 300) {
                    $retryResp = $retryResp2; $retryCode = $retryCode2; $retryErr = $retryErr2;
                } else if (!$retryErr2) { $retryResp = $retryResp2; $retryCode = $retryCode2; }
            }
            if (!$retryErr && $retryCode >= 200 && $retryCode < 300) {
                $resp = $retryResp; $code = $retryCode; $err = $retryErr;
                // Persist the working model as new default for next saves (best-effort)
                $current = aimLoadSettings();
                if ($current['provider'] === 'google' && $current['model'] === $model) {
                    $current['model'] = $suggested;
                    @aimSaveSettings($current);
                    aimLog('Auto-migrated Google model to ' . $suggested);
                }
            } else if (!$retryErr) {
                // Keep retry error for more helpful hint (will be surfaced below)
                $resp = $retryResp; $code = $retryCode;
            }
        }
    }
    if ($err) return ['success'=>false,'error'=>'Curl error: ' . $err];
    if ($code < 200 || $code >= 300) {
        $snippet = substr($resp ?: '', 0, 800);
        $hint = '';
        // Friendly hints — Google deprecates older gemini versions quickly; surface the suggested replacement if present
        if ($code === 404 && $provider === 'google' && stripos($snippet, 'models/') !== false) {
            if (preg_match('/models\/([a-z0-9._\-]+)/i', $snippet, $m) && stripos($snippet, 'Please update') !== false) {
                // Extract the API's suggested replacement (e.g. models/gemini-3.6-flash)
                preg_match('/use models\/([a-z0-9._\-]+)/i', $snippet, $sug);
                $suggested = $sug[1] ?? null;
                if ($suggested) {
                    $hint = ' — Model "' . $model . '" no longer available. Google suggests "' . $suggested . '". Update Config → Model to that (or use the live model picker).';
                } else {
                    $hint = ' — Model "' . $model . '" not available (deprecated). Use the live model picker in Config — try gemini-2.5-flash or gemini-2.5-pro.';
                }
            } else {
                $hint = ' — Model "' . $model . '" not available (deprecated or wrong API version). Use the live model picker in Config — try gemini-2.5-flash or gemini-2.5-pro.';
            }
        } elseif ($code === 404 && stripos($snippet, 'model') !== false) {
            $hint = ' — Model "' . $model . '" not found for provider ' . $provider . '. Check Config → Model picker for valid models for this provider.';
        } elseif ($code === 401) {
            $hint = ' — check API key for ' . $provider;
        } elseif ($code === 403 && $provider === 'opencode') {
            $hint = ' — free models are available inside OpenCode only; choose a paid model or use an OpenCode Go subscription';
        } elseif ($code === 429) {
            $hint = ' — Quota exceeded (free tier limit, e.g. 20 req for gemini-3.6-flash). Retry in 20-30s, check your provider\'s rate-limit docs, or switch to Ollama/local or a different provider/model.';
            if (preg_match('/retry in ([0-9.]+)s/i', $snippet, $rm)) $hint .= ' Suggested retry in ' . $rm[1] . 's.';
        }
        return ['success'=>false,'error'=>"HTTP $code: $snippet" . $hint];
    }
    $decoded = json_decode($resp, true);
    if ($decoded === null) return ['success'=>false,'error'=>'Invalid JSON from provider'];

    // Normalize to {reply, tool_calls, raw}
    $reply = '';
    $toolCalls = [];

    if ($shape === 'openai' || $shape === 'mistral' || $shape === 'grok' || $shape === 'openrouter' || $shape === 'azure') {
        $choice = $decoded['choices'][0] ?? null;
        if ($choice) {
            $msg = $choice['message'] ?? [];
            $reply = $msg['content'] ?? '';
            // Some providers put string, others null when tool_calls present
            if ($reply === null) $reply = '';
            foreach (($msg['tool_calls'] ?? []) as $tc) {
                $fn = $tc['function'] ?? [];
                $toolCalls[] = ['id'=>$tc['id'] ?? uniqid('call_'), 'name'=>$fn['name'] ?? '', 'arguments'=> json_decode($fn['arguments'] ?? '{}', true) ?: []];
            }
            // Also support legacy function_call
            if (isset($msg['function_call'])) {
                $fc = $msg['function_call'];
                $toolCalls[] = ['id'=>uniqid('call_'), 'name'=>$fc['name'] ?? '', 'arguments'=> json_decode($fc['arguments'] ?? '{}', true) ?: []];
            }
        }
    } elseif ($shape === 'anthropic') {
        $blocks = $decoded['content'] ?? [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') $reply .= $b['text'];
            if (($b['type'] ?? '') === 'tool_use') {
                $toolCalls[] = ['id'=>$b['id'] ?? uniqid('call_'), 'name'=>$b['name'] ?? '', 'arguments'=> $b['input'] ?? []];
            }
        }
        // Some SDKs return stop_reason tool_use but still include text
        if ($reply === '' && empty($toolCalls) && isset($decoded['content'])) {
            $reply = json_encode($decoded['content']);
        }
    } elseif ($shape === 'google') {
        $cand = $decoded['candidates'][0] ?? null;
        $parts = $cand['content']['parts'] ?? [];
        foreach ($parts as $p) {
            if (isset($p['text'])) $reply .= $p['text'];
            if (isset($p['functionCall'])) {
                $fc = $p['functionCall'];
                $toolCalls[] = ['id'=>uniqid('call_'), 'name'=>$fc['name'] ?? '', 'arguments'=> $fc['args'] ?? []];
            }
        }
    } elseif ($shape === 'responses') {
        // OpenAI Responses API: output_text at top level, or message/output_text
        // items and function_call items inside output[].
        if (isset($decoded['output_text']) && is_string($decoded['output_text'])) $reply = $decoded['output_text'];
        foreach (($decoded['output'] ?? []) as $item) {
            $t = $item['type'] ?? '';
            if ($t === 'message') {
                foreach (($item['content'] ?? []) as $c) {
                    if (($c['type'] ?? '') === 'output_text') $reply .= (string)($c['text'] ?? '');
                    elseif (isset($c['text']) && is_string($c['text'])) $reply .= $c['text'];
                }
            } elseif ($t === 'function_call') {
                $args = $item['arguments'] ?? [];
                if (is_string($args)) $args = json_decode($args, true) ?: [];
                $toolCalls[] = ['id'=>$item['call_id'] ?? uniqid('call_'), 'name'=>$item['name'] ?? '', 'arguments'=>$args];
            }
        }
    } elseif ($shape === 'opencode-server') {
        $text = trim((string)($decoded['data']['text'] ?? ''));
        // Models often wrap the JSON block in a fenced code block.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $mm)) $text = trim($mm[1]);
        $parsed = json_decode($text, true);
        if (is_array($parsed) && isset($parsed['tool_calls']) && is_array($parsed['tool_calls'])) {
            foreach ($parsed['tool_calls'] as $tc) {
                $args = $tc['arguments'] ?? [];
                if (is_string($args)) $args = json_decode($args, true) ?: [];
                if (empty($tc['name'])) continue;
                $toolCalls[] = ['id'=>uniqid('call_'), 'name'=>(string)$tc['name'], 'arguments'=>is_array($args)?$args:[]];
            }
            if (isset($parsed['reply']) && is_string($parsed['reply'])) $reply = $parsed['reply'];
        } else {
            $reply = $text;
        }
    } elseif ($shape === 'ollama') {
        $msg = $decoded['message'] ?? [];
        $reply = $msg['content'] ?? $decoded['response'] ?? '';
        foreach (($msg['tool_calls'] ?? $decoded['tool_calls'] ?? []) as $tc) {
            $fn = $tc['function'] ?? $tc;
            $toolCalls[] = ['id'=>uniqid('call_'), 'name'=>$fn['name'] ?? '', 'arguments'=> is_string($fn['arguments'] ?? null) ? (json_decode($fn['arguments'], true) ?: []) : ($fn['arguments'] ?? [])];
        }
        // Ollama sometimes returns content with JSON tool block — try to parse
        if (empty($toolCalls) && is_string($reply) && preg_match('/\{.*"name"\s*:\s*"(get_|update_|list_|create_|delete_|restart_)/', $reply)) {
            // Attempt to extract JSON tool calls embedded in text (fallback)
            // Keep as text reply; no auto-extract to avoid false positives
        }
    }

    // Ensure reply is string
    if (!is_string($reply)) $reply = json_encode($reply);

    return ['success'=>true,'reply'=>$reply,'tool_calls'=>$toolCalls,'raw'=>$decoded,'model'=>$model,'provider'=>$provider];
}

function aimBuildSystemPrompt($settings) {
    $custom = trim($settings['system_prompt'] ?? '');
    if ($custom !== '') return $custom;
    $toolsSnippet = implode(', ', array_column(aimGetTools(), 'name'));
    $contextNote = !empty($settings['include_fpp_context']) ? " You will receive live FPP context (status, settings, playlists) in the first user message — use it." : "";
    return "You are FPP AI Mode, an assistant that helps configure Falcon Player (FPP) via its local API.$contextNote\n"
        . "You have these tools: $toolsSnippet.\n"
        . "Rules:\n"
        . "- For playlists/schedules you must READ first (list_/get_) to avoid duplicates, but for simple scalar sets with an explicit value (e.g. Set volume to 80%, Set brightness) you may call update_settings directly without a prior get.\n"
        . "- Explain each change briefly in your reply before calling tools.\n"
        . "- Use valid JSON for tool arguments. Times are HH:MM:SS, days like MTWThFSaSu or 127, booleans as true/false.\n"
        . "- Playlist entries MUST have complete required fields per type: sequence needs sequenceName, media needs mediaName, both needs both, command needs exact FPP command name in command+args, effect needs effectName, pause needs duration. Never send {\"type\":\"command\"} without command name. For commands, preserve exact names: \"Effects Stop\" (plural, stops all effects) and \"Effect Stop\" (singular, stops one effect) are DIFFERENT - use exactly what the user requested, do not normalize or conflate. Use list_playlists/get_playlist first.\n"
        . "- Never invent playlist/media names not shown in context; ask the user if unsure.\n"
        . "- Prefer minimal, reversible edits. Offer to restart FPPD only if needed.\n"
        . "- If the user request is ambiguous, ask a clarifying question instead of guessing.\n"
        . "- If a tool returns an error about missing fields, fix the payload on retry (add required fields) rather than repeating the same call.\n"
        . "- Keep replies concise and actionable.";
}

/* ── Endpoints ── */

function getEndpointsfppAImode() {
    $r = [];
    $r[] = ['method'=>'GET', 'endpoint'=>'status', 'callback'=>'aimStatusEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'diagnostics', 'callback'=>'aimDiagnosticsEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'tools', 'callback'=>'aimToolsEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'models', 'callback'=>'aimModelsEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'models', 'callback'=>'aimModelsEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'save', 'callback'=>'aimSaveEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'test', 'callback'=>'aimTestEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'chat', 'callback'=>'aimChatEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'conversations', 'callback'=>'aimConversationsListEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'conversations', 'callback'=>'aimConversationsCreateEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'conversations/:id', 'callback'=>'aimConversationGetEndpoint'];
    $r[] = ['method'=>'DELETE', 'endpoint'=>'conversations/:id', 'callback'=>'aimConversationDeleteEndpoint'];
    $r[] = ['method'=>'PUT', 'endpoint'=>'conversations/:id', 'callback'=>'aimConversationUpdateEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'conversations/:id/delete', 'callback'=>'aimConversationDeleteEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'conversations/:id/update', 'callback'=>'aimConversationUpdateEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'conversations/:id', 'callback'=>'aimConversationUpdateEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'history', 'callback'=>'aimHistoryEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'history/clear', 'callback'=>'aimHistoryClearEndpoint'];
    $r[] = ['method'=>'POST', 'endpoint'=>'execute', 'callback'=>'aimExecuteEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'logs', 'callback'=>'aimLogsEndpoint'];
    $r[] = ['method'=>'GET', 'endpoint'=>'icon', 'callback'=>'aimIconEndpoint'];
    // Update / reinstall / uninstall are deliberately NOT exposed here: FPP's
    // Plugin Manager owns those operations, including the privacy-disclosure
    // re-prompt, version pinning and dependency reference counting (§2.11,
    // §14.16). Only the restart flag is offered as a plugin API route.
    $r[] = ['method'=>'POST', 'endpoint'=>'restart-fppd', 'callback'=>'aimRestartFPPDEndpoint'];
    return $r;
}
if (!function_exists('getEndpointsfppaImode')) {
    eval('function getEndpointsfppaImode(){ return getEndpointsfppAImode(); }');
}
if (!function_exists('getEndpointsfppaimode')) {
    eval('function getEndpointsfppaimode(){ return getEndpointsfppAImode(); }');
}

function aimIconEndpoint() {
    $iconFile = AIM_PLUGIN_DIR . '/icon.png';
    if (!file_exists($iconFile)) {
        header('HTTP/1.0 404 Not Found');
        return json(['error'=>'Icon not found']);
    }
    $mtime = filemtime($iconFile);
    $etag = '"' . md5_file($iconFile) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($iconFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { header('HTTP/1.1 304 Not Modified'); exit; }
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ims = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($ims !== false && $ims >= $mtime) { header('HTTP/1.1 304 Not Modified'); exit; }
    }
    readfile($iconFile);
    exit;
}

function aimStatusEndpoint() {
    $settings = aimLoadSettings();
    // Redact keys in response
    $safe = $settings;
    $safe['api_key'] = $safe['api_key'] ? '***' . substr($safe['api_key'], -4) : '';
    if (isset($safe['providers']) && is_array($safe['providers'])) {
        foreach ($safe['providers'] as $k=>&$v) {
            if (isset($v['api_key']) && $v['api_key'] !== '') $v['api_key'] = '***' . substr($v['api_key'], -4);
        }
    }
    $providers = aimGetProviders();
    $defProv = $settings['defaultProvider'] ?? $settings['provider'] ?? 'openai';
    $base = aimEffectiveBaseUrl($settings, $defProv);
    $fppStatus = aimFppGet('/api/fppd/status', 2);
    $history = aimLoadHistory();
    return json([
        'success'=>true,
        'settings'=>$safe,
        'raw_settings_keys'=> array_keys($settings),
        'provider_meta'=> $providers[$defProv] ?? null,
        'effective_base_url'=> $base,
        'defaultProvider'=>$defProv,
        'providers'=>$safe['providers'] ?? [],
        'fpp_status'=> $fppStatus,
        'fpp_reachable'=> $fppStatus !== null,
        'history_count'=> count($history),
        'tools'=> array_column(aimGetTools(), 'name'),
    ]);
}

function aimDiagnosticsEndpoint() {
    $settings = aimLoadSettings();
    $provider = $settings['provider'] ?? 'openai';
    $providers = aimGetProviders();
    $base = aimEffectiveBaseUrl($settings);
    $checks = [];

    // 1. Settings presence
    $checks[] = ['check'=>'Provider selected','ok'=> aimProviderExists($provider), 'detail'=> $provider];
    $needsKey = $providers[$provider]['needsKey'] ?? true;
    $checks[] = ['check'=>'API key configured','ok'=> !$needsKey || !empty($settings['api_key']), 'detail'=> $needsKey ? (!empty($settings['api_key']) ? 'present' : 'missing') : ($provider === 'opencode-server' ? 'optional (Basic auth password/token)' : 'not required (Ollama)')];
    $checks[] = ['check'=>'Model selected','ok'=> !empty($settings['model']) || $provider === 'opencode-server', 'detail'=> $provider === 'opencode-server' && empty($settings['model']) ? 'server default' : ($settings['model'] ?? '')];
    $checks[] = ['check'=>'Base URL','ok'=> $provider !== 'ollama' ? !empty($base) : true, 'detail'=> $base ?: ($provider==='ollama' ? 'not set — Ollama requires local install + host' : 'empty (using default)')];
    $checks[] = ['check'=>'PHP curl','ok'=> function_exists('curl_init'), 'detail'=> function_exists('curl_init') ? 'available' : 'missing'];
    $checks[] = ['check'=>'FPP reachable','ok'=> aimFppGet('/api/fppd/status', 2) !== null, 'detail'=> aimFppGet('/api/fppd/status', 2) ? 'ok' : 'unreachable'];

    // 2. Ollama local check if selected — only if host configured
    if ($provider === 'ollama') {
        if (empty($base)) {
            $checks[] = ['check'=>'Ollama reachable','ok'=>false,'detail'=> 'Host not set — Ollama not installed. Set Base URL to http://<ollama-host>:11434 to enable.'];
        } else {
            $ollamaUp = false; $detail = 'not reachable';
            $ch = curl_init(rtrim($base,'/') . '/api/tags');
            if ($ch) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                $resp = @curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
                if ($code === 200 && $resp) { $ollamaUp = true; $detail = 'reachable'; }
                else $detail = 'HTTP ' . $code;
            }
            $checks[] = ['check'=>'Ollama reachable','ok'=>$ollamaUp,'detail'=> rtrim($base,'/') . '/api/tags: ' . $detail . ($ollamaUp ? '' : ' — is Ollama running on that host?')];
        }
    }
    if ($provider === 'opencode-server') {
        $apiKey = aimGetProviderConfig($settings, $provider)['api_key'] ?? '';
        $probe = aimServerRequest($base, '/api/info', $apiKey, null, 8);
        $checks[] = ['check'=>'OpenCode server reachable', 'ok'=>$probe['success'],
                     'detail'=> rtrim($base,'/') . '/api/info: ' . ($probe['success'] ? 'ok' : $probe['error'])];
    }

    $allOk = true;
    foreach ($checks as $c) if (!$c['ok']) $allOk = false;
    return json(['success'=>$allOk, 'results'=>$checks, 'settings'=>['provider'=>$provider,'model'=>$settings['model'],'base_url'=>$base]]);
}

function aimToolsEndpoint() {
    return json(['success'=>true,'tools'=> aimGetTools()]);
}

function aimFetchOpencodeModels($base, $apiKey, $timeout = 12) {
    $url = rtrim((string)$base, '/') . '/v1/models';
    $headers = ['Accept: application/json'];
    if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
    if ($err) return ['success'=>false,'error'=>'Curl error: ' . $err];
    if ($code < 200 || $code >= 300) return ['success'=>false,'error'=>'HTTP ' . $code . ': ' . substr((string)$resp, 0, 400)];
    $decoded = json_decode($resp, true);
    if (!is_array($decoded)) return ['success'=>false,'error'=>'Invalid JSON from OpenCode'];
    $supported = ['chat-completions','messages','responses','generate-content','interactions'];
    $models = []; $meta = [];
    foreach (($decoded['data'] ?? []) as $m) {
        if (!is_array($m) || ($m['type'] ?? 'chat') !== 'chat') continue;
        $id = (string)($m['id'] ?? '');
        $api = (string)($m['recommended_api'] ?? '');
        if ($id === '' || !in_array($api, $supported, true)) continue;
        $models[] = $id;
        $meta[$id] = ['api'=>$api,'name'=>(string)($m['name'] ?? $id)];
    }
    if (empty($models)) return ['success'=>false,'error'=>'No supported chat models returned by OpenCode'];
    sort($models);
    aimModelCacheSave('opencode', ['models'=>$meta]);
    return ['success'=>true,'models'=>$models,'meta'=>$meta];
}

/* OpenCode's HTTP API is protected by HTTP Basic auth (username "opencode" for
   the managed service, password from OPENCODE_PASSWORD / service.json). Accept
   several forms in the Config key field, and fall back to Bearer for servers
   configured with a token instead. */
function aimServerAuthHeadersList($apiKey) {
    $key = trim((string)$apiKey);
    if ($key === '') return [[]]; // no auth header — a foreground serve may be open
    if (stripos($key, 'basic ') === 0) return [['Authorization: ' . $key]];
    if (stripos($key, 'bearer ') === 0) return [['Authorization: ' . $key]];
    if (strpos($key, ':') !== false) return [['Authorization: Basic ' . base64_encode($key)]];
    return [
        ['Authorization: Basic ' . base64_encode('opencode:' . $key)],
        ['Authorization: Bearer ' . $key],
    ];
}
function aimServerCurl($url, $headers, $body = null, $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
    return [$resp, $code, $err];
}
function aimServerRequest($base, $path, $apiKey, $body = null, $timeout = 10) {
    $url = rtrim((string)$base, '/') . $path;
    $headers = ['Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    $last = null;
    foreach (aimServerAuthHeadersList($apiKey) as $auth) {
        list($resp, $code, $err) = aimServerCurl($url, array_merge($headers, $auth), $body, $timeout);
        if ($err === '' && $code >= 200 && $code < 300) {
            return ['success'=>true,'code'=>$code,'body'=>json_decode($resp, true),'text'=>(string)$resp];
        }
        $last = ['success'=>false,'code'=>$code,
                 'error'=>$err !== '' ? ('Curl error: ' . $err) : ('HTTP ' . $code . ': ' . substr((string)$resp, 0, 300))];
        if ($code !== 401 && $code !== 403) break;
    }
    return $last;
}

function aimFetchOpencodeServerModels($base, $apiKey, $timeout = 8) {
    $decoded = null;
    // A freshly started server can return an empty model snapshot before its
    // providers settle; try twice before giving up.
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $r = aimServerRequest($base, '/api/model', $apiKey, null, $timeout);
        if (!$r['success']) return $r;
        $decoded = $r['body'];
        if (is_array($decoded) && !empty($decoded['data'])) break;
        if ($attempt === 0) usleep(300000);
    }
    if (!is_array($decoded)) return ['success'=>false,'error'=>'Invalid JSON from OpenCode server'];
    $rows = $decoded['data'] ?? $decoded;
    $models = []; $meta = [];
    foreach ($rows as $k => $m) {
        if (!is_array($m)) continue;
        // Model.Info.id is the bare model ID; providerID is separate.
        $id = (string)($m['modelID'] ?? $m['id'] ?? (is_string($k) ? $k : ''));
        $pid = (string)($m['providerID'] ?? '');
        if ($id !== '' && $pid !== '' && strpos($id, $pid . '/') === 0) {
            $id = substr($id, strlen($pid) + 1);
        }
        if ($id === '') continue;
        $label = $pid !== '' ? $pid . '/' . $id : $id;
        $models[] = $label;
        $meta[$label] = ['id'=>$id,'providerID'=>$pid];
    }
    if (empty($models)) return ['success'=>false,'error'=>'OpenCode server has no models yet — configure a provider in OpenCode, then press Refresh'];
    $models = array_values(array_unique($models));
    sort($models);
    aimModelCacheSave('opencode-server', ['models'=>$meta]);
    return ['success'=>true,'models'=>$models,'meta'=>$meta];
}

function aimFetchProviderModels($provider, $apiKey, $baseUrl, $timeout = 12) {
    $providers = aimGetProviders();
    if (!isset($providers[$provider])) return ['success'=>false,'error'=>'Unknown provider: ' . $provider];
    $meta = $providers[$provider];
    if ($meta['needsKey'] && !$apiKey && $provider !== 'ollama') {
        return ['success'=>false,'error'=>'API key required to list models for ' . $meta['label']];
    }
    $base = $baseUrl ? rtrim($baseUrl, '/') : rtrim($meta['defaultBase'], '/');
    if ($provider === 'opencode') return aimFetchOpencodeModels($base, $apiKey, $timeout);
    if ($provider === 'opencode-server') return aimFetchOpencodeServerModels($base, $apiKey, $timeout);
    $url = '';
    $headers = [];
    if ($provider === 'openai' || $provider === 'mistral' || $provider === 'grok' || $provider === 'openrouter') {
        $url = rtrim($base, '/') . '/models';
        $headers = ['Authorization: Bearer ' . $apiKey];
        if ($provider === 'openrouter') {
            $headers[] = 'HTTP-Referer: https://github.com/jessica12ryan/fpp-AImode';
            $headers[] = 'X-Title: FPP AI Mode';
        }
    } elseif ($provider === 'azure') {
        // Azure: list deployments
        $url = rtrim($base, '/') . '/openai/deployments?api-version=2024-02-15-preview';
        $headers = ['api-key: ' . $apiKey];
    } elseif ($provider === 'anthropic') {
        $url = rtrim($base, '/') . '/v1/models';
        $headers = ['x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
    } elseif ($provider === 'google') {
        // Try v1beta first, then v1 — key in a header, not the query string
        $url = rtrim($base, '/') . '/v1beta/models';
        $headers = ['x-goog-api-key: ' . $apiKey];
    } elseif ($provider === 'ollama') {
        $url = rtrim($base, '/') . '/api/tags';
        $headers = [];
    } else {
        return ['success'=>false,'error'=>'List not implemented for provider: ' . $provider];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
    if ($err) return ['success'=>false,'error'=>'Curl error: ' . $err];
    if ($code === 404 && $provider === 'google') {
        // Try v1 fallback for Google
        $altUrl = rtrim($base, '/') . '/v1/models';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $altUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $resp2 = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err2 = curl_error($ch);
        if (function_exists("curl_close") && version_compare(PHP_VERSION, "8.0", "<")) @curl_close($ch);
        if (!$err2 && $code2 >= 200 && $code2 < 300) { $resp = $resp2; $code = $code2; }
    }
    if ($code < 200 || $code >= 300) {
        $snippet = substr($resp ?: '', 0, 600);
        return ['success'=>false,'error'=>"HTTP $code: $snippet"];
    }
    $decoded = json_decode($resp, true);
    if ($decoded === null) return ['success'=>false,'error'=>'Invalid JSON from provider'];
    $models = [];
    if ($provider === 'openai' || $provider === 'mistral' || $provider === 'grok' || $provider === 'openrouter') {
        foreach (($decoded['data'] ?? []) as $m) {
            $id = $m['id'] ?? $m['name'] ?? null;
            if ($id) $models[] = $id;
        }
        if (empty($models) && isset($decoded['models'])) foreach ($decoded['models'] as $m) if (isset($m['id'])) $models[] = $m['id'];
    } elseif ($provider === 'azure') {
        foreach (($decoded['data'] ?? []) as $m) {
            $id = $m['id'] ?? null;
            if ($id) $models[] = $id;
        }
    } elseif ($provider === 'anthropic') {
        foreach (($decoded['data'] ?? []) as $m) {
            $id = $m['id'] ?? null;
            if ($id) $models[] = $id;
        }
        // Anthropic also returns {data:[{id:...}]}
        if (empty($models) && isset($decoded['models'])) foreach ($decoded['models'] as $m) $models[] = $m['id'] ?? $m;
    } elseif ($provider === 'google') {
        foreach (($decoded['models'] ?? []) as $m) {
            $name = $m['name'] ?? '';
            // Google returns "models/gemini-3.6-flash" — strip prefix
            if (strpos($name, 'models/') === 0) $name = substr($name, 7);
            if ($name) $models[] = $name;
        }
    } elseif ($provider === 'ollama') {
        foreach (($decoded['models'] ?? []) as $m) {
            $name = $m['name'] ?? $m['model'] ?? null;
            if ($name) $models[] = $name;
        }
    }
    $models = array_values(array_unique(array_filter($models)));
    sort($models);
    if (empty($models)) return ['success'=>false,'error'=>'No models returned from provider'];
    return ['success'=>true,'models'=>$models];
}
function aimModelsEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) $body = [];
    $body = array_merge($_POST, $body);
    $settings = aimLoadSettings();
    $provider = trim((string)($body['provider'] ?? $settings['defaultProvider'] ?? $settings['provider'] ?? ''));
    // Keys and destinations of cloud providers are never taken from a request.
    // The self-hosted server is the exception: this request already passed the
    // same-origin check, and the operator may be trying a URL/token before saving.
    $cfg = aimGetProviderConfig($settings, $provider);
    $apiKey = $cfg['api_key'];
    $baseUrl = $cfg['base_url'];
    if ($provider === 'opencode-server') {
        if (isset($body['base_url']) && trim((string)$body['base_url']) !== '') {
            $baseUrl = rtrim(trim((string)$body['base_url']), '/');
        }
        if (isset($body['api_key']) && trim((string)$body['api_key']) !== ''
            && trim((string)$body['api_key']) !== AIM_KEEP_KEY_SENTINEL) {
            $apiKey = trim((string)$body['api_key']);
        }
    }
    if (!$provider) return json(['success'=>false,'error'=>'Provider required']);
    $res = aimFetchProviderModels($provider, $apiKey, $baseUrl);
    if (!$res['success']) {
        aimLog('Models fetch failed provider=' . $provider . ' error=' . $res['error']);
        return json(['success'=>false,'error'=>$res['error']]);
    }
    aimLog('Models fetched provider=' . $provider . ' count=' . count($res['models']));
    return json(['success'=>true,'models'=>$res['models'],'provider'=>$provider]);
}

function aimSaveEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $body = $_POST;
    // Support JSON body (fetch with contentType json may land in php://input; FPP's json() helper populates $_POST via json decode in some versions, but be safe)
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $j = json_decode($raw, true);
        if (is_array($j)) $body = array_merge($body, $j);
    }
    // Allow partial updates: merge with existing
    $existing = aimLoadSettings();
    $merged = array_merge($existing, $body);
    // Don't treat stale top-level api_key/model/base_url from existing as explicit unless body actually sent them
    // This prevents switching defaultProvider from clobbering the new default's stored key with the old default's key
    if (!isset($body['api_key'])) unset($merged['api_key']);
    if (!isset($body['model'])) unset($merged['model']);
    if (!isset($body['base_url'])) unset($merged['base_url']);
    // Coerce types from strings
    if (isset($body['temperature'])) $merged['temperature'] = (float)$body['temperature'];
    if (isset($body['max_tokens'])) $merged['max_tokens'] = (int)$body['max_tokens'];
    foreach (['auto_approve','dry_run','include_fpp_context','history_enabled'] as $k) {
        if (isset($body[$k])) $merged[$k] = !empty($body[$k]) ? 1 : 0;
    }
    if (!aimSaveSettings($merged)) {
        $target = aimGetSettingsFile();
        aimLog('Save failed to ' . $target);
        return json(['success'=>false,'error'=>'Could not write ' . $target . ' — check permissions on plugindata/fpp-AImode/ (should be 775 dir, 600 file, owned by fpp)']);
    }
    aimLog('Settings saved provider=' . ($merged['provider'] ?? '') . ' model=' . ($merged['model'] ?? ''));
    // Never echo stored keys (or the keep-key sentinel) back to the browser.
    $safe = $merged;
    $topKey = (string)($safe['api_key'] ?? '');
    $safe['api_key'] = ($topKey !== '' && $topKey !== AIM_KEEP_KEY_SENTINEL) ? '***' . substr($topKey, -4) : '';
    if (isset($safe['providers']) && is_array($safe['providers'])) {
        foreach ($safe['providers'] as $pk => &$pv) {
            if (!is_array($pv)) continue;
            $pkStr = (string)($pv['api_key'] ?? '');
            $pv['api_key'] = ($pkStr !== '' && $pkStr !== AIM_KEEP_KEY_SENTINEL) ? '***' . substr($pkStr, -4) : '';
        }
        unset($pv);
    }
    return json(['success'=>true,'message'=>'Settings saved','settings'=>$safe]);
}

function aimTestEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $body = $_POST;
    $raw = file_get_contents('php://input');
    if (!empty($raw)) { $j = json_decode($raw, true); if (is_array($j)) $body = array_merge($body, $j); }
    $settings = aimLoadSettings();
    // The provider may be chosen by the request, but its key and endpoint come
    // only from saved settings. A model typed in the Config page may be tested
    // before saving; it is just a string in a URL path, not a secret or host.
    $testProvider = trim((string)($body['provider'] ?? $settings['defaultProvider'] ?? $settings['provider'] ?? 'openai'));
    $effective = aimGetProviderConfig($settings, $testProvider);
    // For the self-hosted server, the operator may be testing a URL/token typed
    // but not yet saved. This request has already passed the same-origin check.
    if ($testProvider === 'opencode-server') {
        if (isset($body['base_url']) && trim((string)$body['base_url']) !== '') {
            $effective['base_url'] = rtrim(trim((string)$body['base_url']), '/');
        }
        if (isset($body['api_key']) && trim((string)$body['api_key']) !== ''
            && trim((string)$body['api_key']) !== AIM_KEEP_KEY_SENTINEL) {
            $effective['api_key'] = trim((string)$body['api_key']);
        }
    }
    $settings['provider'] = $effective['provider'];
    $settings['defaultProvider'] = $effective['provider'];
    $settings['api_key'] = $effective['api_key'];
    $settings['model'] = trim((string)($body['model'] ?? '')) !== '' ? trim((string)$body['model']) : $effective['model'];
    $settings['base_url'] = $effective['base_url'];
    // The stable /api/info route is a better connectivity probe than the
    // experimental generation route, and it does not spend any tokens.
    if ($settings['provider'] === 'opencode-server') {
        $probe = aimServerRequest($settings['base_url'] ?: aimEffectiveBaseUrl($settings, 'opencode-server'), '/api/info', $settings['api_key'], null, 8);
        if (!$probe['success']) {
            aimLog('Test failed: OpenCode server unreachable: ' . $probe['error']);
            return json(['success'=>false,'error'=>'OpenCode server unreachable: ' . $probe['error']]);
        }
        if ($settings['model'] === '') {
            aimLog('Test SUCCESS opencode-server (info only)');
            return json(['success'=>true,'message'=>'Connected','reply'=>'OpenCode server reachable — no model selected, the server default will be used','provider'=>'opencode-server','model'=>'(server default)']);
        }
    }
    // Build minimal test message
    $messages = [['role'=>'user','content'=>'Reply with exactly: OK']];
    $tools = []; // no tools for test
    aimLog('Test connection provider=' . ($settings['provider'] ?? '') . ' model=' . ($settings['model'] ?? ''));
    $res = aimCallProvider($settings, $messages, $tools, 15);
    if (!$res['success']) {
        aimLog('Test failed: ' . $res['error']);
        // Friendly hints
        $err = $res['error'];
        if (stripos($err, '401') !== false) $err .= ' — check API key';
        if (stripos($err, '404') !== false && ($settings['provider'] ?? '') === 'ollama') $err .= ' — is Ollama running? try http://<ollama-ip>:11434';
        if (stripos($err, 'Could not resolve') !== false) $err .= ' — check base URL and FPP internet';
        return json(['success'=>false,'error'=>$err]);
    }
    aimLog('Test SUCCESS provider=' . $settings['provider'] . ' reply=' . substr($res['reply'] ?? '',0,80));
    return json(['success'=>true,'message'=>'Connected','reply'=>$res['reply'],'provider'=>$res['provider'],'model'=>$res['model']]);
}

function aimChatEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    ignore_user_abort(true);
    set_time_limit(120);
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) $body = [];
    // Also merge $_POST and URL param
    $body = array_merge($_POST, $body);
    // Support conversationId from body, query, or FPP param()
    $conversationId = trim((string)($body['conversationId'] ?? $body['conversation_id'] ?? $body['conversation'] ?? ''));
    if (!$conversationId) {
        $paramId = null;
        if (function_exists('param')) { $paramId = @param('conversationId', null); if (!$paramId) $paramId = @param('id', null); }
        if ($paramId) $conversationId = trim((string)$paramId);
    }
    if (!$conversationId && isset($_GET['conversationId'])) $conversationId = trim((string)$_GET['conversationId']);

    $prompt = trim((string)($body['prompt'] ?? $body['message'] ?? ''));
    if ($prompt === '') return json(['success'=>false,'error'=>'Prompt is required']);

    $settings = aimLoadSettings();

    // Resolve conversation first — need conv provider before picking effective provider (background-aware, survives refresh)
    $conv = null;
    if ($conversationId) $conv = aimGetConversation($conversationId);
    if (!$conv) $conv = aimGetOrCreateActiveConversation($conversationId);
    $conversationId = $conv['id'];

    // Resolve provider — per-conversation override takes precedence, then defaultProvider, then per-request override
    $convProvider = $conv['provider'] ?? null;
    $convModel = $conv['model'] ?? null;
    $defProvider = $convProvider ?: ($settings['defaultProvider'] ?? $settings['provider'] ?? 'openai');
    $effective = aimGetProviderConfig($settings, $defProvider);
    // If conversation has stored model, use it (unless per-request overrides)
    if ($convModel && $convModel !== '') $effective['model'] = $convModel;
    // Allow per-request override of provider/model (for testing or per-conversation switch)
    $reqProvider = trim((string)($body['provider'] ?? ''));
    if ($reqProvider && aimProviderExists($reqProvider)) {
        $defProvider = $reqProvider;
        $effective = aimGetProviderConfig($settings, $defProvider);
        // A model typed in the UI may be used for this chat; keys and endpoints
        // are always read from saved settings (never from a request).
        if (isset($body['model']) && $body['model'] !== '') $effective['model'] = trim((string)$body['model']);
        // Persist per-conversation provider/model if changed via request
        $conv['provider'] = $effective['provider'];
        $conv['model'] = $effective['model'];
        aimSaveConversation($conv);
    }
    // No else-if needed: $defProvider was initialised from $convProvider, so
    // $effective already reflects the conversation's provider in that case.
    // Merge effective into settings copy for provider calls
    $effSettings = $settings;
    $effSettings['provider'] = $effective['provider'];
    $effSettings['api_key'] = $effective['api_key'];
    $effSettings['model'] = $effective['model'];
    $effSettings['base_url'] = $effective['base_url'];
    $effSettings['defaultProvider'] = $defProvider;
    // Validate effective
    if ($effSettings['provider'] !== 'ollama' && empty($effSettings['api_key'])) {
        return json(['success'=>false,'error'=>'API key not configured for default provider ' . $defProvider . '. Go to Config and add it.']);
    }
    // A self-hosted OpenCode server has its own default model, so an empty
    // selection is valid there; all cloud providers need an explicit model.
    if (empty($effSettings['model']) && $effSettings['provider'] !== 'opencode-server') {
        return json(['success'=>false,'error'=>'Model not configured for default provider ' . $defProvider]);
    }

    // Keep timestamp title - do not rename from prompt (user requested Chat YYYY-MM-DD, h:mm:ss AM format)
    // Title is already set at creation to Chat Y-m-d, g:i:s A; leave as is even on first message
    // Append user prompt to conversation and mark thinking
    $conv['messages'][] = ['role'=>'user','content'=>$prompt,'ts'=>date('Y-m-d H:i:s')];
    $conv['status'] = 'thinking';
    $conv['updated'] = date('Y-m-d H:i:s');
    aimSaveConversation($conv);

    // Build LLM messages with system + FPP context + conversation history (last 12)
    $system = aimBuildSystemPrompt($settings);
    $messages = [['role'=>'system','content'=>$system]];
    if (!empty($settings['include_fpp_context'])) {
        $ctx = aimGetFppContext(false);
        $messages[] = ['role'=>'user','content'=>"FPP LIVE CONTEXT (do not repeat verbatim, use for tool calls):\n" . $ctx];
        $messages[] = ['role'=>'assistant','content'=>'Understood. I will use get_/list_ tools to verify before making changes and explain each action.'];
    }
    // Use all stored messages except the just-added prompt (added again below)
    // as conversation history for the model.
    $historyForLLM = array_slice($conv['messages'], 0, -1);
    $historyForLLM = array_slice($historyForLLM, -12);
    foreach ($historyForLLM as $h) {
        if (!isset($h['role']) || !isset($h['content'])) continue;
        if ($h['role'] === 'system') continue;
        if (in_array($h['role'], ['user','assistant'])) {
            $messages[] = ['role'=>$h['role'],'content'=>$h['content']];
        }
    }
    $messages[] = ['role'=>'user','content'=>$prompt];

    $tools = aimGetTools();

    aimLog('Chat prompt conv=' . $conversationId . ' provider=' . $effSettings['provider'] . ' model=' . $effSettings['model'] . ' len=' . strlen($prompt) . ' default=' . $defProvider);
    // Also mirror to legacy history for backward compat
    aimAppendHistory('user', $prompt);

    $isDryRun = !empty($settings['dry_run']);
    $autoApprove = !empty($settings['auto_approve']);
    $allToolCalls = [];
    $allExecuted = [];
    $finalReply = '';
    $finalProvider = $settings['provider'];
    $finalModel = $settings['model'];
    $currentMessages = $messages;
    $maxIterations = 4;
    $iteration = 0;

    while ($iteration < $maxIterations) {
        $res = aimCallProvider($effSettings, $currentMessages, $tools, 40);
        if (!$res['success']) {
            aimLog('Chat failed iter ' . $iteration . ': ' . $res['error']);
            if ($iteration === 0) {
                // Mark conversation idle and persist error for polling clients
                $conv['status'] = 'idle';
                $conv['updated'] = date('Y-m-d H:i:s');
                $conv['messages'][] = ['role'=>'assistant','content'=>'Error: ' . $res['error'],'ts'=>date('Y-m-d H:i:s'),'meta'=>['error'=>$res['error']]];
                aimSaveConversation($conv);
                return json(['success'=>false,'error'=>$res['error'],'conversationId'=>$conversationId,'conversation'=>$conv]);
            }
            // On later iteration, return what we have with error appended
            $finalReply .= "\n\n[Error on follow-up: " . $res['error'] . "]";
            break;
        }
        $reply = $res['reply'] ?? '';
        $toolCalls = $res['tool_calls'] ?? [];
        $finalProvider = $res['provider'] ?? $finalProvider;
        $finalModel = $res['model'] ?? $finalModel;
        $finalReply = $reply;
        // Accumulate tool calls for UI
        $allToolCalls = array_merge($allToolCalls, $toolCalls);
        aimLog('Chat iter ' . $iteration . ' reply len ' . strlen($reply) . ' tools ' . count($toolCalls));

        // No tools — we are done
        if (empty($toolCalls)) {
            break;
        }
        // Dry run or manual approval — stop and let UI handle (background-safe)
        if ($isDryRun || !$autoApprove) {
            aimAppendHistory('assistant', $reply, ['tool_calls'=>$toolCalls, 'provider'=>$finalProvider, 'model'=>$finalModel]);
            // Update conversation and mark idle (awaiting manual approval)
            $conv['messages'][] = ['role'=>'assistant','content'=>$reply,'ts'=>date('Y-m-d H:i:s'),'meta'=>['tool_calls'=>$toolCalls,'provider'=>$finalProvider,'model'=>$finalModel]];
            $conv['status'] = 'idle';
            $conv['updated'] = date('Y-m-d H:i:s');
            aimSaveConversation($conv);
            return json([
                'success'=>true,
                'reply'=>$reply,
                'tool_calls'=>$toolCalls,
                'executed'=>[],
                'dry_run'=>$isDryRun,
                'auto_approve'=>$autoApprove,
                'provider'=>$finalProvider,
                'model'=>$finalModel,
                'conversationId'=>$conversationId,
                'conversation'=>$conv,
            ]);
        }
        // Auto-execute all tool calls and feed results back for next iteration
        $executedThisTurn = [];
        $obsLines = [];
        foreach ($toolCalls as $tc) {
            $out = aimExecuteTool($tc['name'] ?? '', $tc['arguments'] ?? []);
            $executedThisTurn[] = ['call'=>$tc,'result'=>$out];
            $allExecuted[] = ['call'=>$tc,'result'=>$out];
            aimLog('Auto-executed tool iter ' . $iteration . ' ' . ($tc['name'] ?? '') . ' success=' . (!empty($out['success']) ? '1' : '0'));
            $obsLines[] = '- ' . ($tc['name'] ?? 'tool') . '(' . json_encode($tc['arguments'] ?? []) . ') => ' . json_encode($out, JSON_UNESCAPED_SLASHES);
        }
        // Build observation for next AI turn — plain user message works for all providers
        $obs = "Tool execution results (iteration " . ($iteration+1) . "):\n" . implode("\n", $obsLines) . "\n\nOriginal request: \"" . $prompt . "\" — if the playlist still needs creation and does not already exist, call the appropriate tool now (e.g. create_playlist). If done, reply confirming completion with details.";
        // Append assistant tool call + observation to message history for next loop
        // Include tool call names in assistant content for providers that don't use strict tool role
        $assistantContent = $reply;
        if (!empty($toolCalls)) {
            $assistantContent .= "\n\n[Called tools: " . implode(', ', array_column($toolCalls, 'name')) . "]";
        }
        $currentMessages[] = ['role'=>'assistant','content'=>$assistantContent];
        $currentMessages[] = ['role'=>'user','content'=>$obs];
        $iteration++;
        // Continue loop to let model issue next tool call (e.g. create after list)
        // Safety: if we just executed create_playlist, the next iteration will likely have no tools and we will exit
        if ($iteration >= $maxIterations) {
            aimLog('Chat max iterations reached');
            break;
        }
        // Small guard: avoid looping forever if model keeps returning same tool
        if ($iteration > 0 && count($toolCalls) > 0 && $toolCalls[0]['name'] === 'list_playlists' && $iteration >= 2) {
            // If we have listed twice without creating, break to avoid loop
            break;
        }
    }

    // Append final assistant reply to history and conversation (background-safe)
    aimAppendHistory('assistant', $finalReply, ['tool_calls'=>$allToolCalls, 'provider'=>$finalProvider, 'model'=>$finalModel]);
    // Update conversation: append assistant message, mark idle, persist
    $conv['messages'][] = ['role'=>'assistant','content'=>$finalReply,'ts'=>date('Y-m-d H:i:s'),'meta'=>['tool_calls'=>$allToolCalls,'executed'=>$allExecuted,'provider'=>$finalProvider,'model'=>$finalModel]];
    $conv['status'] = 'idle';
    $conv['updated'] = date('Y-m-d H:i:s');
    // Titles are set from the first prompt at conversation creation; a final
    // reply must not overwrite them.
    aimSaveConversation($conv);

    aimLog('Chat final reply conv=' . $conversationId . ' len=' . strlen($finalReply) . ' total tools=' . count($allToolCalls) . ' executed=' . count($allExecuted));

    return json([
        'success'=>true,
        'reply'=>$finalReply,
        'tool_calls'=>$allToolCalls,
        'executed'=>$allExecuted,
        'dry_run'=>$isDryRun,
        'auto_approve'=>$autoApprove,
        'provider'=>$finalProvider,
        'model'=>$finalModel,
        'conversationId'=>$conversationId,
        'conversation'=>$conv,
    ]);
}

function aimHistoryEndpoint() {
    $h = aimLoadHistory();
    return json(['success'=>true,'entries'=>$h]);
}

function aimHistoryClearEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    aimClearHistory();
    aimLog('History cleared');
    return json(['success'=>true,'message'=>'History cleared']);
}

function aimConversationsListEndpoint() {
    $list = aimListConversations();
    return json(['success'=>true,'conversations'=>$list]);
}
function aimConversationsCreateEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) $body = [];
    $body = array_merge($_POST, $body);
    $title = trim((string)($body['title'] ?? ''));
    $conv = aimCreateConversation($title);
    return json(['success'=>true,'conversation'=>$conv]);
}
function aimGetRouteId($key='id') {
    if (function_exists('param')) {
        $v = @param($key, null);
        if ($v) return $v;
    }
    if (isset($_GET[$key]) && $_GET[$key] !== '') return $_GET[$key];
    if (isset($_POST[$key]) && $_POST[$key] !== '') return $_POST[$key];
    // Fallback: parse from REQUEST_URI like /api/plugin/fpp-AImode/conversations/conv_xxx or /conversations/conv_xxx/delete
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri && preg_match('#/conversations/([a-zA-Z0-9_\-]+)#', $uri, $m)) return $m[1];
    return null;
}
function aimConversationGetEndpoint() {
    $id = aimGetRouteId('id');
    if (!$id) {
        $raw = file_get_contents('php://input');
        $b = json_decode($raw ?: '{}', true);
        $id = $b['id'] ?? null;
    }
    if (!$id) return json(['success'=>false,'error'=>'Conversation id required']);
    $conv = aimGetConversation($id);
    if (!$conv) return json(['success'=>false,'error'=>'Conversation not found']);
    return json(['success'=>true,'conversation'=>$conv]);
}
function aimConversationDeleteEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $id = aimGetRouteId('id');
    if (!$id) {
        $raw = file_get_contents('php://input');
        $b = json_decode($raw ?: '{}', true);
        $id = $b['id'] ?? null;
    }
    if (!$id) return json(['success'=>false,'error'=>'Conversation id required']);
    aimDeleteConversation($id);
    return json(['success'=>true,'message'=>'Conversation deleted']);
}
function aimConversationUpdateEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $id = aimGetRouteId('id');
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) $body = [];
    $body = array_merge($_POST, $body);
    if (!$id) $id = $body['id'] ?? null;
    if (!$id) return json(['success'=>false,'error'=>'Conversation id required']);
    $conv = aimGetConversation($id);
    if (!$conv) return json(['success'=>false,'error'=>'Conversation not found']);
    if (isset($body['title'])) $conv['title'] = (function_exists('mb_substr') ? mb_substr(trim($body['title']), 0, 80) : substr(trim($body['title']), 0, 80));
    if (isset($body['provider']) && aimProviderExists($body['provider'])) $conv['provider'] = $body['provider'];
    if (isset($body['model'])) $conv['model'] = trim((string)$body['model']);
    // Allow status update if caller is internal (not exposed to UI normally)
    if (isset($body['status'])) $conv['status'] = $body['status'];
    aimSaveConversation($conv);
    return json(['success'=>true,'conversation'=>$conv]);
}

function aimExecuteEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) $body = [];
    $body = array_merge($_POST, $body);

    $name = trim((string)($body['name'] ?? $body['tool'] ?? ''));
    $args = $body['arguments'] ?? $body['args'] ?? [];
    if (is_string($args)) { $args = json_decode($args, true) ?: []; }

    if ($name === '') return json(['success'=>false,'error'=>'Tool name required']);
    // Validate tool exists
    $known = array_column(aimGetTools(), 'name');
    if (!in_array($name, $known, true)) return json(['success'=>false,'error'=>'Unknown tool: ' . $name]);

    $settings = aimLoadSettings();
    if (!empty($settings['dry_run'])) {
        aimLog('Dry-run execute tool ' . $name);
        return json(['success'=>true,'dry_run'=>true,'message'=>'Dry-run: not executed','tool'=>$name,'arguments'=>$args]);
    }

    aimLog('Execute tool ' . $name . ' args=' . json_encode($args));
    $out = aimExecuteTool($name, $args);
    if (!empty($out['success'])) aimLog('Tool ' . $name . ' success');
    else aimLog('Tool ' . $name . ' failed: ' . ($out['error'] ?? 'unknown'));
    return json(array_merge(['tool'=>$name,'arguments'=>$args], $out));
}

function aimLogsEndpoint() {
    $logFile = aimGetLogFile();
    if (!file_exists($logFile)) return json(['success'=>true,'entries'=>[]]);
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return json(['success'=>false,'error'=>'Could not read log file']);
    $lines = array_slice($lines, -100);
    $entries = [];
    foreach ($lines as $line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) fpp-AImode (\S+): (.*)$/', $line, $m)) {
            $level = 'INFO';
            if (preg_match('/^(SUCCESS|ERROR|WARNING)/', $m[3], $lm)) $level = $lm[1];
            elseif (stripos($m[3], 'failed') !== false || stripos($m[3], 'error') !== false) $level = 'ERROR';
            $entries[] = ['timestamp'=>$m[1],'source'=>$m[2],'level'=>$level,'message'=>$m[3]];
        } else {
            $entries[] = ['timestamp'=>'','source'=>'','level'=>'INFO','message'=>$line];
        }
    }
    return json(['success'=>true,'entries'=> array_reverse($entries)]);
}

function aimRestartFPPDEndpoint() {
    if (!aimSameOriginRequest()) return aimDenyCrossOrigin();
    $opts = ['http'=>['method'=>'PUT','header'=>'Content-Type: application/json','content'=>'1']];
    $res = @file_get_contents('http://localhost/api/settings/restartFlag', false, stream_context_create($opts));
    if ($res === false) return json(['success'=>false,'error'=>'Could not set restart flag']);
    return json(['success'=>true,'message'=>'FPPD restart flag set']);
}
?>
