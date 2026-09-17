<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## status.php                                              ##
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
$aimHistoryFile = $aimDataDir . '/history.json';
$aimLegacyHistoryFile = $aimPluginDir . '/config/history.json';
if (!file_exists($aimHistoryFile) && file_exists($aimLegacyHistoryFile)) $aimHistoryFile = $aimLegacyHistoryFile;
$aimSettings = [];
if (file_exists($aimSettingsFile)) $aimSettings = json_decode(@file_get_contents($aimSettingsFile), true) ?: [];
$_fppUiLevel = (int)($GLOBALS['settings']['uiLevel'] ?? $settings['uiLevel'] ?? 0);
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
.aim-welcome { background: linear-gradient(135deg, var(--bs-primary-bg-subtle,#e7f1ff) 0%, var(--bs-tertiary-bg,#f8f9fa) 100%); border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; padding:16px 18px; display:flex; gap:14px; align-items:center; margin-bottom:16px; }
.aim-welcome-icon { font-size:28px; }
.aim-welcome h4 { margin:0 0 4px 0; font-size:16px; font-weight:700; }
.aim-welcome p { margin:0; font-size:12.5px; color:var(--bs-secondary-color); line-height:1.5; }
.aim-health { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px; }
.aim-health-card { background:var(--bs-body-bg,#fff); border:1px solid var(--bs-border-color,#dee2e6); border-radius:10px; padding:14px; display:flex; gap:12px; align-items:center; }
.aim-health-card .aim-hc-icon { font-size:22px; width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:8px; flex-shrink:0; }
.aim-health-card.ok .aim-hc-icon { background:var(--bs-success-bg-subtle); }
.aim-health-card.warn .aim-hc-icon { background:var(--bs-warning-bg-subtle); }
.aim-health-card.err .aim-hc-icon { background:var(--bs-danger-bg-subtle); }
.aim-health-card h6 { margin:0; font-size:12px; font-weight:700; color:var(--bs-secondary-color); text-transform:uppercase; letter-spacing:.5px; }
.aim-health-card b { font-size:15px; display:block; margin-top:2px; }
.aim-health-card small { font-size:11px; color:var(--bs-secondary-color); }
fieldset { border-radius:10px !important; }
fieldset legend { font-weight:700; font-size:14px; padding:0 8px; }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto; max-width:960px;">
    <div class="aim-welcome">
        <div class="aim-welcome-icon">📊</div>
        <div style="flex:1;">
            <h4>System health at a glance</h4>
            <p>Check your AI provider, FPP connection, and available tools. Everything green? You're ready to chat.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="plugin.php?plugin=fpp-AImode&page=assistant.php" class="buttons" style="background:var(--bs-primary); color:#fff; border-color:var(--bs-primary); font-weight:600;">💬 Open Assistant</a>
            <a href="plugin.php?plugin=fpp-AImode&page=config.php" class="buttons">⚙️ Configure</a>
        </div>
    </div>

    <div class="aim-health" id="aim_health_cards">
        <div class="aim-health-card" id="aim_hc_provider"><div class="aim-hc-icon">🤖</div><div><h6>Provider</h6><b id="aim_hc_provider_val">—</b><small id="aim_hc_provider_sub">Loading…</small></div></div>
        <div class="aim-health-card" id="aim_hc_fpp"><div class="aim-hc-icon">🎄</div><div><h6>FPP</h6><b id="aim_hc_fpp_val">—</b><small id="aim_hc_fpp_sub">Checking…</small></div></div>
        <div class="aim-health-card" id="aim_hc_history"><div class="aim-hc-icon">💬</div><div><h6>Conversations</h6><b id="aim_hc_history_val">—</b><small id="aim_hc_history_sub">History</small></div></div>
        <div class="aim-health-card" id="aim_hc_tools"><div class="aim-hc-icon">🔧</div><div><h6>Tools</h6><b id="aim_hc_tools_val">—</b><small id="aim_hc_tools_sub">Available</small></div></div>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:11px; color:var(--bs-secondary-color);">
        <span id="aim_last_updated">Last updated: —</span>
        <label style="display:flex; gap:6px; align-items:center; cursor:pointer;"><input type="checkbox" id="aim_auto_refresh" checked> Auto-refresh</label>
    </div>

    <fieldset class="border p-3">
        <legend>📋 Current configuration</legend>
        <div class="p-3">
            <div id="aim_status_table"><span class="text-secondary">Loading…</span></div>
            <p style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <a class="buttons" href="plugin.php?plugin=fpp-AImode&page=assistant.php" style="font-weight:600;">💬 Open Assistant</a>
                <a class="buttons" href="plugin.php?plugin=fpp-AImode&page=config.php">⚙️ Configure Provider</a>
                <button type="button" class="buttons" onclick="aimStatus.refresh();">↻ Refresh now</button>
            </p>
        </div>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:16px;">
        <legend>🩺 Diagnostics <span id="aim_diag_badge" class="badge bg-secondary" style="font-size:11px; vertical-align:middle; margin-left:8px;">checking…</span></legend>
        <div class="p-3" id="aim_diag_table"><span class="text-secondary">Running checks…</span></div>
        <p style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button type="button" class="buttons" onclick="aimStatus.refresh();">↻ Re-run checks</button>
            <span id="aim_diag_meta" class="text-secondary" style="font-size:12px;"></span>
        </p>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:16px;">
        <legend>🔧 Available tools <span class="text-secondary" style="font-weight:400; font-size:11px;">(what the AI can do)</span></legend>
        <div class="p-3">
            <div style="display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                <input type="text" id="aim_tools_search" class="form-control" placeholder="🔍 Search tools… e.g. playlist, schedule" style="flex:1 1 200px; max-width:320px; font-size:12px;" oninput="aimStatus.filterTools()">
                <span class="text-secondary" style="font-size:11px; align-self:center;" id="aim_tools_count"></span>
            </div>
            <div id="aim_tools_table"><span class="text-secondary">Loading…</span></div>
        </div>
    </fieldset>
</div>

<script>
var aimStatus = {
    _allTools: [],
    refresh: function(){
        var now = new Date();
        $('#aim_last_updated').text('Last updated: ' + now.toLocaleString());
        // status + health cards
        $.ajax({
            url:'api/plugin/fpp-AImode/status',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(!d.success){ $('#aim_status_table').html('<span class="text-danger">'+aimStatus.esc(d.error||'Error')+'</span>'); return; }
                var s = d.settings || {};
                var fpp = d.fpp_status || {};
                var html = '<table class="fppTable" style="width:auto;">';
                html += '<tr><td style="padding:6px;"><b>🤖 Provider</b></td><td style="padding:6px;">'+aimStatus.esc(s.provider||'—')+' <span class="text-secondary" style="font-size:11px;">('+aimStatus.esc(d.effective_base_url||'')+')</span></td></tr>';
                html += '<tr><td style="padding:6px;"><b>🧠 Model</b></td><td style="padding:6px;">'+aimStatus.esc((d.provider_meta && d.provider_meta.defaultModel) ? (s.model||'') : (s.model||'—'))+'</td></tr>';
                html += '<tr><td style="padding:6px;"><b>🔑 API Key</b></td><td style="padding:6px;">'+ (s.api_key && s.api_key.indexOf('***')===0 ? '<span class="badge bg-success">✓ Set ('+aimStatus.esc(s.api_key)+')</span>' : '<span class="badge bg-warning text-dark">Not set</span>') +'</td></tr>';
                html += '<tr><td style="padding:6px;"><b>🎄 FPP</b></td><td style="padding:6px;">'+(d.fpp_reachable ? '<span class="badge bg-success">● Reachable</span>' : '<span class="badge bg-danger">● Unreachable</span>')+'</td></tr>';
                if(fpp){
                    var st = fpp.status_name || fpp.status || '';
                    html += '<tr><td style="padding:6px;"><b>Status</b></td><td style="padding:6px;">'+aimStatus.esc(st||JSON.stringify(fpp).slice(0,120))+'</td></tr>';
                }
                html += '<tr><td style="padding:6px;"><b>💬 History</b></td><td style="padding:6px;">'+aimStatus.esc(String(d.history_count||0))+' entries</td></tr>';
                html += '<tr><td style="padding:6px;"><b>🔧 Tools</b></td><td style="padding:6px;">'+aimStatus.esc(String((d.tools||[]).length))+' available</td></tr>';
                html += '</table>';
                $('#aim_status_table').html(html);
                // health cards
                var provOk = s.api_key && s.api_key.indexOf('***')===0;
                $('#aim_hc_provider').removeClass('ok warn err').addClass(provOk?'ok':'warn').find('#aim_hc_provider_val').text(s.provider||'—');
                $('#aim_hc_provider_sub').text(provOk ? 'API key set' : 'Needs API key');
                $('#aim_hc_fpp').removeClass('ok warn err').addClass(d.fpp_reachable?'ok':'err').find('#aim_hc_fpp_val').text(d.fpp_reachable?'Reachable':'Unreachable');
                $('#aim_hc_fpp_sub').text(fpp.status_name || (d.fpp_reachable?'FPPD online':'Check FPP'));
                $('#aim_hc_history').removeClass('ok warn err').addClass('ok').find('#aim_hc_history_val').text(String(d.history_count||0));
                $('#aim_hc_history_sub').text('entries · last 40 kept');
                $('#aim_hc_tools').removeClass('ok warn err').addClass('ok').find('#aim_hc_tools_val').text(String((d.tools||[]).length));
                $('#aim_hc_tools_sub').text('tools ready');
            },
            error: function(){ $('#aim_status_table').html('<span class="text-danger">Could not reach plugin API</span>'); $('#aim_hc_provider').addClass('err'); }
        });
        // diagnostics
        $.ajax({
            url:'api/plugin/fpp-AImode/diagnostics',
            type:'GET',
            dataType:'json',
            success: function(d){
                var allOk = d.success;
                $('#aim_diag_badge').removeClass('bg-success bg-danger bg-secondary').addClass(allOk?'bg-success':'bg-danger').text(allOk?'✓ All good':'✗ Issues found');
                if(!d.results){ $('#aim_diag_table').html('<span class="text-danger">No diagnostics</span>'); return; }
                var pct = Math.round((d.results.filter(function(r){return r.ok;}).length / d.results.length)*100);
                var html='<div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;"><div style="flex:1; height:8px; background:var(--bs-tertiary-bg); border-radius:4px; overflow:hidden;"><div style="width:'+pct+'%; height:100%; background:'+(allOk?'var(--bs-success)':'var(--bs-warning)')+';"></div></div><span style="font-size:11px; font-weight:600;">'+pct+'% passing</span></div>';
                html+='<table class="fppTable" style="width:auto;"><thead><tr><th>✓</th><th>Check</th><th>Detail</th></tr></thead><tbody>';
                d.results.forEach(function(r){
                    html+='<tr style="'+(r.ok?'':'background:var(--bs-warning-bg-subtle);')+'"><td style="text-align:center;">'+(r.ok ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>')+'</td><td style="font-weight:500;">'+aimStatus.esc(r.check)+'</td><td class="text-secondary" style="font-size:12px;">'+aimStatus.esc(r.detail||'')+'</td></tr>';
                });
                html+='</tbody></table>';
                if(!d.success) html = '<div class="alert alert-warning" style="font-size:12px; padding:8px 12px; border-radius:8px; margin-bottom:10px;">⚠️ Some checks failed — open <a href="plugin.php?plugin=fpp-AImode&page=config.php">Config</a> to fix API key / model.</div>' + html;
                else html = '<div class="alert alert-success" style="font-size:12px; padding:8px 12px; border-radius:8px; margin-bottom:10px;">✅ All systems go — you\'re ready to chat!</div>' + html;
                $('#aim_diag_table').html(html);
                $('#aim_diag_meta').text(d.settings ? (d.settings.provider + ' / ' + d.settings.model) : '');
            },
            error: function(){ $('#aim_diag_table').html('<span class="text-danger">Could not reach API</span>'); $('#aim_diag_badge').text('error').removeClass('bg-secondary').addClass('bg-danger'); }
        });
        // tools
        $.ajax({
            url:'api/plugin/fpp-AImode/tools',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(!d.tools){ $('#aim_tools_table').html('<span class="text-danger">No tools</span>'); return; }
                aimStatus._allTools = d.tools;
                aimStatus.renderTools(d.tools);
                $('#aim_tools_count').text(d.tools.length + ' tools');
            },
            error: function(){ $('#aim_tools_table').html('<span class="text-danger">Could not reach API</span>'); }
        });
    },
    renderTools: function(tools){
        var html='<table class="fppTable" style="width:auto;"><thead><tr><th>Tool</th><th>What it does</th></tr></thead><tbody>';
        tools.forEach(function(t){
            html+='<tr><td><code style="background:var(--bs-tertiary-bg); padding:2px 6px; border-radius:4px;">'+aimStatus.esc(t.name)+'</code></td><td style="font-size:12px;">'+aimStatus.esc(t.description)+'</td></tr>';
        });
        html+='</tbody></table>';
        $('#aim_tools_table').html(html);
        if(tools.length===0) $('#aim_tools_table').html('<div class="text-secondary" style="padding:12px; text-align:center;">No tools match your search.</div>');
    },
    filterTools: function(){
        var q = ($('#aim_tools_search').val()||'').toLowerCase().trim();
        if(!q) { aimStatus.renderTools(aimStatus._allTools); $('#aim_tools_count').text(aimStatus._allTools.length + ' tools'); return; }
        var filtered = aimStatus._allTools.filter(function(t){ return t.name.toLowerCase().indexOf(q)!==-1 || t.description.toLowerCase().indexOf(q)!==-1; });
        aimStatus.renderTools(filtered);
        $('#aim_tools_count').text(filtered.length + '/' + aimStatus._allTools.length + ' tools');
    },
    esc: function(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
};
var aimStatusTimer = null;
function aimStatusStart(){ if(aimStatusTimer) clearInterval(aimStatusTimer); aimStatusTimer = setInterval(function(){ if($('#aim_auto_refresh').is(':checked')) aimStatus.refresh(); }, 10000); }
$(document).ready(function(){ aimStatus.refresh(); aimStatusStart(); $('#aim_auto_refresh').on('change', function(){ if($(this).is(':checked')) aimStatus.refresh(); }); });
</script>

<?php include __DIR__ . '/footer.inc'; ?>
