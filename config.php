<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## config.php — Multi-provider config                      ##
 * #############################################################
 */
$aimPluginDir = __DIR__;
$aimMediaDir = $settings['mediaDirectory'] ?? $GLOBALS['settings']['mediaDirectory'] ?? getenv('MEDIADIR') ?: null;
if (!$aimMediaDir) $aimMediaDir = $aimPluginDir;
$aimDataDir = rtrim($aimMediaDir, '/') . '/plugindata/fpp-AImode';
if ($aimMediaDir === $aimPluginDir) $aimDataDir = $aimPluginDir . '/config';
$aimSettingsFile = $aimDataDir . '/settings.json';
$aimLegacySettingsFile = $aimPluginDir . '/config/settings.json';
if (!file_exists($aimSettingsFile) && file_exists($aimLegacySettingsFile)) $aimSettingsFile = $aimLegacySettingsFile;

$aimDefaults = [
    'provider' => 'openai',
    'api_key' => '',
    'model' => 'gpt-4o-mini',
    'base_url' => '',
    'providers' => [],
    'defaultProvider' => 'openai',
    'system_prompt' => '',
    'temperature' => 0.7,
    'max_tokens' => 2048,
    'auto_approve' => 1,
    'dry_run' => 0,
    'include_fpp_context' => 1,
    'history_enabled' => 1,
];
$aimProviders = [
    'openai' => ['label'=>'OpenAI','defaultBase'=>'https://api.openai.com/v1','models'=>['gpt-4o','gpt-4o-mini','gpt-4-turbo','o1','o1-mini','o3-mini','o3','gpt-5','gpt-5-mini','gpt-5-nano','gpt-5.6-sol','gpt-5.6-terra','gpt-5.6-luna','gpt-4.1','gpt-4.1-mini']],
    'anthropic' => ['label'=>'Anthropic (Claude)','defaultBase'=>'https://api.anthropic.com','models'=>['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-3-opus-20240229','claude-3-haiku-20240307','claude-sonnet-4-6','claude-opus-4-8','claude-opus-5','claude-sonnet-5','claude-haiku-4-5']],
    'google' => ['label'=>'Google Gemini','defaultBase'=>'https://generativelanguage.googleapis.com','models'=>['gemini-3.6-flash','gemini-3.8-flash','gemini-3.7-flash','gemini-3.5-flash','gemini-3.5-flash-lite','gemini-2.5-flash','gemini-2.5-pro','gemini-1.5-flash','gemini-1.5-pro']],
    'mistral' => ['label'=>'Mistral','defaultBase'=>'https://api.mistral.ai/v1','models'=>['mistral-large-latest','mistral-small-latest','mistral-nemo','open-mistral-7b','mistral-large-2407','codestral-latest']],
    'grok' => ['label'=>'Grok (xAI)','defaultBase'=>'https://api.x.ai/v1','models'=>['grok-3','grok-3-mini','grok-3-fast','grok-2','grok-beta','grok-2-mini']],
    'openrouter' => ['label'=>'OpenRouter','defaultBase'=>'https://openrouter.ai/api/v1','models'=>['openai/gpt-4o','openai/gpt-4o-mini','openai/gpt-5','openai/gpt-5-mini','anthropic/claude-3.5-sonnet','anthropic/claude-opus-4','google/gemini-2.5-flash','google/gemini-3.6-flash','mistralai/mistral-large','x-ai/grok-3']],
    'ollama' => ['label'=>'Ollama (Local — not installed)','defaultBase'=>'','models'=>['llama3.1','llama3.3','qwen2.5','qwen3','mistral','gemma2','gemma3','phi3','phi4','codellama','deepseek-r1']],
    'azure' => ['label'=>'Azure OpenAI','defaultBase'=>'https://{your-endpoint}.openai.azure.com','models'=>['gpt-4o','gpt-4o-mini','gpt-35-turbo','gpt-5','gpt-5-mini','o3','o4-mini']],
];
// Load settings and ensure providers map exists (migrate legacy)
$aimSettings = $aimDefaults;
if (file_exists($aimSettingsFile)) {
    $j = json_decode(@file_get_contents($aimSettingsFile), true);
    if (is_array($j)) {
        $aimSettings = array_merge($aimDefaults, $j);
        // Migrate single provider to providers map if needed
        if (empty($aimSettings['providers']) || !is_array($aimSettings['providers'])) $aimSettings['providers'] = [];
        if (empty($aimSettings['defaultProvider'])) $aimSettings['defaultProvider'] = $aimSettings['provider'] ?? 'openai';
        $legacyProv = $aimSettings['provider'] ?? 'openai';
        $legacyKey = $aimSettings['api_key'] ?? '';
        $legacyModel = $aimSettings['model'] ?? '';
        $legacyBase = $aimSettings['base_url'] ?? '';
        if (($legacyKey !== '' || $legacyModel !== '') && empty($aimSettings['providers'][$legacyProv])) {
            $aimSettings['providers'][$legacyProv] = ['api_key'=>$legacyKey,'model'=>$legacyModel,'base_url'=>$legacyBase];
        }
        foreach (array_keys($aimProviders) as $k) {
            if (!isset($aimSettings['providers'][$k]) || !is_array($aimSettings['providers'][$k])) {
                $def = $aimProviders[$k];
                $aimSettings['providers'][$k] = ['api_key'=>'','model'=>$def['defaultBase'] ? ($def['models'][0] ?? '') : '','base_url'=>''];
                // Actually model should be defaultModel, not base
                $aimSettings['providers'][$k]['model'] = $aimProviders[$k]['models'][0] ?? '';
            } else {
                if (!isset($aimSettings['providers'][$k]['api_key'])) $aimSettings['providers'][$k]['api_key'] = '';
                if (!isset($aimSettings['providers'][$k]['model']) || $aimSettings['providers'][$k]['model'] === '') {
                    $aimSettings['providers'][$k]['model'] = $aimProviders[$k]['models'][0] ?? '';
                }
                if (!isset($aimSettings['providers'][$k]['base_url'])) $aimSettings['providers'][$k]['base_url'] = '';
            }
        }
    }
}
$_fppUiLevel = (int)($GLOBALS['settings']['uiLevel'] ?? $settings['uiLevel'] ?? 0);
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
@media only screen and (max-width: 480px) {
    fieldset { padding: 8px !important; }
    table { width: 100%; table-layout: fixed; word-wrap: break-word; }
    td { display: block; width: 100% !important; box-sizing: border-box; }
    input[type="text"], input[type="password"], select, textarea { width: 100% !important; box-sizing: border-box; }
    input.buttons { width: 100%; margin-bottom: 6px; box-sizing: border-box; }
}
.aim-welcome { background: linear-gradient(135deg, var(--bs-primary-bg-subtle,#e7f1ff) 0%, var(--bs-tertiary-bg,#f8f9fa) 100%); border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; padding:16px 18px; display:flex; gap:14px; align-items:center; margin-bottom:16px; }
.aim-welcome-icon { font-size:28px; }
.aim-welcome h4 { margin:0 0 4px 0; font-size:16px; font-weight:700; }
.aim-welcome p { margin:0; font-size:12.5px; color:var(--bs-secondary-color); line-height:1.5; }
.aim-steps { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0; }
.aim-step { flex:1 1 160px; background:var(--bs-body-bg,#fff); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:10px 12px; text-align:center; }
.aim-step b { display:block; font-size:13px; color:var(--bs-primary); }
.aim-step span { font-size:11px; color:var(--bs-secondary-color); }
.provider-card { border:1px solid var(--bs-border-color); border-radius:8px; padding:12px 14px; margin-bottom:10px; background:var(--bs-body-bg); transition:.15s; }
.provider-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); transform: translateY(-1px); }
.provider-card.configured { border-left:4px solid var(--bs-success); }
.provider-card.default { background:var(--bs-primary-bg-subtle,#f0f7ff); border-color:var(--bs-primary-border-subtle); }
.aim-overview-cards { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:10px; margin-top:12px; }
.aim-overview-card { background:var(--bs-body-bg,#fff); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:10px 12px; display:flex; flex-direction:column; gap:6px; }
.aim-overview-card.is-default { border-color:var(--bs-primary); background:var(--bs-primary-bg-subtle,#f0f7ff); }
.aim-overview-card .aim-card-top { display:flex; justify-content:space-between; align-items:center; }
.aim-overview-card .aim-card-title { font-weight:700; font-size:13px; }
.aim-overview-card .aim-card-model { font-size:11px; color:var(--bs-secondary-color); font-family:monospace; }
fieldset { border-radius:10px !important; }
fieldset legend { font-weight:700; font-size:14px; padding:0 8px; }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto; max-width:960px;">
    <div class="aim-welcome">
        <div class="aim-welcome-icon">⚙️</div>
        <div style="flex:1;">
            <h4>Connect your AI provider — 30 seconds</h4>
            <p>Pick a <b>Default Provider</b> for new chats, paste its API key, choose a model, and hit <b>Test</b>. You can add multiple providers and switch per-conversation later in the Assistant.</p>
        </div>
        <a href="plugin.php?plugin=fpp-AImode&page=assistant.php" class="buttons" style="white-space:nowrap;">💬 Open Assistant</a>
    </div>
    <div class="aim-steps">
        <div class="aim-step"><b>① Choose</b><span>Default provider</span></div>
        <div class="aim-step"><b>② Paste</b><span>API key / token</span></div>
        <div class="aim-step"><b>③ Pick</b><span>Model</span></div>
        <div class="aim-step"><b>④ Test & Save</b><span>Verify connection</span></div>
    </div>

    <fieldset class="border p-3">
        <legend>⭐ Default provider for new chats</legend>
        <div class="p-3">
            <div class="alert alert-info" style="font-size:12.5px; border-radius:8px;">
                💡 New conversations automatically use the <b>Default Provider</b>. You can override it per-chat in the Assistant's bottom bar. No need to re-enter keys — just switch.
            </div>
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; background:var(--bs-tertiary-bg,#f8f9fa); padding:12px; border-radius:8px; border:1px solid var(--bs-border-color,#dee2e6);">
                <label for="aim_default_provider" style="font-weight:600; font-size:13px; white-space:nowrap;">Default Provider:</label>
                <select id="aim_default_provider" class="form-select" style="flex:1 1 220px; max-width:320px; font-weight:500;">
                    <?php foreach ($aimProviders as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo ($aimSettings['defaultProvider'] ?? $aimSettings['provider'])===$k?'selected':''; ?>><?php echo htmlspecialchars($v['label']); ?> (<?php echo $k; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span class="text-secondary" style="font-size:12px;">Used for every new chat</span>
                <span id="aim_default_status" class="text-success" style="font-size:12px; font-weight:600;"></span>
            </div>
            <div class="aim-overview-cards" id="aim_providers_cards" style="margin-top:14px;"></div>
            <div class="table-responsive" style="margin-top:12px;">
                <table class="table table-sm" style="font-size:12px;">
                    <thead><tr><th>Provider</th><th>Status</th><th>Model</th><th>Default</th></tr></thead>
                    <tbody id="aim_providers_overview"></tbody>
                </table>
            </div>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>🔑 Configure a provider — add your API key</legend>
        <div class="p-3">
            <div class="alert alert-light" style="font-size:12px; border:1px dashed var(--bs-border-color); border-radius:8px;">
                💡 Choose a provider, paste its key, pick a model, then <b>Test</b> and <b>Save</b>. Keys are stored locally in <code>plugindata/fpp-AImode/settings.json</code> (0600, never logged).
            </div>
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:6px; width:180px;"><b>① Provider:</b></td>
                    <td style="padding:6px;">
                        <select id="aim_provider" class="form-select" style="max-width:320px; font-weight:500;">
                            <?php foreach ($aimProviders as $k=>$v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v['label']); ?> (<?php echo $k; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-secondary" style="font-size:11px; margin-top:4px;">Select the provider you want to configure — you can add several.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px;"><b>② API Key:</b> <span class="text-danger">*</span></td>
                    <td style="padding:6px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="password" id="aim_api_key" class="form-control" style="flex:1 1 260px; min-width:0; font-family:monospace; font-size:13px;" placeholder="Paste API key here">
                            <label class="form-check-label" style="font-size:13px;"><input type="checkbox" class="form-check-input" id="aim_show_key" onchange="$('#aim_api_key').attr('type', this.checked ? 'text':'password')"> Show</label>
                            <span id="aim_key_status" class="text-secondary" style="font-size:12px;"></span>
                        </div>
                        <div class="text-secondary" style="font-size:11px; margin-top:6px;">
                            Stored locally. Leave blank only for <b>Ollama</b> (local). Expected format: <span id="aim_key_hint" class="text-warning" style="font-weight:600;"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px;"><b>③ Model:</b></td>
                    <td style="padding:6px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="aim_model_select" class="form-select" style="flex:1 1 180px; min-width:0; font-weight:500;"></select>
                            <input type="text" id="aim_model" class="form-control" style="flex:1 1 160px; min-width:0; font-family:monospace; font-size:12px;" placeholder="or type custom model ID">
                            <button type="button" class="buttons" id="aim_refresh_models" onclick="aimConfig.fetchModels(true)" title="Fetch live model list from provider (needs API key)" style="white-space:nowrap;">↻ Fetch models</button>
                            <span id="aim_models_status" class="text-secondary" style="font-size:12px;"></span>
                        </div>
                        <div class="text-secondary" style="font-size:11px; margin-top:6px;">Choose from live list or type a custom deployment/model ID (e.g. Azure deployment name).</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px;"><b>Base URL:</b> <span class="text-secondary" style="font-weight:400;">(optional)</span></td>
                    <td style="padding:6px;">
                        <input type="text" id="aim_base_url" class="form-control" style="font-family:monospace; font-size:12px;" placeholder="Leave empty for default — e.g. https://your-endpoint.openai.azure.com or http://192.168.1.50:11434">
                        <div class="text-secondary" style="font-size:11px; margin-top:6px;">
                            Default: <code id="aim_default_base"></code> · For <b>Azure</b> paste your endpoint, for <b>Ollama</b> your LAN host, or a proxy.
                        </div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding:10px 4px;">
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <button type="button" class="buttons" onclick="aimConfig.saveProvider();" style="background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); font-weight:700; padding:8px 18px; border-radius:8px;">💾 Save This Provider</button>
                            <button type="button" class="buttons" onclick="aimConfig.testProvider();" style="font-weight:600; padding:8px 14px; border-radius:8px;">🧪 Test Connection</button>
                            <span id="aim_save_result" style="font-weight:600; font-size:13px;"></span>
                        </div>
                        <div id="aim_test_result" style="margin-top:10px; padding:8px; border-radius:8px; min-height:20px;"></div>
                    </td>
                </tr>
            </table>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>🎛️ Global behavior</legend>
        <div class="p-3">
            <div class="alert alert-light" style="font-size:12px; border:1px dashed var(--bs-border-color); border-radius:8px; margin-bottom:14px;">
                Fine-tune how the AI responds and what it can do. Changes apply to all conversations.
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                <div style="background:var(--bs-tertiary-bg,#f8f9fa); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:14px;">
                    <h6 style="font-size:13px; font-weight:700; margin:0 0 10px 0;">🧠 Creativity</h6>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:600;">Temperature: <span id="aim_temp_val" class="badge bg-secondary"><?php echo htmlspecialchars($aimSettings['temperature']); ?></span></label>
                        <input type="range" id="aim_temp" class="form-range" min="0" max="2" step="0.1" value="<?php echo htmlspecialchars($aimSettings['temperature']); ?>" style="margin-top:6px;">
                        <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--bs-secondary-color);"><span>0 precise</span><span>1 balanced</span><span>2 creative</span></div>
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:600;">Max tokens per reply</label>
                        <div style="display:flex; gap:8px; align-items:center; margin-top:4px;">
                            <input type="number" id="aim_max_tokens" class="form-control" min="64" max="16384" step="64" value="<?php echo (int)$aimSettings['max_tokens']; ?>" style="max-width:110px;">
                            <span class="text-secondary" style="font-size:11px;">64–16384 · Default 2048</span>
                        </div>
                    </div>
                </div>
                <div style="background:var(--bs-tertiary-bg,#f8f9fa); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:14px;">
                    <h6 style="font-size:13px; font-weight:700; margin:0 0 10px 0;">⚡ Execution</h6>
                    <label style="display:flex; gap:8px; align-items:center; font-size:13px; margin-bottom:8px; cursor:pointer;"><input type="checkbox" id="aim_dryrun" class="form-check-input" <?php echo !empty($aimSettings['dry_run'])?'checked':''; ?>> <span><b>Dry-run</b> — propose only, never execute</span></label>
                    <label style="display:flex; gap:8px; align-items:center; font-size:13px; margin-bottom:8px; cursor:pointer;"><input type="checkbox" id="aim_auto" class="form-check-input" <?php echo !empty($aimSettings['auto_approve'])?'checked':''; ?>> <span><b>Auto-approve</b> — run tools without manual approval</span></label>
                    <div class="text-secondary" style="font-size:11px; margin-top:8px; padding:6px 8px; background:#fff; border-radius:6px; border:1px solid var(--bs-border-color,#eee);">💡 Auto-approve is on by default for a smooth experience. Uncheck for a careful review step.</div>
                </div>
                <div style="background:var(--bs-tertiary-bg,#f8f9fa); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:14px;">
                    <h6 style="font-size:13px; font-weight:700; margin:0 0 10px 0;">📦 Context & Memory</h6>
                    <label style="display:flex; gap:8px; align-items:center; font-size:13px; margin-bottom:8px; cursor:pointer;"><input type="checkbox" id="aim_ctx" class="form-check-input" <?php echo !empty($aimSettings['include_fpp_context'])?'checked':''; ?>> <span><b>Include FPP context</b> — send live status/playlists with each prompt</span></label>
                    <label style="display:flex; gap:8px; align-items:center; font-size:13px; cursor:pointer;"><input type="checkbox" id="aim_hist" class="form-check-input" <?php echo !empty($aimSettings['history_enabled'])?'checked':''; ?>> <span><b>Keep history</b> — remember last 40 turns</span></label>
                </div>
            </div>
            <div style="margin-top:14px;">
                <label style="font-size:12px; font-weight:600;">System prompt <span class="text-secondary" style="font-weight:400;">(optional, advanced)</span></label>
                <textarea id="aim_system_prompt" rows="3" class="form-control" style="width:100%; font-family:monospace; font-size:12px; margin-top:4px;" placeholder="Leave empty for the default FPP assistant instructions…"><?php echo htmlspecialchars($aimSettings['system_prompt']); ?></textarea>
                <div class="text-secondary" style="font-size:11px; margin-top:4px;">Only change if you want to override the default FPP expert behavior.</div>
            </div>
            <div style="margin-top:14px; display:flex; gap:8px; align-items:center;">
                <button type="button" class="buttons" onclick="aimConfig.saveGlobal();" style="background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); font-weight:600; padding:8px 18px;">💾 Save Global Settings</button>
                <span id="aim_global_result" style="font-size:13px; font-weight:600;"></span>
            </div>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>🚀 Quick start</legend>
        <div class="p-3" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
            <a href="plugin.php?plugin=fpp-AImode&page=assistant.php" class="buttons" style="text-align:center; padding:14px; border-radius:8px; text-decoration:none; display:flex; flex-direction:column; gap:4px; align-items:center;"><span style="font-size:20px;">💬</span><b>Open Assistant</b><span style="font-size:11px; color:var(--bs-secondary-color);">Start chatting with your FPP</span></a>
            <a href="plugin.php?plugin=fpp-AImode&page=status.php" class="buttons" style="text-align:center; padding:14px; border-radius:8px; text-decoration:none; display:flex; flex-direction:column; gap:4px; align-items:center;"><span style="font-size:20px;">📊</span><b>View Status</b><span style="font-size:11px; color:var(--bs-secondary-color);">Health & diagnostics</span></a>
            <a href="plugin.php?plugin=fpp-AImode&page=help.php" class="buttons" style="text-align:center; padding:14px; border-radius:8px; text-decoration:none; display:flex; flex-direction:column; gap:4px; align-items:center;"><span style="font-size:20px;">📖</span><b>Help & Examples</b><span style="font-size:11px; color:var(--bs-secondary-color);">See what you can ask</span></a>
        </div>
    </fieldset>
</div>

<script>
var aimProviders = <?php echo json_encode($aimProviders); ?>;
var aimSettings = <?php echo json_encode($aimSettings); ?>;

var aimConfig = {
    _fetchedModels: {},
    _providers: aimSettings.providers || {},
    _defaultProvider: aimSettings.defaultProvider || aimSettings.provider || 'openai',
    init: function(){
        // Populate default provider select
        $('#aim_default_provider').val(aimConfig._defaultProvider);
        aimConfig.renderOverview();
        // Init provider editor to default provider
        $('#aim_provider').val(aimConfig._defaultProvider);
        aimConfig.loadProvider(aimConfig._defaultProvider);
        aimConfig.populateModels();
        // Auto-fetch for default
        setTimeout(function(){ aimConfig.fetchModels(false); }, 900);
    },
    renderOverview: function(){
        var tbody = $('#aim_providers_overview').empty();
        var cards = $('#aim_providers_cards').empty();
        var def = aimConfig._defaultProvider;
        Object.keys(aimProviders).forEach(function(k){
            var p = aimConfig._providers[k] || {api_key:'',model:'',base_url:''};
            var isOllama = k==='ollama';
            // Ollama requires explicit host config and local install — not enabled by default
            var configured = (p.api_key && p.api_key.length>0) || (isOllama && p.base_url && p.base_url.length>0);
            var isDefault = k===def;
            var row = $('<tr>').addClass(isDefault?'table-info':'');
            row.append($('<td>').html('<b>'+aimProviders[k].label+'</b> ('+k+')' + (isDefault?' <span class="badge bg-primary">default</span>':'')));
            var statusHtml = configured ? '<span class="text-success">✓</span> ' + (isOllama ? 'host set' : 'key set') : (isOllama ? '<span class="text-secondary" title="Requires local Ollama install">— not installed</span>' : '<span class="text-secondary">—</span>');
            row.append($('<td>').html(statusHtml));
            row.append($('<td>').text(p.model || aimProviders[k].models[0] || ''));
            row.append($('<td>').text(isDefault?'★':'' ));
            tbody.append(row);
            // Card
            var card = $('<div>').addClass('aim-overview-card' + (isDefault?' is-default':'' )).css('cursor','pointer').attr('title','Click to configure '+k).on('click', function(){ $('#aim_provider').val(k).trigger('change'); $('html,body').animate({scrollTop:$('#aim_provider').closest('fieldset').offset().top - 20}, 300); });
            var top = $('<div>').addClass('aim-card-top');
            top.append($('<span>').addClass('aim-card-title').html(aimProviders[k].label + (isDefault?' <span class="badge bg-primary" style="font-size:10px;">DEFAULT</span>':'')));
            var badge = configured ? '<span class="badge bg-success">✓ Ready</span>' : (isOllama ? '<span class="badge bg-secondary" title="Requires local Ollama install">Not installed</span>' : '<span class="badge bg-secondary">— Not set</span>');
            top.append($('<span>').html(badge));
            card.append(top);
            card.append($('<div>').addClass('aim-card-model').text((p.model || aimProviders[k].models[0] || '—') + ' · '+k));
            if(p.base_url) card.append($('<div>').css({fontSize:'10px', color:'var(--bs-secondary-color)', fontFamily:'monospace', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}).text(p.base_url));
            else if(isOllama) card.append($('<div>').css({fontSize:'10px', color:'var(--bs-warning-text-emphasis)', fontStyle:'italic'}).text('Requires local install — set host to enable'));
            cards.append(card);
        });
    },
    loadProvider: function(prov){
        var p = aimConfig._providers[prov] || {api_key:'',model:'',base_url:''};
        var meta = aimProviders[prov] || {models:[], defaultBase:''};
        $('#aim_api_key').val(p.api_key || '');
        $('#aim_model').val(p.model || meta.models[0] || '');
        $('#aim_base_url').val(p.base_url || '');
        aimConfig.populateModels();
        var isOllama2 = prov==='ollama';
        if(isOllama2) $('#aim_key_status').html(p.base_url ? '<span class="text-success">✓ host set</span>' : '<span class="text-warning">— not installed</span>');
        else $('#aim_key_status').text(p.api_key ? '✓ key set' : '—');
        setTimeout(function(){ aimConfig.fetchModels(false); }, 400);
    },
    populateModels: function(fetched){
        var prov = $('#aim_provider').val();
        var meta = aimProviders[prov] || {models:[], defaultBase:''};
        var list = fetched || meta.models || [];
        if (fetched && fetched.length) aimConfig._fetchedModels[prov] = fetched;
        else if (aimConfig._fetchedModels[prov]) list = aimConfig._fetchedModels[prov];
        // Ensure default for this provider is flash for Gemini (already first in preset = gemini-3.6-flash)
        if(!list || !list.length) list = meta.models || [];
        var sel = $('#aim_model_select').empty();
        (list||[]).forEach(function(m){ sel.append($('<option>',{value:m,text:m})); });
        var cur = $('#aim_model').val();
        if (cur && list.indexOf(cur)===-1 && cur.indexOf('/')===-1) {
            sel.append($('<option>',{value:cur,text:cur+' (custom)'}));
        } else if (cur && list.indexOf(cur)===-1) {
            sel.append($('<option>',{value:cur,text:cur+' (custom)'}));
        }
        if (list.indexOf(cur)!==-1) sel.val(cur);
        else if (list.length && !cur) { sel.val(list[0]); $('#aim_model').val(list[0]); }
        else if (cur) sel.val(cur);
        var isOllama = prov==='ollama';
        $('#aim_default_base').text(meta.defaultBase || (isOllama ? '— not set (set host to enable)' : ''));
        if(isOllama && !$('#aim_base_url').val()){
            $('#aim_default_base').html('<span class="text-warning">— not set — Ollama not installed (enter host to enable)</span>');
        }
        if (fetched) {
            $('#aim_models_status').html('<span class="text-success">✓ '+fetched.length+' live</span>');
            setTimeout(function(){ $('#aim_models_status').text(''); }, 4000);
        } else if(isOllama && !$('#aim_base_url').val()){
            $('#aim_models_status').html('<span class="text-secondary">Preset '+list.length+' — Ollama not installed</span>');
        } else if(!fetched && list.length && prov!=='ollama' && !$('#aim_api_key').val()){
            $('#aim_models_status').html('<span class="text-secondary">Preset '+list.length+' — enter key + ↻ for live</span>');
        } else if(!fetched && list.length){
            $('#aim_models_status').html('<span class="text-secondary">Preset '+list.length+' — fetching live…</span>');
        }
        var hint=''; if(prov==='openai') hint='sk-…'; else if(prov==='anthropic') hint='sk-ant-…'; else if(prov==='google') hint='AIza…'; else if(prov==='grok') hint='xai-…'; else if(prov==='openrouter') hint='sk-or-…'; else if(isOllama) hint='no key — requires host'; else if(prov==='azure') hint='Azure API key';
        $('#aim_key_hint').text(hint ? 'Expected: '+hint : '');
        if(isOllama) $('#aim_api_key').attr('placeholder','No key needed — set Ollama host below if installed'); else $('#aim_api_key').attr('placeholder','Paste your API key to fetch live models');
        if(isOllama) {
            $('#aim_base_url').attr('placeholder','http://<ollama-host>:11434 — leave empty if not using Ollama');
            if(!$('#aim_base_url').val()) $('#aim_base_url').css('border-color','var(--bs-warning)');
            else $('#aim_base_url').css('border-color','');
        } else {
            $('#aim_base_url').attr('placeholder','Leave empty for default — e.g. https://your-endpoint.openai.azure.com');
            $('#aim_base_url').css('border-color','');
        }
    },
    fetchModels: function(manual){
        var prov = $('#aim_provider').val();
        var key = $('#aim_api_key').val();
        var base = $('#aim_base_url').val();
        var isOllama = prov==='ollama';
        // Ollama requires host to be set — not installed by default
        if(isOllama && !base){
            if(manual) $.jGrowl('Ollama host not set — Ollama not installed. Enter host (e.g. http://192.168.1.50:11434) to fetch live models.',{themeState:'warning'});
            $('#aim_models_status').html('<span class="text-warning">Ollama not installed — set host to fetch live</span>');
            aimConfig.populateModels();
            return;
        }
        // Always show preset immediately; dynamic fetch augments it
        if (!isOllama && !key) {
            if(manual) $.jGrowl('Enter API key for '+prov+' to fetch live models — showing preset '+ (aimProviders[prov]?aimProviders[prov].models.length:0) +' instead',{themeState:'warning'});
            $('#aim_models_status').html('<span class="text-warning">Preset '+ (aimProviders[prov]?aimProviders[prov].models.length:0) +' — need key for live</span>');
            // Still populate preset so dropdown is never empty
            aimConfig.populateModels();
            return;
        }
        $('#aim_models_status').html('<span class="text-secondary">Fetching live…</span>');
        $('#aim_refresh_models').prop('disabled',true);
        $.ajax({
            url:'api/plugin/fpp-AImode/models',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({provider: prov, api_key: key, base_url: base}),
            dataType:'json',
            timeout: 12000,
            success:function(r){
                if(r.success && r.models && r.models.length){
                    aimConfig.populateModels(r.models);
                    if(manual) $.jGrowl('Fetched '+r.models.length+' live models for '+prov,{themeState:'success'});
                } else {
                    var presetN = (aimProviders[prov] ? aimProviders[prov].models.length : 0);
                    $('#aim_models_status').html('<span class="text-warning">Preset '+presetN+' — '+ (r.error||'live unavailable') +'</span>');
                    // keep preset already shown
                    if(manual) $.jGrowl((r.error||'Live fetch failed')+' — showing preset',{themeState:'warning'});
                }
            },
            error:function(xhr, status){
                var m='Could not fetch models'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){ if(status==='timeout') m='timeout — showing preset'; }
                var presetN2 = (aimProviders[prov] ? aimProviders[prov].models.length : 0);
                $('#aim_models_status').html('<span class="text-warning">Preset '+presetN2+' — '+m+'</span>');
                if(manual) $.jGrowl(m+' — showing preset',{themeState:'warning'});
            },
            complete:function(){ $('#aim_refresh_models').prop('disabled',false); }
        });
    },
    saveProvider: function(){
        var prov = $('#aim_provider').val();
        var data = {
            api_key: $('#aim_api_key').val(),
            model: $('#aim_model').val() || $('#aim_model_select').val(),
            base_url: $('#aim_base_url').val()
        };
        if(!data.model){ $.jGrowl('Model required',{themeState:'error'}); return; }
        if(prov!=='ollama' && !data.api_key){
            if(!confirm('No API key for '+prov+'. Save anyway?')) return;
        }
        // Update local map
        aimConfig._providers[prov] = data;
        // Prepare save payload with full providers map
        var payload = {
            providers: aimConfig._providers,
            defaultProvider: $('#aim_default_provider').val(),
            provider: $('#aim_default_provider').val()
        };
        $('#aim_save_result').html('<span class="text-warning">Saving…</span>');
        $.ajax({
            url:'api/plugin/fpp-AImode/save',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify(payload),
            dataType:'json',
            success:function(r){
                if(r.success){
                    $('#aim_save_result').html('<span class="text-success">Saved '+prov+'!</span>');
                    aimConfig.renderOverview();
                    $.jGrowl('Saved '+prov,{themeState:'success'});
                } else {
                    $('#aim_save_result').html('<span class="text-danger">'+escHtml(r.error||'Failed')+'</span>');
                }
            },
            error:function(xhr){
                var m='Could not save'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){}
                $('#aim_save_result').html('<span class="text-danger">'+escHtml(m)+'</span>');
            }
        });
    },
    saveGlobal: function(){
        var payload = {
            system_prompt: $('#aim_system_prompt').val(),
            temperature: parseFloat($('#aim_temp').val()),
            max_tokens: parseInt($('#aim_max_tokens').val(),10),
            include_fpp_context: $('#aim_ctx').is(':checked')?1:0,
            history_enabled: $('#aim_hist').is(':checked')?1:0,
            dry_run: $('#aim_dryrun').is(':checked')?1:0,
            auto_approve: $('#aim_auto').is(':checked')?1:0,
            defaultProvider: $('#aim_default_provider').val(),
            provider: $('#aim_default_provider').val()
        };
        $('#aim_global_result').html('<span class="text-warning">Saving…</span>');
        $.ajax({
            url:'api/plugin/fpp-AImode/save',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify(payload),
            dataType:'json',
            success:function(r){
                if(r.success){
                    $('#aim_global_result').html('<span class="text-success">Saved!</span>');
                    aimConfig._defaultProvider = payload.defaultProvider;
                    aimConfig.renderOverview();
                    $.jGrowl('Global settings saved',{themeState:'success'});
                } else {
                    $('#aim_global_result').html('<span class="text-danger">'+escHtml(r.error||'Failed')+'</span>');
                }
            },
            error:function(xhr){
                var m='Could not save'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){}
                $('#aim_global_result').html('<span class="text-danger">'+escHtml(m)+'</span>');
            }
        });
    },
    testProvider: function(){
        var prov = $('#aim_provider').val();
        var data = {
            provider: prov,
            api_key: $('#aim_api_key').val(),
            model: $('#aim_model').val() || $('#aim_model_select').val(),
            base_url: $('#aim_base_url').val()
        };
        $('#aim_test_result').html('<span class="text-warning">Testing '+escHtml(prov)+' / '+escHtml(data.model)+' …</span>');
        $.ajax({
            url:'api/plugin/fpp-AImode/test',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify(data),
            dataType:'json',
            success:function(r){
                if(r.success){
                    $('#aim_test_result').html('<span class="text-success"><b>✓ Connected</b> — reply: '+escHtml(r.reply||'OK')+' <span class="text-secondary">('+escHtml(r.provider||'')+' / '+escHtml(r.model||'')+')</span></span>');
                    $.jGrowl('Test succeeded',{themeState:'success'});
                } else {
                    $('#aim_test_result').html('<span class="text-danger"><b>✗ Failed:</b> '+escHtml(r.error||'Unknown')+'</span>');
                }
            },
            error:function(xhr){
                var m='Could not reach plugin API'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){}
                $('#aim_test_result').html('<span class="text-danger">'+escHtml(m)+'</span>');
            }
        });
    },
    clearHistory: function(){
        if(!confirm('Clear conversation history?')) return;
        $.ajax({ url:'api/plugin/fpp-AImode/history/clear', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
            success:function(r){ $.jGrowl(r.message||'History cleared',{themeState:'success'}); },
            error:function(){ $.jGrowl('Could not clear history',{themeState:'error'}); }
        });
    }
};

function escHtml(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

$(document).ready(function(){
    aimConfig.init();
    $('#aim_provider').on('change', function(){
        aimConfig.loadProvider($(this).val());
    });
    $('#aim_default_provider').on('change', function(){
        var v=$(this).val();
        aimConfig._defaultProvider = v;
        aimConfig.renderOverview();
        // Auto-save default change
        $.ajax({
            url:'api/plugin/fpp-AImode/save',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({defaultProvider: v, provider: v}),
            dataType:'json',
            success:function(r){
                if(r.success) {
                    $('#aim_default_status').html('<span class="text-success">✓ Default set to '+v+'</span>');
                    setTimeout(function(){ $('#aim_default_status').text(''); }, 3000);
                }
            }
        });
    });
    $('#aim_model_select').on('change', function(){ $('#aim_model').val($(this).val()); });
    $('#aim_temp').on('input', function(){ $('#aim_temp_val').text($(this).val()); });
    // Auto-fetch on provider change
    var _fetchDebounce=null;
    function scheduleFetch(){ clearTimeout(_fetchDebounce); _fetchDebounce=setTimeout(function(){ aimConfig.fetchModels(false); }, 600); }
    $('#aim_provider').on('change', scheduleFetch);
    $('#aim_api_key').on('blur', scheduleFetch);
    setTimeout(function(){ 
        // Initial fetch for default provider if key present
        var prov=$('#aim_provider').val();
        var key=$('#aim_api_key').val();
        if(prov==='ollama' || (key && key.length>8)) aimConfig.fetchModels(false);
    }, 900);
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
