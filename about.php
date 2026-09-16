<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## about.php                                               ##
 * #############################################################
 */
$uiLevel = (int)($settings['uiLevel'] ?? 0);
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

<div style="margin:0 auto;"> <br />
    <fieldset class="border p-3">
        <legend>About AI Mode Plugin</legend>
        <div class="p-3">
            <div id='credits'>
                <h3 style="margin-top:0;">AI Mode Plugin for FPP</h3>

                <p>
                    Natural-language configuration for <b>Falcon Player (FPP)</b> — describe what you want
                    (typed or spoken via <b>🎤 Voice Input</b>), and the AI builds the playlists, schedules, settings, and outputs for you.
                </p>

                <h4>Features</h4>
                <ul>
                    <li>8 providers: OpenAI, Anthropic (Claude), Google Gemini, Mistral, Grok (xAI), OpenRouter, Ollama (local), Azure OpenAI</li>
                    <li>Switch providers with one dropdown — API token / base URL / model per provider</li>
                    <li>Custom system prompt &amp; tunable temperature / max tokens</li>
                    <li>Conversational assistant with history, tool-call approval, dry-run &amp; auto-approve</li>
                    <li><b>Voice input</b> via browser Web Speech API — mic → transcript → prompt (audio never sent to FPP or AI provider)</li>
                    <li>FPP-aware: injects live status, playlists, settings, and outputs so the AI knows your show</li>
                    <li>Tool calling for: status, settings, playlists, schedules, outputs, system info, restart</li>
                    <li>Redacted logs, 0600 key storage, no background transmission</li>
                    <li>Works with FPP 8.x, 9.x, and 10.x</li>
                </ul>

                <h4>How It Works</h4>
                <ol>
                    <li>You pick a provider and paste its API key in <b>Config</b></li>
                    <li>You chat in <b>Assistant</b> — the plugin sends your prompt + FPP context to the provider</li>
                    <li>The model replies with text and optional <b>tool calls</b> (e.g. <code>create_playlist</code>)</li>
                    <li>You review each tool call and click <b>Approve</b> — the plugin executes it via <code>http://localhost/api/*</code></li>
                    <li>Results appear in the chat and in <b>Logs</b>; use <b>Status</b> to see the current setup</li>
                </ol>

                <h4>Privacy</h4>
                <p>
                    Prompts + FPP context are sent <b>only</b> to the provider you selected, and only when you click Send/Test.
                    Keys are stored in <code>plugindata/fpp-AImode/settings.json</code> with 0600 (not in config, so crash bundles never carry them) and never logged. Voice audio stays in the browser — only the transcript text is sent as a prompt (browser may use its own cloud for transcription per browser privacy). Choose Ollama for fully local AI.
                </p>

                <h4>Links</h4>
                <p>
                    <a href="https://github.com/jessica12ryan/fpp-AImode" target="_blank">GitHub Repository</a><br>
                    <a href="https://github.com/jessica12ryan/fpp-AImode/issues" target="_blank">Issue Tracker &amp; Feature Requests</a><br>
                    <a href="https://github.com/jessica12ryan/fpp-AImode/blob/main/README.md" target="_blank">README &amp; Installation Guide</a>
                </p>

                <h4>Plugin Info</h4>
                <p>
                    Name: <b>AI Mode Plugin for FPP</b><br>
                    Author: <b>jessica12ryan</b><br>
                    License: <b>MIT</b><br>
                </p>
            </div>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
