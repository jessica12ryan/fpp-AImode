<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## developer.php                                           ##
 * #############################################################
 */
$pluginDir = __DIR__;
$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
.btn-danger { background: var(--bs-danger); color: var(--bs-white); border: 1px solid var(--bs-danger); padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-danger:hover { filter:brightness(0.85); }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-warning { background: var(--bs-warning); color: var(--bs-dark); border: 1px solid var(--bs-warning); padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-warning:hover { filter:brightness(0.9); }
.btn-warning:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.modal-box { background: var(--bs-body-bg); color: var(--bs-body-color); border-radius: 8px; padding: 24px 32px; max-width: 420px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); text-align: center; }
.modal-message { font-size: 15px; line-height: 1.5; margin-bottom: 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.modal-btn { padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; border: none; }
.modal-btn-primary { background: var(--bs-danger); color: var(--bs-white); }
.modal-btn-primary:hover { filter:brightness(0.85); }
.modal-btn-warning { background: var(--bs-warning); color: var(--bs-dark); }
.modal-btn-warning:hover { filter:brightness(0.9); }
.modal-btn-default { background: var(--bs-secondary); color: var(--bs-white); }
.modal-btn-default:hover { filter:brightness(0.9); }
.modal-btn-info { background: var(--bs-primary); color: var(--bs-white); }
.modal-btn-info:hover { filter:brightness(0.9); }
.btn-info { background: var(--bs-primary); color: var(--bs-white); border: 1px solid var(--bs-primary); padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-info:hover { filter:brightness(0.9); }
.btn-info:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Developer Tools</legend>
        <div class="p-3">

            <h3 class="text-primary">Updates</h3>
            <p>Check whether the plugin is up to date with GitHub.</p>
            <div style="display:flex; gap:10px; align-items:start;">
                <div>
                    <button type="button" class="btn-info" id="check_updates_btn" onclick="aimDev.checkUpdates();">↻ Check for Updates</button>
                </div>
                <div id="update_result"></div>
            </div>

            <hr style="margin:20px 0;">

            <h3 class="text-warning">Plugin Management</h3>
            <p>Reinstall to apply file updates, or uninstall completely. Config (keys/history) is preserved on reinstall.</p>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-warning" id="reinstall_btn" onclick="aimDev.reinstall();">⚠ Reinstall Plugin</button>
                <button type="button" class="btn-danger" id="uninstall_btn" onclick="aimDev.uninstall();">⚠ Uninstall Plugin</button>
            </div>

            <hr style="margin:20px 0;">

            <h3 class="text-secondary">Diagnostics</h3>
            <p>Raw diagnostics JSON (same as Status tab) for debugging.</p>
            <pre id="aim_dev_diag" style="background:var(--bs-tertiary-bg,#f8f9fa); padding:10px; border-radius:4px; max-height:400px; overflow:auto; font-size:11px;">Click “Load Diagnostics”</pre>
            <button type="button" class="buttons" onclick="aimDev.loadDiag();">Load Diagnostics</button>
            <button type="button" class="buttons" onclick="aimDev.restartFppd();">Flag FPPD Restart</button>
            <span id="aim_restart_result" class="text-secondary" style="font-size:12px; margin-left:8px;"></span>
        </div>
    </fieldset>
</div>

<div id="modal_overlay" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-message" id="modal_message"></div>
        <div class="modal-actions" id="modal_actions"></div>
    </div>
</div>

<script>
var aimDev = {
    showModal: function(message, buttons) {
        $('#modal_message').html(message);
        var $actions = $('#modal_actions').empty();
        $.each(buttons, function(i, btn) {
            $actions.append($('<button>', {
                text: btn.label,
                class: 'modal-btn ' + (btn['class'] || 'modal-btn-default'),
                click: function() { if (btn.onClick) btn.onClick(); else aimDev.hideModal(); }
            }));
        });
        $('#modal_overlay').show();
    },
    hideModal: function(){ $('#modal_overlay').hide(); },
    showConfirm: function(message, onConfirm, cls){
        aimDev.showModal(message, [
            { label: 'Cancel', 'class': 'modal-btn-default', onClick: aimDev.hideModal },
            { label: 'Confirm', 'class': cls || 'modal-btn-primary', onClick: function(){ aimDev.hideModal(); if(onConfirm) onConfirm(); } }
        ]);
    },
    showAlert: function(m){ aimDev.showModal(m, [{label:'OK','class':'modal-btn-primary'}]); },
    checkUpdates: function(){
        $('#check_updates_btn').html('↻ Check for Updates').prop('disabled',true).attr('onclick','aimDev.checkUpdates();');
        $('#update_result').html('<span class="text-secondary">Checking…</span>');
        $.ajax({
            url:'api/plugin/fpp-AImode/check-updates', type:'GET', dataType:'json',
            success: function(d){
                if(d.updateAvailable){
                    $('#check_updates_btn').html('↻ Install Updates').prop('disabled',false).attr('onclick','aimDev.installUpdates();');
                    $('#update_result').html('<span class="text-warning fw-semibold">⚠ Update available!</span><br><span class="text-secondary small">Local: '+d.localSha+'<br>Remote: '+d.remoteSha+'<br><a href="https://github.com/jessica12ryan/fpp-AImode" target="_blank">View on GitHub</a></span>');
                } else {
                    $('#check_updates_btn').html('↻ Check for Updates').prop('disabled',false).attr('onclick','aimDev.checkUpdates();');
                    $('#update_result').html('<span class="text-success fw-semibold">✓ Plugin is up to date</span><br><span class="text-secondary small">'+d.localSha+'</span>');
                }
            },
            error: function(xhr){
                $('#check_updates_btn').html('↻ Check for Updates').prop('disabled',false).attr('onclick','aimDev.checkUpdates();');
                var msg='Could not reach plugin API.';
                try{ var r=JSON.parse(xhr.responseText); if(r.error) msg=r.error; }catch(e){}
                $('#update_result').html('<span class="text-danger">'+msg+'</span>');
            }
        });
    },
    installUpdates: function(){
        aimDev.showConfirm('Update the plugin to the latest from GitHub? Config will be preserved.', function(){
            $('#check_updates_btn').prop('disabled',true);
            $.ajax({ url:'api/plugin/fpp-AImode/update', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
                success: function(){ location.reload(); },
                error: function(xhr){ var m='Could not reach API.'; try{ var r=JSON.parse(xhr.responseText); if(r.error) m=r.error; }catch(e){} $('#check_updates_btn').prop('disabled',false); aimDev.showAlert(m); }
            });
        }, 'modal-btn-info');
    },
    reinstall: function(){
        aimDev.showConfirm('Reinstall the plugin? Config will be preserved.', function(){
            $('#reinstall_btn').prop('disabled',true);
            $.ajax({ url:'api/plugin/fpp-AImode/reinstall', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
                success: function(){ location.reload(); },
                error: function(xhr){ var m='Could not reach API.'; try{ var r=JSON.parse(xhr.responseText); if(r.error) m=r.error; }catch(e){} $('#reinstall_btn').prop('disabled',false); aimDev.showAlert(m); }
            });
        }, 'modal-btn-warning');
    },
    uninstall: function(){
        aimDev.showConfirm('Completely remove the plugin and all its files? Config will be lost. FPPD will be flagged for restart.', function(){
            $('#uninstall_btn').prop('disabled',true);
            $.ajax({ url:'api/plugin/fpp-AImode/uninstall', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
                success: function(){ window.location.href='plugins.php?tab=available'; },
                error: function(xhr){ var m='Could not reach API.'; try{ var r=JSON.parse(xhr.responseText); if(r.error) m=r.error; }catch(e){} $('#uninstall_btn').prop('disabled',false); aimDev.showAlert(m); }
            });
        }, 'modal-btn-primary');
    },
    loadDiag: function(){
        $('#aim_dev_diag').text('Loading…');
        $.ajax({ url:'api/plugin/fpp-AImode/diagnostics', type:'GET', dataType:'json',
            success: function(d){ $('#aim_dev_diag').text(JSON.stringify(d, null, 2)); },
            error: function(){ $('#aim_dev_diag').text('Failed to load'); }
        });
    },
    restartFppd: function(){
        $('#aim_restart_result').text('Flagging…');
        $.ajax({ url:'api/plugin/fpp-AImode/restart-fppd', type:'POST', contentType:'application/json', data:'{}', dataType:'json',
            success: function(r){ $('#aim_restart_result').html('<span class="text-success">'+r.message+'</span>'); },
            error: function(){ $('#aim_restart_result').html('<span class="text-danger">Failed</span>'); }
        });
    }
};
$(document).on('click', '#modal_overlay', function(e){ if(e.target===this) aimDev.hideModal(); });
</script>

<?php include __DIR__ . '/footer.inc'; ?>
