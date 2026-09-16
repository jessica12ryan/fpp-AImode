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
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>AI Mode — Status</legend>
        <div class="p-3">
            <div id="aim_status_table"><span class="text-secondary">Loading…</span></div>
            <p style="margin-top:10px;">
                <a class="buttons" href="plugin.php?plugin=fpp-AImode&page=assistant.php">✎ Open Assistant</a>
                <a class="buttons" href="plugin.php?plugin=fpp-AImode&page=config.php">Configure</a>
                <input type="button" class="buttons" value="↻ Refresh" onclick="aimStatus.refresh();">
            </p>
        </div>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:12px;">
        <legend>Diagnostics</legend>
        <div class="p-3" id="aim_diag_table"><span class="text-secondary">Loading…</span></div>
        <p style="margin-top:8px;">
            <input type="button" class="buttons" value="↻ Refresh" onclick="aimStatus.refresh();">
            <span id="aim_diag_meta" class="text-secondary" style="font-size:12px; margin-left:8px;"></span>
        </p>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:12px;">
        <legend>Available Tools</legend>
        <div class="p-3" id="aim_tools_table"><span class="text-secondary">Loading…</span></div>
    </fieldset>
</div>

<script>
var aimStatus = {
    refresh: function(){
        // status
        $.ajax({
            url:'api/plugin/fpp-AImode/status',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(!d.success){ $('#aim_status_table').html('<span class="text-danger">'+aimStatus.esc(d.error||'Error')+'</span>'); return; }
                var s = d.settings || {};
                var fpp = d.fpp_status || {};
                var html = '<table class="fppTable" style="width:auto;">';
                html += '<tr><td style="padding:4px;"><b>Provider:</b></td><td style="padding:4px;">'+aimStatus.esc(s.provider||'—')+' <span class="text-secondary">('+aimStatus.esc(d.effective_base_url||'')+')</span></td></tr>';
                html += '<tr><td style="padding:4px;"><b>Model:</b></td><td style="padding:4px;">'+aimStatus.esc((d.provider_meta && d.provider_meta.defaultModel) ? (s.model||'') : (s.model||'—'))+'</td></tr>';
                html += '<tr><td style="padding:4px;"><b>API Key:</b></td><td style="padding:4px;">'+aimStatus.esc(s.api_key||'—')+'</td></tr>';
                html += '<tr><td style="padding:4px;"><b>FPP Reachable:</b></td><td style="padding:4px;">'+(d.fpp_reachable ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>')+'</td></tr>';
                if(fpp){
                    var st = fpp.status_name || fpp.status || '';
                    html += '<tr><td style="padding:4px;"><b>FPP Status:</b></td><td style="padding:4px;">'+aimStatus.esc(st||JSON.stringify(fpp).slice(0,120))+'</td></tr>';
                }
                html += '<tr><td style="padding:4px;"><b>History entries:</b></td><td style="padding:4px;">'+aimStatus.esc(String(d.history_count||0))+'</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Tools:</b></td><td style="padding:4px;">'+aimStatus.esc((d.tools||[]).join(', '))+'</td></tr>';
                html += '</table>';
                $('#aim_status_table').html(html);
            },
            error: function(){ $('#aim_status_table').html('<span class="text-danger">Could not reach plugin API</span>'); }
        });
        // diagnostics
        $.ajax({
            url:'api/plugin/fpp-AImode/diagnostics',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(!d.results){ $('#aim_diag_table').html('<span class="text-danger">No diagnostics</span>'); return; }
                var html='<table class="fppTable" style="width:auto;"><thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead><tbody>';
                d.results.forEach(function(r){
                    html+='<tr><td>'+aimStatus.esc(r.check)+'</td><td>'+(r.ok ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>')+'</td><td class="text-secondary" style="font-size:12px;">'+aimStatus.esc(r.detail||'')+'</td></tr>';
                });
                html+='</tbody></table>';
                if(!d.success) html = '<div class="text-warning" style="margin-bottom:6px;">Some checks failed — see Config.</div>' + html;
                else html = '<div class="text-success" style="margin-bottom:6px;">All checks passed.</div>' + html;
                $('#aim_diag_table').html(html);
                $('#aim_diag_meta').text(d.settings ? (d.settings.provider + ' / ' + d.settings.model) : '');
            },
            error: function(){ $('#aim_diag_table').html('<span class="text-danger">Could not reach API</span>'); }
        });
        // tools
        $.ajax({
            url:'api/plugin/fpp-AImode/tools',
            type:'GET',
            dataType:'json',
            success: function(d){
                if(!d.tools){ $('#aim_tools_table').html('<span class="text-danger">No tools</span>'); return; }
                var html='<table class="fppTable" style="width:auto;"><thead><tr><th>Tool</th><th>Description</th></tr></thead><tbody>';
                d.tools.forEach(function(t){
                    html+='<tr><td><code>'+aimStatus.esc(t.name)+'</code></td><td style="font-size:12px;">'+aimStatus.esc(t.description)+'</td></tr>';
                });
                html+='</tbody></table>';
                $('#aim_tools_table').html(html);
            },
            error: function(){ $('#aim_tools_table').html('<span class="text-danger">Could not reach API</span>'); }
        });
    },
    esc: function(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
};
$(document).ready(function(){ aimStatus.refresh(); setInterval(aimStatus.refresh, 10000); });
</script>

<?php include __DIR__ . '/footer.inc'; ?>
