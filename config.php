<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## config.php                                              ##
 * #############################################################
 */
$aimPluginDir = __DIR__;
// GUIDELINE §14.11 / §5 — credentials in plugindata, not plugin config. Resolve plugindata dir the FPP way.
$aimMediaDir = $settings['mediaDirectory'] ?? $GLOBALS['settings']['mediaDirectory'] ?? getenv('MEDIADIR') ?: null;
if (!$aimMediaDir) $aimMediaDir = $aimPluginDir; // dev fallback
$aimDataDir = rtrim($aimMediaDir, '/') . '/plugindata/fpp-AImode';
if ($aimMediaDir === $aimPluginDir) $aimDataDir = $aimPluginDir . '/config'; // keep legacy dev path
$aimSettingsFile = $aimDataDir . '/settings.json';
$aimLegacySettingsFile = $aimPluginDir . '/config/settings.json';
if (!file_exists($aimSettingsFile) && file_exists($aimLegacySettingsFile)) $aimSettingsFile = $aimLegacySettingsFile;

// Defaults mirrored from api.php for rendering without loading api
$aimDefaults = [
    'provider' => 'openai',
    'api_key' => '',
    'model' => 'gpt-4o-mini',
    'base_url' => '',
    'system_prompt' => '',
    'temperature' => 0.7,
    'max_tokens' => 2048,
    'auto_approve' => 0,
    'dry_run' => 0,
    'include_fpp_context' => 1,
    'history_enabled' => 1,
];
$aimProviders = [
    'openai' => ['label'=>'OpenAI','defaultBase'=>'https://api.openai.com/v1','models'=>['gpt-4o','gpt-4o-mini','gpt-4-turbo','o1','o1-mini','o3-mini']],
    'anthropic' => ['label'=>'Anthropic (Claude)','defaultBase'=>'https://api.anthropic.com','models'=>['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-3-opus-20240229']],
    'google' => ['label'=>'Google Gemini','defaultBase'=>'https://generativelanguage.googleapis.com','models'=>['gemini-1.5-flash','gemini-1.5-pro','gemini-2.0-flash']],
    'mistral' => ['label'=>'Mistral','defaultBase'=>'https://api.mistral.ai/v1','models'=>['mistral-large-latest','mistral-small-latest']],
    'grok' => ['label'=>'Grok (xAI)','defaultBase'=>'https://api.x.ai/v1','models'=>['grok-2','grok-beta','grok-2-mini']],
    'openrouter' => ['label'=>'OpenRouter','defaultBase'=>'https://openrouter.ai/api/v1','models'=>['openai/gpt-4o','openai/gpt-4o-mini','anthropic/claude-3.5-sonnet','google/gemini-flash-1.5']],
    'ollama' => ['label'=>'Ollama (Local)','defaultBase'=>'http://localhost:11434','models'=>['llama3.1','qwen2.5','mistral','gemma2']],
    'azure' => ['label'=>'Azure OpenAI','defaultBase'=>'https://{your-endpoint}.openai.azure.com','models'=>['gpt-4o','gpt-4o-mini','gpt-35-turbo']],
];
$aimSettings = $aimDefaults;
if (file_exists($aimSettingsFile)) {
    $j = json_decode(@file_get_contents($aimSettingsFile), true);
    if (is_array($j)) $aimSettings = array_merge($aimDefaults, $j);
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
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>AI Mode — Provider &amp; Model</legend>
        <div class="p-3">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:4px;"><b>Provider:</b></td>
                    <td style="padding:4px;">
                        <select id="aim_provider" class="form-select" style="max-width:100%;">
                            <?php foreach ($aimProviders as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $aimSettings['provider']===$k?'selected':''; ?>><?php echo htmlspecialchars($v['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-secondary" style="font-size:12px;">Switch to use another AI. Keys are per-provider.</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>API Key / Token:</b></td>
                    <td style="padding:4px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="password" id="aim_api_key" class="form-control" style="flex:1 1 200px; min-width:0;" placeholder="Paste your API key" value="<?php echo htmlspecialchars($aimSettings['api_key']); ?>">
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" id="aim_show_key" onchange="$('#aim_api_key').attr('type', this.checked ? 'text':'password')"> Show</label>
                        </div>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">
                            Stored in <code>plugindata/fpp-AImode/settings.json</code> with 0600 (plugindata, not config, per privacy). Never logged. Leave blank for Ollama.
                            <span id="aim_key_hint" class="text-warning" style="margin-left:8px;"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Model:</b></td>
                    <td style="padding:4px;">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="aim_model_select" class="form-select" style="flex:1 1 160px; min-width:0;"></select>
                            <input type="text" id="aim_model" class="form-control" style="flex:1 1 140px; min-width:0;" placeholder="custom model" value="<?php echo htmlspecialchars($aimSettings['model']); ?>">
                        </div>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">Pick a preset or type a custom deployment/model ID (Azure deployment name, OpenRouter slug, Ollama tag).</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Base URL (optional):</b></td>
                    <td style="padding:4px;">
                        <input type="text" id="aim_base_url" class="form-control" placeholder="Leave empty for default" value="<?php echo htmlspecialchars($aimSettings['base_url']); ?>">
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">
                            Default: <code id="aim_default_base"></code> &nbsp;|&nbsp; Use for Azure endpoint, Ollama host (<code>http://&lt;ollama-ip&gt;:11434</code>), or proxy.
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Temperature:</b></td>
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
                    <td style="padding:4px;"></td>
                    <td style="padding:4px;">
                        <input type="button" class="buttons" value="Save Settings" onclick="aimConfig.save();">
                        <input type="button" class="buttons" value="Test Connection" onclick="aimConfig.test();">
                        <span id="aim_save_result" style="margin-left:8px;"></span>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td><div id="aim_test_result" style="margin-top:4px;"></div></td>
                </tr>
            </table>
            </div>
        </div>
    </fieldset>

    <br/>

    <fieldset class="border p-3">
        <legend>Behavior</legend>
        <div class="p-3">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <td style="padding:4px;"><b>System Prompt:</b></td>
                    <td style="padding:4px;">
                        <textarea id="aim_system_prompt" rows="6" class="form-control" style="width:100%; max-width:100%; font-family:monospace; font-size:12px;" placeholder="Leave empty for default FPP assistant prompt"><?php echo htmlspecialchars($aimSettings['system_prompt']); ?></textarea>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">Override the default FPP tool-calling prompt. Empty = built-in prompt that knows your playlists/settings.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Include FPP Context:</b></td>
                    <td style="padding:4px;"><label><input type="checkbox" id="aim_ctx" <?php echo !empty($aimSettings['include_fpp_context'])?'checked':''; ?>> Send live FPP status/playlists/settings with each prompt</label> <span class="text-secondary" style="font-size:12px; margin-left:8px;">Recommended — lets the AI see your current show.</span></td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>History:</b></td>
                    <td style="padding:4px;"><label><input type="checkbox" id="aim_hist" <?php echo !empty($aimSettings['history_enabled'])?'checked':''; ?>> Keep conversation history (last 40 turns)</label> <input type="button" class="buttons" value="Clear History" onclick="aimConfig.clearHistory();" style="margin-left:12px;"></td>
                </tr>
                <tr>
                    <td style="padding:4px;"><b>Execution:</b></td>
                    <td style="padding:4px;">
                        <label><input type="checkbox" id="aim_dryrun" <?php echo !empty($aimSettings['dry_run'])?'checked':''; ?>> Dry-run (propose but never execute)</label>
                        <label style="margin-left:16px;"><input type="checkbox" id="aim_auto" <?php echo !empty($aimSettings['auto_approve'])?'checked':''; ?>> Auto-approve tool calls</label>
                        <div class="text-secondary" style="font-size:12px; margin-top:4px;">Auto-approve executes every tool call immediately after the AI reply. Off by default — you approve each call.</div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding:4px;"><input type="button" class="buttons" value="Save Behavior" onclick="aimConfig.save();"></td>
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

var aimConfig = {
    populateModels: function() {
        var prov = $('#aim_provider').val();
        var meta = aimProviders[prov] || {models:[], defaultBase:''};
        var sel = $('#aim_model_select').empty();
        (meta.models||[]).forEach(function(m){
            sel.append($('<option>',{value:m,text:m}));
        });
        // Select matching or first
        var cur = $('#aim_model').val();
        if (cur && meta.models.indexOf(cur) === -1) {
            sel.append($('<option>',{value:cur,text:cur + ' (current)'}));
        }
        if (meta.models.indexOf(cur) !== -1) sel.val(cur);
        else if (meta.models.length) {
            // don't overwrite custom typed value, just highlight
            if (!cur) { sel.val(meta.models[0]); $('#aim_model').val(meta.models[0]); }
            else sel.val(meta.models[0]);
        }
        $('#aim_default_base').text(meta.defaultBase || '');
        // hint for key
        var hint = '';
        if (prov === 'openai') hint = 'sk-…';
        else if (prov === 'anthropic') hint = 'sk-ant-…';
        else if (prov === 'google') hint = 'AIza…';
        else if (prov === 'grok') hint = 'xai-…';
        else if (prov === 'openrouter') hint = 'sk-or-…';
        else if (prov === 'ollama') hint = 'no key needed';
        else if (prov === 'azure') hint = 'Azure API key';
        $('#aim_key_hint').text(hint ? 'Expected: ' + hint : '');
        // Toggle key required
        if (prov === 'ollama') $('#aim_api_key').attr('placeholder','No key needed for Ollama');
        else $('#aim_api_key').attr('placeholder','Paste your API key');
    },
    collect: function() {
        return {
            provider: $('#aim_provider').val(),
            api_key: $('#aim_api_key').val(),
            model: $('#aim_model').val() || $('#aim_model_select').val(),
            base_url: $('#aim_base_url').val(),
            system_prompt: $('#aim_system_prompt').val(),
            temperature: parseFloat($('#aim_temp').val()),
            max_tokens: parseInt($('#aim_max_tokens').val(),10),
            include_fpp_context: $('#aim_ctx').is(':checked') ? 1 : 0,
            history_enabled: $('#aim_hist').is(':checked') ? 1 : 0,
            dry_run: $('#aim_dryrun').is(':checked') ? 1 : 0,
            auto_approve: $('#aim_auto').is(':checked') ? 1 : 0
        };
    },
    save: function() {
        var data = aimConfig.collect();
        if (!data.provider || !data.model) { $.jGrowl('Provider and model are required',{themeState:'error'}); return; }
        if (data.provider !== 'ollama' && !data.api_key) {
            // allow saving without key but warn
            if (!confirm('No API key entered for ' + data.provider + '. Save anyway? You will need a key to chat.')) return;
        }
        $('#aim_save_result').html('<span class="text-warning">Saving…</span>');
        $.ajax({
            url: 'api/plugin/fpp-AImode/save',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function(r){
                if (r.success) {
                    $('#aim_save_result').html('<span class="text-success">Saved!</span>');
                    $.jGrowl('Settings saved',{themeState:'success'});
                } else {
                    $('#aim_save_result').html('<span class="text-danger">'+escHtml(r.error||'Failed')+'</span>');
                }
            },
            error: function(xhr){
                var m='Could not save';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) m=j.error; }catch(e){}
                $('#aim_save_result').html('<span class="text-danger">'+escHtml(m)+'</span>');
            }
        });
    },
    test: function() {
        var data = aimConfig.collect();
        $('#aim_test_result').html('<span class="text-warning">Testing '+escHtml(data.provider)+' / '+escHtml(data.model)+' …</span>');
        $.ajax({
            url: 'api/plugin/fpp-AImode/test',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function(r){
                if (r.success) {
                    $('#aim_test_result').html('<span class="text-success"><b>✓ Connected</b> — reply: '+escHtml(r.reply||'OK')+' <span class="text-secondary">('+escHtml(r.provider||'')+' / '+escHtml(r.model||'')+')</span></span>');
                    $.jGrowl('Test succeeded',{themeState:'success'});
                } else {
                    $('#aim_test_result').html('<span class="text-danger"><b>✗ Failed:</b> '+escHtml(r.error||'Unknown')+'</span>');
                }
            },
            error: function(xhr){
                var m='Could not reach plugin API';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) m=j.error; }catch(e){}
                $('#aim_test_result').html('<span class="text-danger">'+escHtml(m)+'</span>');
            }
        });
    },
    clearHistory: function(){
        if (!confirm('Clear conversation history?')) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/history/clear', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
            success:function(r){ $.jGrowl(r.message||'History cleared',{themeState:'success'}); },
            error:function(){ $.jGrowl('Could not clear history',{themeState:'error'}); }
        });
    }
};

function escHtml(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

$(document).ready(function(){
    aimConfig.populateModels();
    $('#aim_provider').on('change', aimConfig.populateModels);
    $('#aim_model_select').on('change', function(){ $('#aim_model').val($(this).val()); });
    $('#aim_temp').on('input', function(){ $('#aim_temp_val').text($(this).val()); });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
