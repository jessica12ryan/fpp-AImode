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
    .aim-voice-bar { flex-direction: column; align-items: stretch !important; }
}
.aim-chat { display:flex; flex-direction:column; gap:12px; }
.aim-messages { border:1px solid var(--bs-border-color,#dee2e6); border-radius:6px; padding:12px; min-height:320px; max-height:520px; overflow-y:auto; background: var(--bs-body-bg,#fff); }
.aim-msg { margin-bottom:12px; padding:8px 10px; border-radius:6px; max-width:92%; word-wrap:break-word; }
.aim-msg-user { background:var(--bs-primary); color:var(--bs-white); align-self:flex-end; margin-left:auto; }
.aim-msg-assistant { background:var(--bs-tertiary-bg); color:var(--bs-body-color); border:1px solid var(--bs-border-color); }
.aim-msg-system { background:var(--bs-warning-bg-subtle); color:var(--bs-warning-text-emphasis); border:1px solid var(--bs-warning-border-subtle); font-size:12px; }
.aim-msg-meta { font-size:11px; opacity:0.7; margin-top:4px; }
.aim-chat-input { display:flex; gap:8px; align-items:flex-end; }
.aim-chat-input textarea { flex:1; min-height:56px; resize:vertical; }
.aim-tool-card { border:1px solid var(--bs-border-color,#dee2e6); border-radius:6px; padding:8px 10px; margin-top:8px; background:var(--bs-body-bg,#fff); }
.aim-tool-card pre { margin:4px 0 6px 0; white-space:pre-wrap; word-break:break-all; font-size:11px; background:var(--bs-tertiary-bg,#f8f9fa); padding:6px; border-radius:4px; }
.aim-examples { display:flex; flex-wrap:wrap; gap:6px; }
.aim-examples button { font-size:12px; padding:4px 8px; }
.aim-voice-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px; padding:8px; border:1px dashed var(--bs-border-color,#dee2e6); border-radius:6px; background: var(--bs-tertiary-bg,#f8f9fa); }
.btn-mic { background:var(--bs-success); color:var(--bs-white); border:1px solid var(--bs-success); padding:6px 14px; border-radius:20px; cursor:pointer; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; }
.btn-mic:hover { filter:brightness(0.9); }
.btn-mic.listening { background:var(--bs-danger); border-color:var(--bs-danger); animation: aimPulse 1.2s infinite; }
.btn-mic:disabled { opacity:0.5; cursor:not-allowed; animation:none; }
@keyframes aimPulse { 0%{ box-shadow:0 0 0 0 rgba(var(--bs-danger-rgb),0.5);} 70%{ box-shadow:0 0 0 8px rgba(var(--bs-danger-rgb),0);} 100%{ box-shadow:0 0 0 0 rgba(var(--bs-danger-rgb),0);} }
.aim-interim { color:var(--bs-secondary-color); font-style:italic; font-size:12px; min-height:1.2em; }
.aim-thinking { display:none; align-items:center; gap:8px; font-size:12px; color:var(--bs-secondary-color); padding:6px 10px; border:1px dashed var(--bs-border-color); border-radius:6px; background:var(--bs-tertiary-bg); margin-top:6px; }
.aim-thinking.show { display:flex; }
.aim-thinking .spinner { width:14px; height:14px; border:2px solid var(--bs-secondary-color); border-top-color:transparent; border-radius:50%; animation:spin 0.8s linear infinite; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg);} }
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
        <legend>AI Assistant — Configure FPP with Natural Language</legend>
        <div class="p-3 aim-chat">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <span class="text-secondary" style="font-size:12px;">Provider:</span> <b id="aim-cur-provider"><?php echo htmlspecialchars($aimSettings['provider']); ?></b>
                    <span class="text-secondary" style="font-size:12px; margin-left:8px;">Model:</span> <b id="aim-cur-model"><?php echo htmlspecialchars($aimSettings['model']); ?></b>
                    <span id="aim-mode-badges" style="margin-left:10px;">
                        <?php if (!empty($aimSettings['dry_run'])) echo '<span class="badge bg-warning text-dark">DRY-RUN</span> '; ?>
                        <?php if (!empty($aimSettings['auto_approve'])) echo '<span class="badge bg-info">AUTO-APPROVE</span>'; else echo '<span class="badge bg-secondary">Approve required</span>'; ?>
                    </span>
                </div>
                <div>
                    <input type="button" class="buttons" value="↻ Reload History" onclick="aimChat.loadHistory();">
                    <input type="button" class="buttons" value="Clear Chat" onclick="aimChat.clearHistory();">
                </div>
            </div>

            <div class="aim-examples">
                <span class="text-secondary" style="font-size:12px; align-self:center;">Try:</span>
                <button class="buttons" onclick="aimChat.fillExample(this)">What playlists do I have?</button>
                <button class="buttons" onclick="aimChat.fillExample(this)">Create a playlist called Test with random shuffle</button>
                <button class="buttons" onclick="aimChat.fillExample(this)">Schedule my Christmas playlist nightly 6pm-11pm</button>
                <button class="buttons" onclick="aimChat.fillExample(this)">Set volume to 75 and show system info</button>
                <button class="buttons" onclick="aimChat.fillExample(this)">Help me set up channel outputs for E1.31</button>
            </div>

            <div id="aimMessages" class="aim-messages">
                <div class="aim-msg aim-msg-system">Loading history…</div>
            </div>
            <div id="aimThinking" class="aim-thinking"><div class="spinner"></div><span id="aimThinkingText">Thinking…</span><span id="aimThinkingDots"></span></div>

            <div class="aim-chat-input">
                <textarea id="aimPrompt" placeholder="Describe what you want to configure… e.g. 'Create a playlist named Halloween with Spooky.fseq and schedule it Oct 31 18:00-23:00'" rows="2" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); aimChat.send();}"></textarea>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <input type="button" class="buttons" id="aimSendBtn" value="Send ▶" onclick="aimChat.send();" style="padding:10px 18px; font-weight:600;">
                    <span id="aimChatStatus" class="text-secondary" style="font-size:11px; text-align:center;"></span>
                </div>
            </div>
            <div class="aim-voice-bar" id="aimVoiceBar">
                <button type="button" class="btn-mic" id="aimMicBtn" onclick="aimVoice.toggle();" title="Start/stop voice input">🎤 Voice Input</button>
                <select id="aimVoiceLang" title="Voice language" class="form-select d-inline-block" style="max-width:10rem; width:auto; font-size:12px;">
                    <option value="">Auto (browser)</option>
                    <option value="en-US">English (US)</option>
                    <option value="en-GB">English (UK)</option>
                    <option value="en-AU">English (AU)</option>
                    <option value="es-ES">Español (ES)</option>
                    <option value="es-US">Español (US)</option>
                    <option value="fr-FR">Français</option>
                    <option value="de-DE">Deutsch</option>
                    <option value="it-IT">Italiano</option>
                    <option value="pt-BR">Português (BR)</option>
                    <option value="nl-NL">Nederlands</option>
                    <option value="ja-JP">日本語</option>
                    <option value="ko-KR">한국어</option>
                    <option value="zh-CN">中文 (简体)</option>
                </select>
                <label style="font-size:12px; display:inline-flex; align-items:center; gap:4px; margin:0;">
                    <input type="checkbox" id="aimVoiceAutoSend"> Auto-send
                </label>
                <span id="aimVoiceStatus" class="text-secondary" style="font-size:12px; flex:1;"></span>
                <span id="aimVoiceSupport" class="text-secondary" style="font-size:11px;"></span>
            </div>
            <div class="aim-interim" id="aimVoiceInterim"></div>
            <div class="text-secondary" style="font-size:11px;">
                <b>Enter</b> to send, <b>Shift+Enter</b> for newline. <b>🎤 Voice</b> transcribes locally via your browser (Web Speech API) — only the text transcript is sent to the AI provider when you Send. Tool calls are shown below each reply — click <b>Approve</b> to execute. In dry-run nothing is executed.
                &nbsp;<a href="plugin.php?plugin=fpp-AImode&page=help.php">Help</a>
            </div>
        </div>
    </fieldset>
</div>

<script>
var aimChat = {
    busy:false,
    fillExample: function(btn){ $('#aimPrompt').val($(btn).text()).focus(); },
    esc: function(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },
    md: function(s){
        // minimal markdown: code blocks + inline code + bold
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
        // Use data attributes to avoid inline JSON escaping issues
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
        aimChat.busy=true;
        $('#aimSendBtn').prop('disabled',true).val('…');
        $('#aimChatStatus').text('Thinking…');
        $('#aimThinking').addClass('show');
        $('#aimThinkingText').text('Thinking… contacting ' + ($('#aim-cur-provider').text()||'AI'));
        var _start = Date.now();
        var _dots = 0;
        aimChat._thinkTimer = setInterval(function(){
            _dots = (_dots+1)%4;
            $('#aimThinkingDots').text('.'.repeat(_dots));
            var sec = Math.floor((Date.now()-_start)/1000);
            $('#aimThinkingText').text('Thinking… contacting ' + ($('#aim-cur-provider').text()||'AI') + ' ('+sec+'s)');
            if(sec>8) $('#aimChatStatus').text('Thinking… '+sec+'s — multi-step tasks (list→create) may take 10-20s');
        }, 500);
        aimChat.addMsg('user', prompt);
        $('#aimPrompt').val('');

        $.ajax({
            url:'api/plugin/fpp-AImode/chat',
            type:'POST',
            contentType:'application/json',
            data: JSON.stringify({prompt: prompt}),
            dataType:'json',
            success: function(r){
                if(!r.success){
                    aimChat.addMsg('system','Error: '+(r.error||'Unknown'), '');
                    $('#aimChatStatus').text('Error');
                    return;
                }
                var meta = (r.provider||'')+' / '+(r.model||'') + (r.dry_run?' · dry-run':'') + (r.auto_approve?' · auto':'');
                aimChat.addMsg('assistant', r.reply || '(no reply)', meta);
                // tool calls
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
                // also show executed if any (when auto)
                $('#aimChatStatus').text(r.tool_calls && r.tool_calls.length ? r.tool_calls.length+' tool(s) proposed' : 'Done');
                // update badges live
                aimChat.refreshStatus();
            },
            error: function(xhr){
                var m='Could not reach API';
                try{ var j=JSON.parse(xhr.responseText); if(j.error) m=j.error; }catch(e){}
                aimChat.addMsg('system','Request failed: '+m,'');
                $('#aimChatStatus').text('Failed');
                clearInterval(aimChat._thinkTimer);
                $('#aimThinking').removeClass('show');
            },
            complete: function(){
                clearInterval(aimChat._thinkTimer);
                $('#aimThinking').removeClass('show');
                aimChat.busy=false;
                $('#aimSendBtn').prop('disabled',false).val('Send ▶');
                setTimeout(function(){ $('#aimChatStatus').text(''); }, 4000);
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
        $('#aimMessages').html('<div class="aim-msg aim-msg-system">Loading…</div>');
        $.ajax({
            url:'api/plugin/fpp-AImode/history',
            type:'GET',
            dataType:'json',
            success: function(r){
                $('#aimMessages').empty();
                if(!r.entries || !r.entries.length){
                    $('#aimMessages').html('<div class="aim-msg aim-msg-system">No history yet. Try one of the examples above.</div>');
                    return;
                }
                r.entries.forEach(function(e){
                    var role = e.role==='user'?'user':'assistant';
                    var meta = e.ts || '';
                    if(e.meta && e.meta.tool_calls && e.meta.tool_calls.length) meta += ' · ' + e.meta.tool_calls.length + ' tool(s)';
                    aimChat.addMsg(role, e.content, meta);
                });
            },
            error: function(){ $('#aimMessages').html('<div class="aim-msg aim-msg-system">Could not load history.</div>'); }
        });
    },
    clearHistory: function(){
        if(!confirm('Clear conversation history? This cannot be undone.')) return;
        $.ajax({
            url:'api/plugin/fpp-AImode/history/clear',
            type:'POST',
            contentType:'application/json',
            data:'{}',
            dataType:'json',
            success: function(){
                $('#aimMessages').html('<div class="aim-msg aim-msg-system">History cleared.</div>');
                $.jGrowl('History cleared',{themeState:'success'});
            },
            error: function(){ $.jGrowl('Could not clear history',{themeState:'error'}); }
        });
    },
    refreshStatus: function(){
        $.ajax({
            url:'api/plugin/fpp-AImode/status',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(d.settings){
                    $('#aim-cur-provider').text(d.settings.provider || '');
                    // model is redacted; keep current
                }
            }
        });
    }
};

var aimVoice = {
    recognition: null,
    listening: false,
    supported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
    interim: '',
    finalText: '',
    init: function(){
        var sup = $('#aimVoiceSupport');
        var btn = $('#aimMicBtn');
        if(!aimVoice.supported){
            sup.text('Voice not supported in this browser — try Chrome/Edge on desktop.');
            btn.prop('disabled', true).attr('title','Web Speech API not available');
            $('#aimVoiceStatus').text('');
            return;
        }
        sup.text('Browser speech ready');
        // Restore prefs from localStorage
        try{
            var lang = localStorage.getItem('fppAImode_voiceLang');
            if(lang) $('#aimVoiceLang').val(lang);
            var auto = localStorage.getItem('fppAImode_voiceAutoSend');
            if(auto === '1') $('#aimVoiceAutoSend').prop('checked', true);
        }catch(e){}
        $('#aimVoiceLang').on('change', function(){
            try{ localStorage.setItem('fppAImode_voiceLang', $(this).val()); }catch(e){}
        });
        $('#aimVoiceAutoSend').on('change', function(){
            try{ localStorage.setItem('fppAImode_voiceAutoSend', this.checked ? '1' : '0'); }catch(e){}
        });
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        aimVoice.recognition = new SR();
        aimVoice.recognition.interimResults = true;
        aimVoice.recognition.continuous = false;
        aimVoice.recognition.maxAlternatives = 1;
        aimVoice.recognition.onstart = function(){
            aimVoice.listening = true;
            btn.addClass('listening').text('⏹ Stop');
            $('#aimVoiceStatus').html('<span class="text-danger">● Listening… speak now</span>');
            $('#aimVoiceInterim').text('');
            aimVoice.finalText = '';
        };
        aimVoice.recognition.onresult = function(event){
            var interim = '';
            var final = '';
            for(var i=event.resultIndex; i<event.results.length; i++){
                var res = event.results[i];
                if(res.isFinal) final += res[0].transcript;
                else interim += res[0].transcript;
            }
            if(interim) $('#aimVoiceInterim').text('… ' + interim);
            if(final){
                $('#aimVoiceInterim').text('');
                var ta = $('#aimPrompt');
                var cur = ta.val();
                // Append with space if needed
                var toInsert = final.trim();
                if(cur && !cur.endsWith(' ') && !cur.endsWith('\n')) toInsert = ' ' + toInsert;
                ta.val(cur + toInsert);
                ta.focus();
                aimVoice.finalText += (aimVoice.finalText ? ' ' : '') + final.trim();
            }
        };
        aimVoice.recognition.onerror = function(event){
            var msg = event.error || 'unknown';
            if(msg === 'not-allowed' || msg === 'permission-denied'){
                $('#aimVoiceStatus').html('<span class="text-danger">Microphone permission denied — allow mic access in browser.</span>');
                $.jGrowl('Microphone permission denied',{themeState:'error'});
            } else if(msg === 'no-speech'){
                $('#aimVoiceStatus').html('<span class="text-warning">No speech detected — try again.</span>');
            } else if(msg === 'audio-capture'){
                $('#aimVoiceStatus').html('<span class="text-danger">No microphone found.</span>');
            } else {
                $('#aimVoiceStatus').html('<span class="text-danger">Voice error: '+aimChat.esc(msg)+'</span>');
            }
            $('#aimVoiceInterim').text('');
        };
        aimVoice.recognition.onend = function(){
            var wasListening = aimVoice.listening;
            aimVoice.listening = false;
            btn.removeClass('listening').text('🎤 Voice Input');
            $('#aimVoiceInterim').text('');
            if(wasListening){
                if(aimVoice.finalText){
                    $('#aimVoiceStatus').html('<span class="text-success">✓ Captured: “'+aimChat.esc(aimVoice.finalText.slice(0,80))+'”</span>');
                    if($('#aimVoiceAutoSend').is(':checked')){
                        setTimeout(function(){ aimChat.send(); }, 250);
                    }
                } else {
                    $('#aimVoiceStatus').html('<span class="text-secondary">Stopped — no transcript. Try again.</span>');
                }
                setTimeout(function(){ $('#aimVoiceStatus').text(''); aimVoice.finalText=''; }, 4000);
            }
        };
    },
    toggle: function(){
        if(!aimVoice.supported){
            $.jGrowl('Voice input not supported in this browser',{themeState:'error'});
            return;
        }
        if(aimVoice.listening){
            try{ aimVoice.recognition.stop(); }catch(e){}
            return;
        }
        var lang = $('#aimVoiceLang').val();
        if(!lang) lang = navigator.language || 'en-US';
        aimVoice.recognition.lang = lang;
        aimVoice.finalText = '';
        try{ aimVoice.recognition.start(); }catch(e){
            $('#aimVoiceStatus').html('<span class="text-danger">Could not start voice: '+aimChat.esc(e.message||String(e))+'</span>');
        }
    }
};

$(document).ready(function(){
    aimChat.loadHistory();
    // poll status badges occasionally
    setInterval(aimChat.refreshStatus, 15000);
    aimVoice.init();
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
