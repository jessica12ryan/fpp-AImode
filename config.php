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
    'openai' => ['label'=>'OpenAI','defaultBase'=>'https://api.openai.com/v1','models'=>['gpt-4o','gpt-4o-mini','gpt-4-turbo','o1','o1-mini','o3-mini','o3','gpt-5','gpt-5-mini','gpt-5.6-sol']],
    'anthropic' => ['label'=>'Anthropic (Claude)','defaultBase'=>'https://api.anthropic.com','models'=>['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-sonnet-4-6','claude-opus-4-8','claude-opus-5','claude-sonnet-5','claude-haiku-4-5']],
    'google' => ['label'=>'Google Gemini','defaultBase'=>'https://generativelanguage.googleapis.com','models'=>['gemini-3.6-flash','gemini-3.8-flash','gemini-3.7-flash','gemini-3.5-flash','gemini-2.5-flash','gemini-2.5-pro','gemini-2.0-flash','gemini-1.5-flash']],
    'mistral' => ['label'=>'Mistral','defaultBase'=>'https://api.mistral.ai/v1','models'=>['mistral-large-latest','mistral-small-latest','mistral-nemo','codestral-latest']],
    'grok' => ['label'=>'Grok (xAI)','defaultBase'=>'https://api.x.ai/v1','models'=>['grok-3','grok-3-mini','grok-2','grok-beta']],
    'openrouter' => ['label'=>'OpenRouter','defaultBase'=>'https://openrouter.ai/api/v1','models'=>['openai/gpt-4o','openai/gpt-4o-mini','openai/gpt-5','anthropic/claude-3.5-sonnet','anthropic/claude-opus-4','google/gemini-2.5-flash','google/gemini-3.6-flash','x-ai/grok-3']],
    'ollama' => ['label'=>'Ollama (Local)','defaultBase'=>'http://localhost:11434','models'=>['llama3.1','llama3.3','qwen2.5','qwen3','mistral','gemma2','gemma3','phi4']],
    'azure' => ['label'=>'Azure OpenAI','defaultBase'=>'https://{your-endpoint}.openai.azure.com','models'=>['gpt-4o','gpt-4o-mini','gpt-35-turbo','gpt-5','o3']],
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
    fieldset { padding: 5px !important; }
    table { width: 100%; table-layout: fixed; word-wrap: break-word; }
    td { display: block; width: 100% !important; box-sizing: border-box; }
    input[type="text"], input[type="password"], select, textarea { width: 100% !important; box-sizing: border-box; }
    input.buttons { width: 100%; margin-bottom: 4px; box-sizing: border-box; }
}
.provider-card { border:1px solid var(--bs-border-color); border-radius:6px; padding:12px; margin-bottom:10px; background:var(--bs-body-bg); }
.provider-card.configured { border-left:4px solid var(--bs-success); }
.provider-card.default { background:var(--bs-tertiary-bg); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>AI Mode — Providers &amp; Default</legend>
        <div class="p-3">
            <div class="alert alert-info" style="font-size:13px;">
                Configure multiple providers below. The <b>Default Provider</b> is automatically used for new conversations. You can still type a provider name in a prompt or change default anytime.
            </div>
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:4px; width:180px;"><b>Default Provider:</b></td>
                    <td style="padding:4px;">
                        <select id="aim_default_provider" class="form-select" style="max-width:320px;">
                            <?php foreach ($aimProviders as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($aimSettings['defaultProvider'] ?? $aimSettings['provider'])===$k?'selected':''; ?>><?php echo htmlspecialchars($v['label']); ?> (<?php echo $k; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-secondary" style="font-size:12px; margin-left:8px;">Used for new conversations</span>
                        <span id="aim_default_status" class="text-success" style="font-size:12px; margin-left:8px;"></span>
                    </td>
                </tr>
            </table>
            <div class="table-responsive" style="margin-top:12px;">
                <table class="table table-sm" style="font-size:12px;">
                    <thead><tr><th>Provider</th><th>Configured</th><th>Model</th><th>Default?</th></tr></thead>
                    <tbody id="aim_providers_overview"></tbody>
                </table>
            </div>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>Configure Provider</legend>
        <div class="p-3">
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:4px; width:180px;"><b>Provider to edit:</b></td>
                    <td style="padding:4px;">
                        <select id="aim_provider" class="form-select" style="max-width:320px;">
                            <?php foreach ($aimProviders as $k=>$v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v['label']); ?> (<?php echo $k; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-secondary" style="font-size:12px; margin-left:8px;">Select a provider to configure its API key and model</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>API Key / Token:</b></td>
                    <td style="padding:4px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="password" id="aim_api_key" class="form-control" style="flex:1 1 220px; min-width:0;" placeholder="Paste API key">
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" id="aim_show_key" onchange="$('#aim_api_key').attr('type', this.checked ? 'text':'password')"> Show</label>
                            <span id="aim_key_status" class="text-secondary" style="font-size:12px;"></span>
                        </div>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">
                            Stored in <code>plugindata/fpp-AImode/settings.json</code> per-provider with 0600. Never logged. Leave blank for Ollama.
                            <span id="aim_key_hint" class="text-warning" style="margin-left:8px;"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Model:</b></td>
                    <td style="padding:4px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="aim_model_select" class="form-select" style="flex:1 1 160px; min-width:0;"></select>
                            <input type="text" id="aim_model" class="form-control" style="flex:1 1 140px; min-width:0;" placeholder="custom model">
                            <button type="button" class="buttons" id="aim_refresh_models" onclick="aimConfig.fetchModels(true)" title="Fetch available models from provider">↻ Refresh</button>
                            <span id="aim_models_status" class="text-secondary" style="font-size:12px;"></span>
                        </div>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">Pick a fetched model or type a custom deployment/model ID.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Base URL (optional):</b></td>
                    <td style="padding:4px;">
                        <input type="text" id="aim_base_url" class="form-control" placeholder="Leave empty for default">
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">
                            Default: <code id="aim_default_base"></code> | Use for Azure endpoint, Ollama host (<code>http://&lt;ollama-ip&gt;:11434</code>), or proxy.
                        </div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding:4px;">
                        <input type="button" class="buttons" value="Save This Provider" onclick="aimConfig.saveProvider();">
                        <input type="button" class="buttons" value="Test This Provider" onclick="aimConfig.testProvider();">
                        <span id="aim_save_result" style="margin-left:8px;"></span>
                    </td>
                </tr>
                <tr><td></td><td><div id="aim_test_result" style="margin-top:4px;"></div></td></tr>
            </table>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>Global Settings</legend>
        <div class="p-3">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:4px; width:180px;"><b>Temperature:</b></td>
                    <td style="padding:4px;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="range" id="aim_temp" class="form-range" min="0" max="2" step="0.1" value="<?php echo htmlspecialchars($aimSettings['temperature']); ?>" style="max-width:14rem; flex:1 1 8rem;">
                            <span id="aim_temp_val" class="fw-semibold"><?php echo htmlspecialchars($aimSettings['temperature']); ?></span>
                            <span class="text-secondary small">0 = precise, 2 = creative</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Max Tokens:</b></td>
                    <td style="padding:4px;">
                        <input type="number" id="aim_max_tokens" class="form-control d-inline-block" min="64" max="16384" step="64" value="<?php echo (int)$aimSettings['max_tokens']; ?>" style="max-width:7rem; width:auto; display:inline-block;">
                        <span class="text-secondary" style="font-size:12px; margin-left:8px;">Per reply (64–16384). Default 2048.</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>System Prompt:</b></td>
                    <td style="padding:4px;">
                        <textarea id="aim_system_prompt" rows="5" class="form-control" style="width:100%; max-width:100%; font-family:monospace; font-size:12px;" placeholder="Leave empty for default FPP assistant prompt"><?php echo htmlspecialchars($aimSettings['system_prompt']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Include FPP Context:</b></td>
                    <td style="padding:4px;"><label><input type="checkbox" id="aim_ctx" <?php echo !empty($aimSettings['include_fpp_context'])?'checked':''; ?>> Send live FPP status/playlists/settings with each prompt</label></td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>History:</b></td>
                    <td style="padding:4px;"><label><input type="checkbox" id="aim_hist" <?php echo !empty($aimSettings['history_enabled'])?'checked':''; ?>> Keep conversation history (last 40 turns)</label></td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Execution:</b></td>
                    <td style="padding:4px;">
                        <label><input type="checkbox" id="aim_dryrun" <?php echo !empty($aimSettings['dry_run'])?'checked':''; ?>> Dry-run (propose but never execute)</label>
                        <label style="margin-left:16px;"><input type="checkbox" id="aim_auto" <?php echo !empty($aimSettings['auto_approve'])?'checked':''; ?>> Auto-approve tool calls</label>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">Auto-approve on by default — uncheck to require manual Approve.</div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding:4px;">
                        <input type="button" class="buttons" value="Save Global Settings" onclick="aimConfig.saveGlobal();">
                        <span id="aim_global_result" style="margin-left:8px;"></span>
                    </td>
                </tr>
            </table>
            </div>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>Quick Links</legend>
        <div class="p-3">
            <a href="plugin.php?plugin=fpp-AImode&page=assistant.php" class="buttons">✎ Open Assistant</a>
            <a href="plugin.php?plugin=fpp-AImode&page=status.php" class="buttons">View Status</a>
            <a href="plugin.php?plugin=fpp-AImode&page=help.php" class="buttons">Help</a>
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
        var def = aimConfig._defaultProvider;
        Object.keys(aimProviders).forEach(function(k){
            var p = aimConfig._providers[k] || {api_key:'',model:'',base_url:''};
            var configured = (p.api_key && p.api_key.length>0) || k==='ollama';
            var isDefault = k===def;
            var row = $('<tr>').addClass(isDefault?'table-info':'');
            row.append($('<td>').html('<b>'+aimProviders[k].label+'</b> ('+k+')' + (isDefault?' <span class="badge bg-primary">default</span>':'')));
            row.append($('<td>').html(configured ? '<span class="text-success">✓</span> ' + (k==='ollama' ? 'local' : 'key set') : '<span class="text-secondary">—</span>'));
            row.append($('<td>').text(p.model || aimProviders[k].models[0] || ''));
            row.append($('<td>').text(isDefault?'★':'' ));
            tbody.append(row);
        });
    },
    loadProvider: function(prov){
        var p = aimConfig._providers[prov] || {api_key:'',model:'',base_url:''};
        var meta = aimProviders[prov] || {models:[], defaultBase:''};
        $('#aim_api_key').val(p.api_key || '');
        $('#aim_model').val(p.model || meta.models[0] || '');
        $('#aim_base_url').val(p.base_url || '');
        aimConfig.populateModels();
        $('#aim_key_status').text(p.api_key ? '✓ key set' : (prov==='ollama'?'local':'—'));
        setTimeout(function(){ aimConfig.fetchModels(false); }, 400);
    },
    populateModels: function(fetched){
        var prov = $('#aim_provider').val();
        var meta = aimProviders[prov] || {models:[], defaultBase:''};
        var list = fetched || meta.models || [];
        if (fetched && fetched.length) aimConfig._fetchedModels[prov] = fetched;
        else if (aimConfig._fetchedModels[prov]) list = aimConfig._fetchedModels[prov];
        var sel = $('#aim_model_select').empty();
        (list||[]).forEach(function(m){ sel.append($('<option>',{value:m,text:m})); });
        var cur = $('#aim_model').val();
        if (cur && list.indexOf(cur)===-1 && cur.indexOf('/')===-1) {
            // keep custom but warn if really mismatched
            sel.append($('<option>',{value:cur,text:cur+' (custom)'}));
        } else if (cur && list.indexOf(cur)===-1) {
            sel.append($('<option>',{value:cur,text:cur+' (custom)'}));
        }
        if (list.indexOf(cur)!==-1) sel.val(cur);
        else if (list.length && !cur) { sel.val(list[0]); $('#aim_model').val(list[0]); }
        else if (cur) sel.val(cur);
        $('#aim_default_base').text(meta.defaultBase || '');
        if (fetched) {
            $('#aim_models_status').html('<span class="text-success">✓ '+fetched.length+' models</span>');
            setTimeout(function(){ $('#aim_models_status').text(''); }, 4000);
        }
        var hint=''; if(prov==='openai') hint='sk-…'; else if(prov==='anthropic') hint='sk-ant-…'; else if(prov==='google') hint='AIza…'; else if(prov==='grok') hint='xai-…'; else if(prov==='openrouter') hint='sk-or-…'; else if(prov==='ollama') hint='no key needed'; else if(prov==='azure') hint='Azure API key';
        $('#aim_key_hint').text(hint ? 'Expected: '+hint : '');
        if(prov==='ollama') $('#aim_api_key').attr('placeholder','No key needed for Ollama'); else $('#aim_api_key').attr('placeholder','Paste your API key');
    },
    fetchModels: function(manual){
        var prov = $('#aim_provider').val();
        var key = $('#aim_api_key').val();
        var base = $('#aim_base_url').val();
        if (prov!=='ollama' && !key) {
            if(manual) $.jGrowl('Enter API key for '+prov+' to fetch models',{themeState:'warning'});
            $('#aim_models_status').html('<span class="text-warning">Need API key</span>');
            return;
        }
        $('#aim_models_status').html('<span class="text-secondary">Fetching…</span>');
        $('#aim_refresh_models').prop('disabled',true);
        $.ajax({
            url:'api/plugin/fpp-AImode/models',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({provider: prov, api_key: key, base_url: base}),
            dataType:'json',
            success:function(r){
                if(r.success && r.models && r.models.length){
                    aimConfig.populateModels(r.models);
                    if(manual) $.jGrowl('Fetched '+r.models.length+' models for '+prov,{themeState:'success'});
                } else {
                    $('#aim_models_status').html('<span class="text-danger">'+(r.error||'Failed')+'</span>');
                    if(manual) $.jGrowl(r.error||'Failed to fetch models',{themeState:'error'});
                }
            },
            error:function(xhr){
                var m='Could not fetch models'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){}
                $('#aim_models_status').html('<span class="text-danger">'+m+'</span>');
                if(manual) $.jGrowl(m,{themeState:'error'});
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
