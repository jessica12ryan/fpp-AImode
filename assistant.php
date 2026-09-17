<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## assistant.php — Chat UI for FPP AI Mode                 ##
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
$aimDefaults = ['provider'=>'openai','api_key'=>'','model'=>'gpt-4o-mini','dry_run'=>0,'auto_approve'=>1];
$aimSettings = $aimDefaults;
if (file_exists($aimSettingsFile)) {
    $j = json_decode(@file_get_contents($aimSettingsFile), true);
    if (is_array($j)) $aimSettings = array_merge($aimDefaults, $j);
}
$aimProviders = [
    'openai' => ['label'=>'OpenAI','defaultBase'=>'https://api.openai.com/v1','models'=>['gpt-4o','gpt-4o-mini','gpt-4-turbo','o1','o1-mini','o3-mini','o3','gpt-5','gpt-5-mini','gpt-5-nano','gpt-5.6-sol','gpt-5.6-terra','gpt-5.6-luna','gpt-4.1','gpt-4.1-mini']],
    'anthropic' => ['label'=>'Anthropic (Claude)','defaultBase'=>'https://api.anthropic.com','models'=>['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-3-opus-20240229','claude-3-haiku-20240307','claude-sonnet-4-6','claude-opus-4-8','claude-opus-5','claude-sonnet-5','claude-haiku-4-5']],
    'google' => ['label'=>'Google Gemini','defaultBase'=>'https://generativelanguage.googleapis.com','models'=>['gemini-3.6-flash','gemini-3.8-flash','gemini-3.7-flash','gemini-3.5-flash','gemini-3.5-flash-lite','gemini-2.5-flash','gemini-2.5-pro','gemini-1.5-flash','gemini-1.5-pro']],
    'mistral' => ['label'=>'Mistral','defaultBase'=>'https://api.mistral.ai/v1','models'=>['mistral-large-latest','mistral-small-latest','mistral-nemo','open-mistral-7b','mistral-large-2407','codestral-latest']],
    'grok' => ['label'=>'Grok (xAI)','defaultBase'=>'https://api.x.ai/v1','models'=>['grok-3','grok-3-mini','grok-3-fast','grok-2','grok-beta','grok-2-mini']],
    'openrouter' => ['label'=>'OpenRouter','defaultBase'=>'https://openrouter.ai/api/v1','models'=>['openai/gpt-4o','openai/gpt-4o-mini','openai/gpt-5','openai/gpt-5-mini','anthropic/claude-3.5-sonnet','anthropic/claude-opus-4','google/gemini-2.5-flash','google/gemini-3.6-flash','mistralai/mistral-large','x-ai/grok-3']],
    'ollama' => ['label'=>'Ollama (Local)','defaultBase'=>'http://localhost:11434','models'=>['llama3.1','llama3.3','qwen2.5','qwen3','mistral','gemma2','gemma3','phi3','phi4','codellama','deepseek-r1']],
    'azure' => ['label'=>'Azure OpenAI','defaultBase'=>'https://{your-endpoint}.openai.azure.com','models'=>['gpt-4o','gpt-4o-mini','gpt-35-turbo','gpt-5','gpt-5-mini','o3','o4-mini']],
];
$_fppUiLevel = (int)($GLOBALS['settings']['uiLevel'] ?? $settings['uiLevel'] ?? 0);
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
$hasKey = !empty($aimSettings['api_key']) || $aimSettings['provider']==='ollama';
?>
<style>
@media only screen and (max-width: 480px) {
    fieldset { padding: 5px !important; }
    .aim-chat-input { flex-direction: column; }
    .aim-chat-input textarea { min-height: 80px; }
    .aim-provider-bar { flex-direction: column; align-items: stretch !important; }
}
.aim-chat { display:flex; flex-direction:column; gap:14px; }
.aim-welcome { background: linear-gradient(135deg, var(--bs-primary-bg-subtle,#e7f1ff) 0%, var(--bs-tertiary-bg,#f8f9fa) 100%); border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; padding:14px 16px; display:flex; gap:12px; align-items:center; }
.aim-welcome-icon { font-size:28px; line-height:1; }
.aim-welcome h4 { margin:0 0 4px 0; font-size:16px; font-weight:700; }
.aim-welcome p { margin:0; font-size:12px; color:var(--bs-secondary-color); line-height:1.4; }
.aim-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:10px 12px; background:var(--bs-tertiary-bg,#f8f9fa); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; }
.aim-header-badges .badge { font-size:11px; padding:5px 7px; }
.aim-conv-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; padding:10px 12px; border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; background:var(--bs-body-bg,#fff); box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.aim-conv-bar label { font-size:12px; font-weight:600; color:var(--bs-secondary-color); white-space:nowrap; }
.aim-examples { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:8px 12px; background:var(--bs-body-bg,#fff); border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; }
.aim-examples .aim-chip { font-size:12px; padding:6px 12px; border-radius:20px; border:1px solid var(--bs-border-color,#dee2e6); background:var(--bs-tertiary-bg,#f8f9fa); cursor:pointer; transition:.15s; }
.aim-examples .aim-chip:hover { background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); transform: translateY(-1px); }
.aim-messages { border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; padding:14px; min-height:340px; max-height:560px; overflow-y:auto; background: var(--bs-body-bg,#fff); box-shadow: inset 0 1px 3px rgba(0,0,0,.03); }
.aim-msg { margin-bottom:14px; padding:10px 14px; border-radius:12px; max-width:88%; word-wrap:break-word; line-height:1.5; position:relative; }
.aim-msg-user { background:var(--bs-primary); color:#fff; align-self:flex-end; margin-left:auto; border-bottom-right-radius:4px; box-shadow: 0 2px 4px rgba(0,0,0,.08); }
.aim-msg-assistant { background:var(--bs-tertiary-bg); color:var(--bs-body-color); border:1px solid var(--bs-border-color); border-bottom-left-radius:4px; }
.aim-msg-system { background:var(--bs-warning-bg-subtle); color:var(--bs-warning-text-emphasis); border:1px solid var(--bs-warning-border-subtle); font-size:12px; border-radius:8px; }
.aim-msg-meta { font-size:11px; opacity:0.65; margin-top:6px; display:flex; gap:8px; align-items:center; }
.aim-chat-input { display:flex; gap:10px; align-items:flex-end; background:var(--bs-tertiary-bg,#f8f9fa); padding:10px; border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; }
.aim-chat-input textarea { flex:1; min-height:62px; resize:vertical; border-radius:8px; border:1px solid var(--bs-border-color,#dee2e6); padding:10px 12px; font-size:14px; }
.aim-chat-input textarea:focus { border-color:var(--bs-primary); box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb),.15); outline:none; }
.aim-tool-card { border:1px solid var(--bs-border-color,#dee2e6); border-radius:8px; padding:10px 12px; margin-top:10px; background:var(--bs-body-bg,#fff); box-shadow: 0 1px 3px rgba(0,0,0,.05); }
.aim-tool-card pre { margin:6px 0; white-space:pre-wrap; word-break:break-all; font-size:11px; background:var(--bs-tertiary-bg,#f8f9fa); padding:8px; border-radius:6px; border:1px solid var(--bs-border-color,#eee); }
.aim-provider-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:4px; padding:10px 12px; border:1px dashed var(--bs-primary-border-subtle,#a0c4ff); border-radius:8px; background: var(--bs-primary-bg-subtle,#f0f7ff); }
.aim-provider-bar label { font-size:12px; font-weight:600; color:var(--bs-primary-text-emphasis); }
.aim-thinking { display:none; align-items:center; gap:10px; font-size:12px; color:var(--bs-secondary-color); padding:10px 14px; border:1px dashed var(--bs-border-color); border-radius:8px; background:var(--bs-tertiary-bg); margin-top:6px; }
.aim-thinking.show { display:flex; }
.aim-thinking .spinner { width:16px; height:16px; border:2.5px solid var(--bs-secondary-color); border-top-color:transparent; border-radius:50%; animation:spin 0.8s linear infinite; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg);} }
.aim-help { font-size:11px; color:var(--bs-secondary-color); background:var(--bs-tertiary-bg,#f8f9fa); padding:8px 12px; border-radius:8px; border:1px solid var(--bs-border-color,#eee); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <?php if (!$hasKey): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom:12px;">
        <b>No API key configured</b> for <code><?php echo htmlspecialchars($aimSettings['provider']); ?></code>. Go to <a href="plugin.php?plugin=fpp-AImode&page=config.php">Config</a> to add your token before chatting.
        <?php if ($aimSettings['provider']==='ollama'): ?> Ollama normally needs no key — ensure the Base URL is correct.<?php endif; ?>
    </div>
    <?php endif; ?>

    <fieldset class="border p-3">
        <legend>✨ AI Assistant — Talk to your FPP</legend>
        <div class="p-3 aim-chat">
            <div class="aim-welcome">
                <div class="aim-welcome-icon">🎄</div>
                <div style="flex:1;">
                    <h4>Configure your show with plain English</h4>
                    <p>Ask to create playlists, schedules, set volume, check status, or troubleshoot outputs. The AI will propose precise FPP actions for you to approve.</p>
                </div>
                <a href="plugin.php?plugin=fpp-AImode&page=config.php" class="buttons" style="white-space:nowrap;">⚙️ Configure</a>
            </div>

            <div id="aimConvBar" class="aim-conv-bar">
                <label for="aimConvSelect">💬 Conversation</label>
                <select id="aimConvSelect" class="form-select" style="flex:1 1 180px; max-width:280px; font-size:13px; font-weight:500;" onchange="aimConv.switch(this.value)"></select>
                <button type="button" class="buttons" onclick="aimConv.create()" title="Start a new chat" style="background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); font-weight:600;">＋ New</button>
                <button type="button" class="buttons btn-sm" onclick="aimConv.rename()" title="Rename this conversation">✎</button>
                <button type="button" class="buttons btn-sm" onclick="aimConv.delete()" title="Delete this conversation">🗑️</button>
                <div style="display:flex; gap:6px; margin-left:auto; align-items:center;">
                    <button type="button" class="buttons btn-sm" onclick="aimChat.loadHistory();" title="Reload current conversation">↻ Reload</button>
                    <button type="button" class="buttons btn-sm" onclick="aimChat.clearHistory();" title="Clear this chat">🧹 Clear</button>
                </div>
                <span id="aimConvStatus" class="text-secondary" style="font-size:11px;"></span>
            </div>

            <div class="aim-examples">
                <span class="text-secondary" style="font-size:12px; font-weight:600; white-space:nowrap;">⚡ Try:</span>
                <button class="aim-chip" onclick="aimChat.fillExample(this)">📋 What playlists do I have?</button>
                <button class="aim-chip" onclick="aimChat.fillExample(this)">🎵 Create playlist Test with shuffle</button>
                <button class="aim-chip" onclick="aimChat.fillExample(this)">📅 Schedule Christmas 6pm–11pm</button>
                <button class="aim-chip" onclick="aimChat.fillExample(this)">🔊 Set volume to 75 + system info</button>
                <button class="aim-chip" onclick="aimChat.fillExample(this)">🔌 Help with E1.31 outputs</button>
            </div>

            <div id="aimMessages" class="aim-messages">
                <div class="aim-msg aim-msg-system">Loading history…</div>
            </div>
            <div id="aimThinking" class="aim-thinking"><div class="spinner"></div><span id="aimThinkingText">Thinking…</span><span id="aimThinkingDots"></span></div>

            <div class="aim-chat-input">
                <textarea id="aimPrompt" placeholder="💬 Describe what you want…  Try 'Create a playlist named Halloween with Spooky.fseq and schedule it Oct 31 6pm–11pm'" rows="2" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); aimChat.send();}"></textarea>
                <div style="display:flex; flex-direction:column; gap:6px; min-width:96px;">
                    <button type="button" class="buttons" id="aimSendBtn" onclick="aimChat.send();" style="padding:12px 20px; font-weight:700; background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); border-radius:8px; font-size:14px;">Send ▶</button>
                    <span id="aimChatStatus" class="text-secondary" style="font-size:11px; text-align:center; min-height:14px;"></span>
                </div>
            </div>
            <div class="aim-provider-bar" id="aimProviderBar">
                <label for="aimConvProvider">🤖 Model for this chat</label>
                <select id="aimConvProvider" class="form-select" style="flex:0 1 150px; max-width:165px; font-size:12px; font-weight:500;" onchange="aimConv.onProviderChange()"></select>
                <select id="aimConvModel" class="form-select" style="flex:1 1 160px; max-width:260px; font-size:12px;" onchange="aimConv.onModelChange()"></select>
                <button type="button" class="buttons" onclick="aimConv.saveProvider()" title="Save this model for this conversation only" style="font-weight:600;">💾 Save</button>
                <button type="button" class="buttons" onclick="aimConv.fetchModels(true)" title="Refresh model list from provider">↻</button>
                <span id="aim-mode-badges" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                    <?php if (!empty($aimSettings['dry_run'])) echo '<span class="badge bg-warning text-dark" title="Proposed actions are not executed">DRY-RUN</span>'; ?>
                    <?php if (!empty($aimSettings['auto_approve'])) echo '<span class="badge bg-success" title="Tools run automatically">✓ Auto-approve</span>'; else echo '<span class="badge bg-secondary" title="You must approve each tool">Manual approve</span>'; ?>
                </span>
                <span id="aimConvProviderStatus" class="text-secondary" style="font-size:11px;"></span>
            </div>
            <div class="aim-help">
                💡 <b>Tip:</b> <b>Enter</b> to send, <b>Shift+Enter</b> for newline. Each reply may propose FPP actions — click <b>✓ Approve</b> to run them. Dry-run is off by default; toggle in <a href="plugin.php?plugin=fpp-AImode&page=config.php">Config</a>.
                · <a href="plugin.php?plugin=fpp-AImode&page=help.php">📖 Help & examples</a>
            </div>
        </div>
    </fieldset>
</div>

<script>
var aimProviders = <?php echo json_encode($aimProviders); ?>;
var aimChat = {
    busy:false,
    currentConvId: null,
    pollTimer: null,
    fillExample: function(btn){ $('#aimPrompt').val($(btn).text()).focus(); },
    esc: function(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
    md: function(s){
        if(!s) return '';
        var h = aimChat.esc(s);
        h = h.replace(/```([\s\S]*?)```/g, function(_,code){ return '<pre style="white-space:pre-wrap;background:var(--bs-tertiary-bg,#f8f9fa);padding:8px;border-radius:4px;overflow:auto;">'+code+'</pre>'; });
        h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
        h = h.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>');
        h = h.replace(/\n/g, '<br>');
        return h;
    },
    addMsg: function(role, content, meta){
        var cls = role==='user' ? 'aim-msg-user' : (role==='system' ? 'aim-msg-system' : 'aim-msg-assistant');
        var html = '<div class="aim-msg '+cls+'"><div>'+aimChat.md(content)+'</div>';
        if(meta) html += '<div class="aim-msg-meta">'+aimChat.esc(meta)+'</div>';
        html += '</div>';
        $('#aimMessages').append(html);
        aimChat.scrollBottom();
    },
    scrollBottom: function(){ var el=document.getElementById('aimMessages'); if(el) el.scrollTop = el.scrollHeight; },
    renderTool: function(tc, idx){
        var name = tc.name || 'unknown';
        var args = tc.arguments || {};
        var argStr = JSON.stringify(args, null, 2);
        var id = 'tool_'+Date.now()+'_'+idx;
        var encoded = encodeURIComponent(JSON.stringify(args));
        var html = '<div class="aim-tool-card" id="'+id+'" data-name="'+aimChat.esc(name)+'" data-args="'+aimChat.esc(encoded)+'">';
        html += '<b>🔧 Tool:</b> <code>'+aimChat.esc(name)+'</code>';
        html += '<pre>'+aimChat.esc(argStr)+'</pre>';
        html += '<div style="display:flex; gap:6px; flex-wrap:wrap;">';
        html += '<button class="buttons" onclick="aimChat.executeFromCard(\''+id+'\')">✓ Approve &amp; Execute</button>';
        html += '<button class="buttons" onclick="$(this).closest(\'.aim-tool-card\').fadeOut()">Dismiss</button>';
        html += '</div><div class="aim-tool-result" style="margin-top:6px; font-size:12px;"></div></div>';
        return html;
    },
    send: function(){
        if(aimChat.busy) return;
        var prompt = $('#aimPrompt').val().trim();
        if(!prompt){ $.jGrowl('Type a prompt first',{themeState:'error'}); return; }
        var convId = aimConv.currentId || aimChat.currentConvId;
        if(!convId && aimConv.currentId) convId = aimConv.currentId;
        aimChat.busy=true;
        $('#aimSendBtn').prop('disabled',true).val('…');
        $('#aimChatStatus').text('Thinking…');
        $('#aimThinking').addClass('show');
        var _provLabel = $('#aimConvProvider').val() || $('#aimConvProvider option:selected').text() || 'AI';
        $('#aimThinkingText').text('Thinking… contacting ' + _provLabel);
        var _start = Date.now();
        var _dots = 0;
        aimChat._thinkTimer = setInterval(function(){
            _dots = (_dots+1)%4;
            $('#aimThinkingDots').text('.'.repeat(_dots));
            var sec = Math.floor((Date.now()-_start)/1000);
            var _p = $('#aimConvProvider').val() || $('#aimConvProvider option:selected').text() || 'AI';
            $('#aimThinkingText').text('Thinking… contacting ' + _p + ' ('+sec+'s)');
            if(sec>8) $('#aimChatStatus').text('Thinking… '+sec+'s — multi-step tasks (list→create) may take 10-20s');
        }, 500);
        aimChat.addMsg('user', prompt);
        $('#aimPrompt').val('');
        // Update conversation status locally to thinking for immediate UI
        if(convId) aimConv.setLocalStatus(convId, 'thinking');

        $.ajax({
            url:'api/plugin/fpp-AImode/chat',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({prompt: prompt, conversationId: convId}),
            dataType:'json',
            success: function(r){
                if(!r.success){
                    aimChat.addMsg('system','Error: '+(r.error||'Unknown'), '');
                    $('#aimChatStatus').text('Error');
                    if(r.conversationId) aimConv.currentId = r.conversationId;
                    return;
                }
                // Update current conversation id from response (may be new)
                if(r.conversationId) {
                    aimChat.currentConvId = r.conversationId;
                    aimConv.currentId = r.conversationId;
                    localStorage.setItem('fppAImode_activeConv', r.conversationId);
                    aimConv.refreshList();
                }
                var meta = (r.provider||'')+' / '+(r.model||'') + (r.dry_run?' · dry-run':'') + (r.auto_approve?' · auto':'');
                aimChat.addMsg('assistant', r.reply || '(no reply)', meta);
                if(r.tool_calls && r.tool_calls.length){
                    var container = $('<div style="margin-bottom:12px;">');
                    if(r.dry_run) container.append('<div class="text-warning" style="font-size:12px; margin-bottom:4px;">Dry-run enabled — tools not executed. Disable in Config to allow execution.</div>');
                    if(r.auto_approve && r.executed && r.executed.length){
                        r.executed.forEach(function(ex){
                            var ok = ex.result && ex.result.success;
                            container.append('<div class="aim-tool-card" style="border-left:3px solid var('+(ok?'--bs-success':'--bs-danger')+')"><b>🔧 '+(ex.call.name||'')+'</b> <span class="text-secondary" style="font-size:11px;">(auto-executed)</span><pre>'+aimChat.esc(JSON.stringify(ex.call.arguments||{},null,2))+'</pre><div style="font-size:12px; color:var('+(ok?'--bs-success':'--bs-danger')+')">'+aimChat.esc(ok ? '✓ '+(JSON.stringify(ex.result.result||ex.result).slice(0,600)) : '✗ '+(ex.result.error||'failed'))+'</div></div>');
                        });
                    } else {
                        r.tool_calls.forEach(function(tc,i){
                            container.append(aimChat.renderTool(tc,i));
                        });
                        if(r.tool_calls.length>1){
                            container.append('<div style="margin-top:6px;"><button class="buttons" onclick="aimChat.approveAll(this)">✓ Approve All ('+r.tool_calls.length+')</button></div>');
                        }
                    }
                    $('#aimMessages').append(container);
                    aimChat.scrollBottom();
                }
                $('#aimChatStatus').text(r.tool_calls && r.tool_calls.length ? r.tool_calls.length+' tool(s) proposed' : 'Done');
                aimChat.refreshStatus();
                // Refresh conversation to show updated history
                if(r.conversationId) aimConv.load(r.conversationId, true);
                else aimConv.refreshList();
            },
            error: function(xhr){
                var m='Could not reach API';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) m=j.error; if(j.conversationId) aimConv.currentId = j.conversationId; }catch(e){}
                aimChat.addMsg('system','Request failed: '+m,'');
                $('#aimChatStatus').text('Failed');
                clearInterval(aimChat._thinkTimer);
                $('#aimThinking').removeClass('show');
                // If request failed but server is still thinking in background, start polling
                if(aimConv.currentId) aimConv.startPolling(aimConv.currentId);
            },
            complete: function(){
                clearInterval(aimChat._thinkTimer);
                $('#aimThinking').removeClass('show');
                aimChat.busy=false;
                $('#aimSendBtn').prop('disabled',false).val('Send ▶');
                setTimeout(function(){ $('#aimChatStatus').text(''); }, 4000);
                // Ensure polling stops if we got final result, otherwise polling will handle
                aimConv.stopPolling();
                // If conversation is still thinking (background), start polling
                setTimeout(function(){ aimConv.checkAndPoll(); }, 500);
            }
        });
    },
    executeFromCard: function(cardId){
        var $card = $('#'+cardId);
        var name = $card.attr('data-name') || '';
        var enc = $card.attr('data-args') || '';
        var args = {};
        try { args = JSON.parse(decodeURIComponent(enc)); } catch(e) { args = {}; }
        return aimChat.execute(name, args, cardId);
    },
    execute: function(name, args, cardId){
        var $card = $('#'+cardId);
        var $res = $card.find('.aim-tool-result');
        $res.html('<span class="text-warning">Executing…</span>');
        if(typeof args==='string'){ try{ args=JSON.parse(args); }catch(e){} }
        $.ajax({
            url:'api/plugin/fpp-AImode/execute',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({name:name, arguments:args}),
            dataType:'json',
            success: function(r){
                if(r.dry_run){
                    $res.html('<span class="text-warning">Dry-run: not executed.</span> <span class="text-secondary">Disable dry-run in Config to apply.</span>');
                    return;
                }
                if(r.success){
                    $res.html('<span class="text-success"><b>✓ Success</b></span> <pre style="margin-top:4px; max-height:200px; overflow:auto;">'+aimChat.esc(JSON.stringify(r.result||r, null, 2).slice(0,2000))+'</pre>');
                    $card.css('border-left','3px solid var(--bs-success)');
                    $.jGrowl('Tool '+name+' executed',{themeState:'success'});
                } else {
                    $res.html('<span class="text-danger"><b>✗ Failed:</b> '+aimChat.esc(r.error||'Unknown')+'</span>');
                    $card.css('border-left','3px solid var(--bs-danger)');
                }
            },
            error: function(xhr){
                var m='API error';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) m=j.error; }catch(e){}
                $res.html('<span class="text-danger">'+aimChat.esc(m)+'</span>');
            }
        });
    },
    approveAll: function(btn){
        var $wrap = $(btn).closest('div').parent();
        $wrap.find('.aim-tool-card button:contains("Approve")').each(function(){ $(this).click(); });
        $(btn).prop('disabled',true).text('Approved');
    },
    loadHistory: function(){
        // Legacy: now loads active conversation
        if(aimConv.currentId) aimConv.load(aimConv.currentId);
        else aimConv.refreshList();
    },
    clearHistory: function(){
        if(!aimConv.currentId) return;
        if(!confirm('Clear this conversation? This cannot be undone.')) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(aimConv.currentId),
            type:'DELETE',
            dataType:'json',
            success: function(){
                $('#aimMessages').html('<div class="aim-msg aim-msg-system">Conversation cleared.</div>');
                aimConv.refreshList();
                $.jGrowl('Conversation deleted',{themeState:'success'});
                aimConv.create();
            },
            error: function(){ $.jGrowl('Could not clear',{themeState:'error'}); }
        });
    },
    refreshStatus: function(){
        $.ajax({
            url:'api/plugin/fpp-AImode/status',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(d.settings){
                    // Keep bottom-bar badges in sync (dry-run / auto-approve)
                    var badges = '';
                    if(d.settings.dry_run) badges += '<span class="badge bg-warning text-dark" title="Proposed actions are not executed">DRY-RUN</span> ';
                    if(d.settings.auto_approve) badges += '<span class="badge bg-success" title="Tools run automatically">✓ Auto-approve</span>';
                    else badges += '<span class="badge bg-secondary" title="You must approve each tool">Manual approve</span>';
                    $('#aim-mode-badges').html(badges);
                }
            }
        });
    }
};

var aimConv = {
    currentId: null,
    pollTimer: null,
    list: [],
    refreshList: function(){
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations',
            type:'GET',
            dataType:'json',
            success: function(r){
                if(!r.success) {
                    $('#aimMessages').html('<div class="aim-msg aim-msg-system">Could not load conversations: '+aimChat.esc(r.error||'Unknown')+'</div>');
                    return;
                }
                aimConv.list = r.conversations || [];
                var sel = $('#aimConvSelect').empty();
                if(aimConv.list.length===0){
                    sel.append($('<option>',{value:'',text:'— No conversations —'}));
                    $('#aimMessages').html('<div class="aim-msg aim-msg-system">No conversations yet. Click <b>+ New</b> or send a prompt below to start one (a new conversation will be created automatically).</div>');
                    aimConv.currentId = null;
                    aimChat.currentConvId = null;
                    localStorage.removeItem('fppAImode_activeConv');
                    aimConv.updateStatusBar();
                    // Show default provider/model in bottom bar even with no conversations
                    aimConv.ensureProviders(function(provs){
                        var defProv = aimConv._defaultProvider || 'openai';
                        var curSel = $('#aimConvProvider').val();
                        if(!curSel || curSel !== defProv) $('#aimConvProvider').val(defProv);
                        var provToShow = $('#aimConvProvider').val() || defProv;
                        var defModel = (provs[provToShow] && provs[provToShow].model) ? provs[provToShow].model : '';
                        if(!defModel && typeof aimProviders !== 'undefined' && aimProviders[provToShow]) defModel = aimProviders[provToShow].models[0] || '';
                        if(defModel) aimConv._currentModel = defModel;
                        aimConv.fetchModelsForProvider(provToShow);
                        // Ensure correct provider option selected after populate
                        $('#aimConvProvider').val(provToShow);
                    });
                    return;
                }
                r.conversations.forEach(function(c){
                    var label = c.title + (c.status==='thinking' ? ' ● thinking' : '') + ' ('+c.messageCount+')';
                    sel.append($('<option>',{value:c.id,text:label}));
                });
                // Restore active
                var active = localStorage.getItem('fppAImode_activeConv') || (r.conversations[0] && r.conversations[0].id);
                if(active && r.conversations.some(function(c){return c.id===active;})){
                    sel.val(active);
                    aimConv.currentId = active;
                    aimChat.currentConvId = active;
                    aimConv.load(active);
                } else if(r.conversations[0]){
                    sel.val(r.conversations[0].id);
                    aimConv.currentId = r.conversations[0].id;
                    aimChat.currentConvId = r.conversations[0].id;
                    aimConv.load(r.conversations[0].id);
                }
                aimConv.updateStatusBar();
            },
            error: function(xhr){
                var msg='Could not load conversations';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) msg=j.error; }catch(e){}
                $('#aimMessages').html('<div class="aim-msg aim-msg-system">Error: '+aimChat.esc(msg)+'<br>Try <b>+ New</b> to create a conversation.</div>');
            }
        });
    },
    load: function(id, silent){
        if(!id) return;
        aimConv.currentId = id;
        aimChat.currentConvId = id;
        localStorage.setItem('fppAImode_activeConv', id);
        $('#aimConvSelect').val(id);
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(id),
            type:'GET',
            dataType:'json',
            success: function(r){
                if(!r.success || !r.conversation) return;
                var conv = r.conversation;
                // Update per-conversation provider bar (bottom bar with voice/auto-send)
                aimConv._currentModel = conv.model || '';
                var prov = conv.provider || aimConv._defaultProvider || 'openai';
                aimConv.populateProviderSelect();
                aimConv.ensureProviders(function(){
                    $('#aimConvProvider').val(prov);
                    aimConv.fetchModelsForProvider(prov);
                    // Keep model selection after fetch populates
                    setTimeout(function(){ if(conv.model) $('#aimConvModel').val(conv.model); }, 600);
                });
                $('#aimMessages').empty();
                if(!conv.messages || !conv.messages.length){
                    $('#aimMessages').html('<div class="aim-msg aim-msg-system">No messages yet. Try one of the examples above.</div>');
                } else {
                    conv.messages.forEach(function(e){
                        var role = e.role==='user'?'user':'assistant';
                        var meta = e.ts || '';
                        if(e.meta && e.meta.tool_calls && e.meta.tool_calls.length) meta += ' · ' + e.meta.tool_calls.length + ' tool(s)';
                        if(e.meta && e.meta.provider) meta += ' · ' + e.meta.provider;
                        aimChat.addMsg(role, e.content, meta);
                        // Render tool calls if present in meta
                        if(e.meta && e.meta.tool_calls && e.meta.tool_calls.length && role==='assistant'){
                            var container = $('<div style="margin-bottom:8px;">');
                            // Show executed if present
                            if(e.meta.executed && e.meta.executed.length){
                                e.meta.executed.forEach(function(ex){
                                    var ok = ex.result && ex.result.success;
                                    container.append('<div class="aim-tool-card" style="border-left:3px solid var('+(ok?'--bs-success':'--bs-danger')+')"><b>🔧 '+(ex.call.name||'')+'</b> <span class="text-secondary" style="font-size:11px;">(auto-executed)</span><pre>'+aimChat.esc(JSON.stringify(ex.call.arguments||{},null,2))+'</pre></div>');
                                });
                            }
                            $('#aimMessages').append(container);
                        }
                    });
                }
                aimConv.updateStatusBar();
                // If conversation is thinking, start polling
                if(conv.status==='thinking' && !silent){
                    aimConv.startPolling(id);
                    $('#aimThinking').addClass('show');
                    $('#aimThinkingText').text('Resuming thinking…');
                } else if(!silent){
                    $('#aimThinking').removeClass('show');
                }
            }
        });
    },
    switch: function(id){ aimConv.load(id); },
    create: function(){
        var _d = new Date();
        var _pad = function(n){ return n<10?'0'+n:n; };
        var _h = _d.getHours(); var _ampm = _h>=12?'PM':'AM'; _h = _h%12; if(_h===0) _h=12;
        var _title = 'Chat ' + _d.getFullYear() + '-' + _pad(_d.getMonth()+1) + '-' + _pad(_d.getDate()) + ', ' + _h + ':' + _pad(_d.getMinutes()) + ':' + _pad(_d.getSeconds()) + ' ' + _ampm;
        var title = prompt('New conversation title:', _title);
        if(title===null) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({title: title}),
            dataType:'json',
            success: function(r){
                if(r.success && r.conversation){
                    aimConv.refreshList();
                    setTimeout(function(){ aimConv.load(r.conversation.id); }, 300);
                }
            }
        });
    },
    delete: function(){
        var id = aimConv.currentId;
        if(!id) return;
        if(!confirm('Delete conversation "'+($('#aimConvSelect option:selected').text())+'"?')) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(id)+'/delete',
            type:'POST',
            dataType:'json',
            success: function(){
                localStorage.removeItem('fppAImode_activeConv');
                aimConv.refreshList();
                $.jGrowl('Conversation deleted',{themeState:'success'});
            },
            error: function(xhr){ var m='Could not delete'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){} $.jGrowl(m,{themeState:'error'}); }
        });
    },
    rename: function(){
        var id = aimConv.currentId;
        if(!id) return;
        var curTitle = $('#aimConvSelect option:selected').text().split(' (')[0].replace(' ● thinking','').trim();
        var title = prompt('Rename conversation:', curTitle);
        if(!title || title===curTitle) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(id)+'/update',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({title: title}),
            dataType:'json',
            success: function(){ aimConv.refreshList(); },
            error: function(xhr){ var m='Could not rename'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){} $.jGrowl(m,{themeState:'error'}); }
        });
    },
    setLocalStatus: function(id, status){
        $('#aimConvStatus').text(status==='thinking' ? '● thinking…' : '');
        // Update select label
        var opt = $('#aimConvSelect option[value="'+id+'"]');
        if(opt.length){
            var base = opt.text().replace(' ● thinking','').replace(' (',' (');
            if(status==='thinking' && opt.text().indexOf('thinking')===-1) opt.text(opt.text().replace(' (',' ● thinking ('));
        }
    },
    updateStatusBar: function(){
        var conv = aimConv.list.find(function(c){return c.id===aimConv.currentId;});
        if(conv && conv.status==='thinking'){
            $('#aimConvStatus').html('<span class="text-warning">● thinking…</span>');
            $('#aimThinking').addClass('show');
        } else {
            $('#aimConvStatus').text('');
            $('#aimThinking').removeClass('show');
        }
    },
    startPolling: function(id){
        aimConv.stopPolling();
        aimConv.pollTimer = setInterval(function(){ aimConv.poll(id); }, 2000);
    },
    stopPolling: function(){ if(aimConv.pollTimer) clearInterval(aimConv.pollTimer); aimConv.pollTimer=null; },
    poll: function(id){
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(id),
            type:'GET',
            dataType:'json',
            success: function(r){
                if(!r.success || !r.conversation) return;
                var conv = r.conversation;
                // If status changed to idle and messages grew, reload
                var localCount = $('#aimMessages .aim-msg').length;
                // Simple: if message count increased or status idle, reload
                if(conv.messages && conv.messages.length > localCount){
                    aimConv.load(id, true);
                }
                if(conv.status !== 'thinking'){
                    aimConv.stopPolling();
                    $('#aimThinking').removeClass('show');
                    aimConv.refreshList();
                } else {
                    $('#aimConvStatus').html('<span class="text-warning">● thinking… '+conv.messages.length+' msgs</span>');
                }
            }
        });
    },
    checkAndPoll: function(){
        if(!aimConv.currentId) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(aimConv.currentId),
            type:'GET',
            dataType:'json',
            success: function(r){
                if(r.success && r.conversation && r.conversation.status==='thinking'){
                    aimConv.startPolling(r.conversation.id);
                }
            }
        });
    },
    // Per-conversation provider/model
    _providerCache: null,
    ensureProviders: function(cb){
        if(aimConv._providerCache) { if(cb) cb(aimConv._providerCache); return; }
        $.ajax({
            url:'api/plugin/fpp-AImode/status',
            type:'GET',
            dataType:'json',
            timeout: 8000,
            success: function(r){
                var provs = {};
                if(r.providers) provs = r.providers;
                else if(r.provider_meta) provs[r.settings.provider] = r.provider_meta;
                aimConv._providerCache = provs;
                aimConv._defaultProvider = r.defaultProvider || r.settings.defaultProvider || r.settings.provider;
                if(cb) cb(provs);
            },
            error: function(){
                // Fallback: at least set default from global if available
                if(!aimConv._defaultProvider) aimConv._defaultProvider = 'openai';
                if(cb) cb(aimConv._providerCache || {});
            }
        });
    },
    populateProviderSelect: function(){
        var doPopulate = function(provs){
            var sel = $('#aimConvProvider');
            if(!sel.length) return;
            var curVal = sel.val();
            // Prefer server default if curVal empty
            if(!curVal && aimConv._defaultProvider) curVal = aimConv._defaultProvider;
            sel.empty();
            var list = (typeof aimProviders !== 'undefined' && aimProviders) ? aimProviders : (window.aimProviders || {});
            var keys = Object.keys(list);
            if(keys.length===0) keys = provs ? Object.keys(provs) : [];
            if(keys.length===0) keys = ['openai','anthropic','google','mistral','grok','openrouter','ollama','azure'];
            keys.forEach(function(k){
                var label = (list[k] && list[k].label) ? list[k].label : k;
                sel.append($('<option>',{value:k,text:label+' ('+k+')'}));
            });
            if(curVal && sel.find('option[value="'+curVal+'"]').length) sel.val(curVal);
            else if(aimConv._defaultProvider && sel.find('option[value="'+aimConv._defaultProvider+'"]').length) sel.val(aimConv._defaultProvider);
        };
        // Try sync populate with local aimProviders if available
        if(typeof aimProviders !== 'undefined' && aimProviders && Object.keys(aimProviders).length){
            doPopulate(aimConv._providerCache || {});
        }
        aimConv.ensureProviders(function(provs){
            doPopulate(provs);
            // If model dropdown still empty and no conversation, trigger default fetch (ready handler also does this; safe to call twice)
            if(!aimConv.currentId && $('#aimConvModel option').length <= 1){
                var dp = aimConv._defaultProvider || $('#aimConvProvider').val() || 'openai';
                if(dp) aimConv.fetchModelsForProvider(dp);
            }
        });
    },
    onProviderChange: function(){
        var prov = $('#aimConvProvider').val();
        aimConv.fetchModelsForProvider(prov);
    },
    onModelChange: function(){
        $('#aimConvProviderStatus').html('<span class="text-warning">• unsaved</span>');
    },
    fetchModels: function(manual){
        var prov = $('#aimConvProvider').val();
        return aimConv.fetchModelsForProvider(prov, manual);
    },
    fetchModelsForProvider: function(prov, manual){
        if(!prov) prov = $('#aimConvProvider').val();
        if(!prov) return;
        var sel = $('#aimConvModel');
        // Immediate preset population so dropdown is never empty (shows instantly even if live fetch stalls)
        var presetImmediate = (typeof aimProviders !== 'undefined' && aimProviders && aimProviders[prov] && aimProviders[prov].models) ? aimProviders[prov].models : ((window.aimProviders && window.aimProviders[prov] && window.aimProviders[prov].models) ? window.aimProviders[prov].models : []);
        sel.empty();
        if(presetImmediate && presetImmediate.length){
            presetImmediate.forEach(function(m){ sel.append($('<option>',{value:m,text:m})); });
            var curImm = aimConv._currentModel || '';
            if(curImm && presetImmediate.indexOf(curImm)===-1) sel.append($('<option>',{value:curImm,text:curImm+' (custom)'}));
            if(curImm) sel.val(curImm);
            $('#aimConvProviderStatus').html('<span class="text-secondary">Preset '+presetImmediate.length+' — fetching live…</span>');
        } else {
            sel.append($('<option>',{value:'',text:'Loading…'}));
        }
        var provs = aimConv._providerCache || {};
        var cfg = provs[prov] || {};
        var rawKey = cfg.api_key || '';
        if(rawKey && rawKey.indexOf('***')===0) rawKey = '';
        var payload = {provider: prov, api_key: rawKey, base_url: cfg.base_url || ''};
        $.ajax({
            url:'api/plugin/fpp-AImode/models',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify(payload),
            dataType:'json',
            timeout: 10000,
            success: function(r){
                if(r.success && r.models && r.models.length){
                    sel.empty();
                    r.models.forEach(function(m){ sel.append($('<option>',{value:m,text:m})); });
                    var cur2 = aimConv._currentModel || '';
                    if(cur2 && r.models.indexOf(cur2)===-1) sel.append($('<option>',{value:cur2,text:cur2+' (custom)'}));
                    if(cur2) sel.val(cur2);
                    $('#aimConvProviderStatus').html('<span class="text-success">✓ '+r.models.length+' models (live)</span>');
                    if(manual) $.jGrowl('Fetched '+r.models.length+' models for '+prov,{themeState:'success'});
                    setTimeout(function(){ $('#aimConvProviderStatus').text(''); }, 3000);
                } else {
                    // Keep preset already shown, just update status
                    if(!presetImmediate || !presetImmediate.length){
                        sel.empty().append($('<option>',{value:'',text:'No models'}));
                        $('#aimConvProviderStatus').html('<span class="text-danger">'+(r.error||'Failed')+'</span>');
                    } else {
                        $('#aimConvProviderStatus').html('<span class="text-secondary">Preset '+presetImmediate.length+' ('+(r.error||'no live update')+')</span>');
                    }
                    if(manual) $.jGrowl(r.error||'Using preset list',{themeState:'warning'});
                }
            },
            error: function(xhr, status){
                var m='Could not fetch live'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){ if(status==='timeout') m='timeout — using preset'; }
                // Preset already shown, keep it
                if(presetImmediate && presetImmediate.length){
                    $('#aimConvProviderStatus').html('<span class="text-warning">Preset '+presetImmediate.length+' ('+m+')</span>');
                } else {
                    sel.empty().append($('<option>',{value:'',text:'Error'}));
                    $('#aimConvProviderStatus').html('<span class="text-danger">'+m+'</span>');
                }
                if(manual) $.jGrowl(m,{themeState:'error'});
            }
        });
    },
    saveProvider: function(){
        var prov = $('#aimConvProvider').val();
        var model = $('#aimConvModel').val() || $('#aimConvModel').find('option:selected').val();
        if(!prov || !model){ $.jGrowl('Provider and model required',{themeState:'error'}); return; }
        var id = aimConv.currentId;
        if(!id){ $.jGrowl('No conversation selected',{themeState:'error'}); return; }
        $('#aimConvProviderStatus').html('<span class="text-secondary">Saving…</span>');
        $.ajax({
            url:'api/plugin/fpp-AImode/conversations/'+encodeURIComponent(id)+'/update',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({provider: prov, model: model}),
            dataType:'json',
            success: function(r){
                if(r.success){
                    $('#aimConvProviderStatus').html('<span class="text-success">✓ Saved</span>');
                    $.jGrowl('Provider saved for this conversation',{themeState:'success'});
                    aimConv.refreshList();
                    setTimeout(function(){ $('#aimConvProviderStatus').text(''); }, 3000);
                } else {
                    $('#aimConvProviderStatus').html('<span class="text-danger">'+(r.error||'Failed')+'</span>');
                }
            },
            error: function(xhr){
                var m='Could not save'; try{var j=JSON.parse(xhr.responseText); if(j.error) m=j.error;}catch(e){}
                $('#aimConvProviderStatus').html('<span class="text-danger">'+m+'</span>');
            }
        });
    }
};

