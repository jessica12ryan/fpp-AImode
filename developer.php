<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## developer.php                                           ##
 * #############################################################
 */
$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Developer Tools</legend>
        <div class="p-3">

            <h3 class="text-secondary">Diagnostics</h3>
            <p>Raw diagnostics JSON (same as the Status tab) for debugging.</p>
            <pre id="aim_dev_diag" class="bg-body-tertiary p-2 rounded" style="max-height:400px; overflow:auto; font-size:11px;">Click &ldquo;Load Diagnostics&rdquo;</pre>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="buttons" onclick="aimDev.loadDiag();">Load Diagnostics</button>
                <button type="button" class="buttons" onclick="aimDev.flagRestart();">Flag FPPD Restart</button>
                <span id="aim_restart_result" class="text-secondary small"></span>
            </div>

            <hr class="my-3">

            <h3 class="text-secondary">Updates and removal</h3>
            <p class="small text-secondary">
                Use <b>Content Setup &rarr; Plugin Manager</b> to update, reinstall or uninstall this plugin.
                FPP applies the version pinned in <code>pluginInfo.json</code>, re-shows the privacy
                disclosure when it changes, and keeps dependency bookkeeping in order. This tab only
                offers diagnostics and the standard FPPD restart flag.
            </p>
        </div>
    </fieldset>
</div>

<script>
var aimDev = {
    loadDiag: function(){
        $('#aim_dev_diag').text('Loading…');
        $.ajax({
            url:'api/plugin/fpp-AImode/diagnostics', type:'GET', dataType:'json',
            success: function(d){ $('#aim_dev_diag').text(JSON.stringify(d, null, 2)); },
            error: function(){ $('#aim_dev_diag').text('Failed to load'); }
        });
    },
    flagRestart: function(){
        $('#aim_restart_result').text('Flagging…');
        $.ajax({
            url:'api/plugin/fpp-AImode/restart-fppd', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
            success: function(r){ $('#aim_restart_result').text(r.message || 'Restart flag set'); },
            error: function(){ $('#aim_restart_result').text('Failed to set restart flag'); }
        });
    }
};
</script>

<?php include __DIR__ . '/footer.inc'; ?>