$(document).ready(function(){
    // Populate provider select immediately with preset, then default to server's defaultProvider/model
    aimConv.populateProviderSelect();
    // Pre-select default provider/model before conversations load so bar is not stuck on openai
    aimConv.ensureProviders(function(provs){
        var defProv = aimConv._defaultProvider || 'openai';
        // Only set if no conversation yet selected (avoids overriding load())
        if(!aimConv.currentId){
            var sel = $('#aimConvProvider');
            // If populate hadn't set a value yet, default to defProv
            if(!sel.val() || sel.find('option[value="'+defProv+'"]').length){
                // set to default provider
                sel.val(defProv);
            }
            var curProv = sel.val() || defProv;
            var defModel = (provs[curProv] && provs[curProv].model) ? provs[curProv].model : '';
            if(!defModel && typeof aimProviders !== 'undefined' && aimProviders[curProv]) defModel = aimProviders[curProv].models[0] || '';
            if(defModel && !aimConv._currentModel) aimConv._currentModel = defModel;
            // Fetch models for the default provider so dropdown shows immediately (even before any conversation)
            if($('#aimConvModel option').length <= 1){
                aimConv.fetchModelsForProvider(curProv);
            }
        }
    });
    aimConv.refreshList();
    setInterval(aimChat.refreshStatus, 15000);
    setInterval(function(){ aimConv.checkAndPoll(); }, 5000);
    document.addEventListener('visibilitychange', function(){ if(!document.hidden) aimConv.checkAndPoll(); });
    window.addEventListener('pageshow', function(){ aimConv.checkAndPoll(); });
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
