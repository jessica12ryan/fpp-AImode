<?php
/**
 * #############################################################
 * ## AI Mode Plugin for FPP (fpp-AImode)                     ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-AImode        ##
 * ## help.php                                                ##
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

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>AI Mode - Help &amp; Usage Guide</legend>
        <div class="p-3">

            <h3>What This Plugin Does</h3>
            <p>
                <b>AI Mode</b> lets you configure Falcon Player (FPP) with plain English — <b>typed or spoken</b>. Instead of clicking through
                settings, playlists, and scheduler screens, you describe what you want — e.g. <i>“make a Christmas playlist and run it nightly”</i> —
                or say it via the <b>🎤 Voice Input</b> button, and the AI translates that into the correct FPP API calls.
            </p>
            <p>It works with <b>8 providers</b>: OpenAI, Anthropic (Claude), Google Gemini, Mistral, Grok (xAI), OpenRouter, Ollama (local), and Azure OpenAI.</p>

            <hr>

            <h3>Quick Start</h3>
            <ol>
                <li><b>Get an API key</b> from your chosen provider (e.g. <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>, <a href="https://console.anthropic.com/" target="_blank">Anthropic</a>, <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>). For Ollama, no key is needed — just run Ollama on your LAN.</li>
                <li>Open <b>AI Mode → Config</b>, pick the <b>Provider</b>, paste the <b>API Key</b>, choose a <b>Model</b>.</li>
                <li>Optionally set a custom <b>Base URL</b> (required for Azure, Ollama on another host, or proxies).</li>
                <li>Click <b>Save Settings</b>, then <b>Test Connection</b> — expect <span class="text-success">✓ Connected</span>.</li>
                <li>Go to <b>Assistant</b>, type a prompt — or click <b>🎤 Voice Input</b>, speak, and edit the transcript — then <b>Send</b>.</li>
                <li>Review any <b>tool calls</b> the AI proposes and click <b>Approve</b>. Check <b>Logs</b> for results.</li>
            </ol>

            <hr>

            <h3>Example Prompts</h3>
            <div class="table-responsive">
            <table class="fppTable" style="width:auto;">
                <thead><tr><th>Goal</th><th>Prompt to try</th></tr></thead>
                <tbody>
                    <tr><td>Inspect</td><td><code>What playlists and schedules do I have? Show current volume and status.</code></td></tr>
                    <tr><td>Create playlist</td><td><code>Create a playlist called Halloween with spooky.fseq and thriller.mp3, no shuffle.</code></td></tr>
                    <tr><td>Schedule</td><td><code>Schedule the Christmas playlist to run nightly 18:00-22:00 every day, repeating.</code></td></tr>
                    <tr><td>Settings</td><td><code>Set the host description to "Front Yard Show" and volume to 80.</code></td></tr>
                    <tr><td>Channel outputs</td><td><code>List my channel outputs and explain how to add an E1.31 universe 1 at 192.168.1.50.</code></td></tr>
                    <tr><td>Troubleshoot</td><td><code>Why is my schedule not playing? Check status and recent logs.</code></td></tr>
                </tbody>
            </table>
            </div>
            <p class="text-secondary" style="font-size:12px;">The AI sees live FPP context (status, settings, playlists) so it knows your current setup. It will read before writing and ask if unsure.</p>

            <hr>

            <h3>How Approval Works</h3>
            <p>
                The AI never changes FPP directly on its first reply — it <b>proposes tool calls</b> (JSON) that you review.
                Each card shows the tool name and arguments. Click <b>Approve &amp; Execute</b> to run it, or <b>Approve All</b> for multiple calls.
            </p>
            <ul>
                <li><b>Dry-run</b> (Config → Behavior) — when on, approvals are shown but nothing executes. Good for testing prompts.</li>
                <li><b>Auto-approve</b> — when on, every tool call runs immediately after the reply with no click needed. Off by default; only enable on a trusted LAN.</li>
                <li>Every execution is logged to <code>/home/fpp/media/logs/plugin-fpp-AImode.log</code> with redacted keys.</li>
            </ul>

            <hr>

            <h3>Provider Setup Details</h3>
            <div class="table-responsive">
            <table class="fppTable" style="width:auto;">
                <thead><tr><th>Provider</th><th>Key &amp; Base URL</th><th>Models (default • latest)</th></tr></thead>
                <tbody>
                    <tr><td>OpenAI</td><td>Key <code>sk-…</code>, default <code>api.openai.com/v1</code></td><td><code>gpt-4o-mini</code> (default) • <code>gpt-4o</code>, <code>gpt-5</code>, <code>gpt-5.6-sol</code>, <code>o3</code></td></tr>
                    <tr><td>Anthropic</td><td>Key <code>sk-ant-…</code>, default <code>api.anthropic.com</code></td><td><code>claude-3-5-sonnet-20241022</code> (default) • <code>claude-sonnet-4-6</code>, <code>claude-opus-5</code></td></tr>
                    <tr><td>Google</td><td>Key <code>AIza…</code>, default <code>generativelanguage.googleapis.com</code></td><td><code>gemini-3.6-flash</code> (default) • <code>gemini-3.8-flash</code>, <code>gemini-2.5-flash</code> (1.5/2.0 deprecated — auto-migrates to 3.6)</td></tr>
                    <tr><td>Mistral</td><td><code>api.mistral.ai/v1</code></td><td><code>mistral-large-latest</code> (default) • <code>codestral-latest</code></td></tr>
                    <tr><td>Grok (xAI)</td><td>Key <code>xai-…</code>, <code>api.x.ai/v1</code></td><td><code>grok-3</code> (default) • <code>grok-3-mini</code>, <code>grok-2</code></td></tr>
                    <tr><td>OpenRouter</td><td>Key <code>sk-or-…</code>, <code>openrouter.ai/api/v1</code></td><td><code>openai/gpt-4o-mini</code> (default) • <code>google/gemini-3.6-flash</code>, <code>anthropic/claude-opus-4</code></td></tr>
                    <tr><td>Ollama</td><td><i>No key</i>, set Base URL to <code>http://&lt;ollama-ip&gt;:11434</code></td><td><code>llama3.1</code> (default) • <code>llama3.3</code>, <code>qwen3</code>, <code>gemma3</code></td></tr>
                    <tr><td>Azure</td><td>Azure key, Base URL <code>https://{endpoint}.openai.azure.com</code>, Model = deployment name</td><td><code>gpt-4o</code>, <code>gpt-5</code>, <code>o3</code></td></tr>
                </tbody>
            </table>
            </div>
            <p class="text-warning" style="font-size:12px;">If Test fails with 401, the key is wrong; 404 often means the model name is invalid for that provider; “Could not resolve” means FPP cannot reach the internet — check FPP network settings.</p>

            <hr>

            <h3>Voice Input</h3>
            <p>
                On the <b>Assistant</b> page, click <b>🎤 Voice Input</b> to transcribe speech via your browser's <b>Web Speech API</b> (works in Chrome/Edge on desktop, Safari on macOS/iOS). While the button shows <span class="text-danger">● Listening…</span> it captures audio; interim text appears below. When you stop, the final transcript is inserted into the prompt box for you to review — <b>only the text</b> is sent to the AI provider when you click <b>Send</b>. No audio leaves your device to FPP or the AI provider.
            </p>
            <ul>
                <li>Choose a <b>language</b> from the dropdown or leave on <b>Auto</b> (uses your browser's language). The choice is saved per-browser in <code>localStorage</code>.</li>
                <li>Tick <b>Auto-send</b> to automatically send the transcript when listening ends (off by default).</li>
                <li>Requires <b>HTTPS or localhost</b> in most browsers and explicit microphone permission on first use. Look for the browser padlock → site settings → microphone.</li>
                <li><b>Privacy:</b> transcription is done by the browser; Chrome may send audio to Google's speech service, Safari uses on-device/Apple. See your browser's privacy policy. The plugin itself stores no audio.</li>
                <li>If voice shows <i>not supported</i>, use Chrome/Edge desktop — Firefox currently needs <code>media.webspeech.recognition.enable</code> in <code>about:config</code>.</li>
            </ul>

            <hr>

            <h3>Available Tools (what the AI can do)</h3>
            <div class="table-responsive">
            <table class="fppTable" style="width:auto;">
                <thead><tr><th>Tool</th><th>What it does</th></tr></thead>
                <tbody>
                    <tr><td><code>get_status</code></td><td>FPPD running status, current playlist/song, volume</td></tr>
                    <tr><td><code>get_settings</code> / <code>update_settings</code></td><td>Read / write FPP settings (volume, hostname, timezone, etc.)</td></tr>
                    <tr><td><code>list_playlists</code> / <code>get_playlist</code> / <code>create_playlist</code> / <code>delete_playlist</code></td><td>Manage playlists</td></tr>
                    <tr><td><code>list_schedules</code> / <code>create_schedule</code> / <code>delete_schedule</code></td><td>Manage scheduler entries</td></tr>
                    <tr><td><code>list_outputs</code></td><td>Channel outputs (E1.31, etc.)</td></tr>
                    <tr><td><code>get_system_info</code></td><td>Pi model, FPP version, storage</td></tr>
                    <tr><td><code>restart_fppd</code></td><td>Flag FPPD for restart after config changes</td></tr>
                </tbody>
            </table>
            </div>
            <p class="text-secondary" style="font-size:12px;">The model is instructed to read before writing and to explain each change.</p>

            <hr>

            <h3>Troubleshooting</h3>
            <h4>Test Connection fails</h4>
            <ul>
                <li>Verify the API key has no extra spaces; use <b>Show</b> to inspect.</li>
                <li>Check Base URL — especially for Azure and Ollama.</li>
                <li>Ensure FPP has internet (SSH: <code>ping -c2 api.openai.com</code> or <code>curl -I https://api.anthropic.com</code>).</li>
                <li>Ollama: run <code>curl http://&lt;ollama-ip&gt;:11434/api/tags</code> from FPP; set <code>OLLAMA_HOST=0.0.0.0</code> if on another host.</li>
            </ul>
            <h4>AI says it called a tool but nothing changed</h4>
            <ul>
                <li>Check <b>Dry-run</b> is off in Config.</li>
                <li>You must click <b>Approve</b> unless Auto-approve is on.</li>
                <li>Look at <b>Logs</b> for <code>tool:</code> entries.</li>
            </ul>
            <h4>Model not found / 404</h4>
            <ul>
                <li>Model IDs are provider-specific. Use the picker or check the provider’s model list.</li>
                <li>Azure: Model field must be your <b>deployment name</b>, not the base model name.</li>
                <li>OpenRouter: use <code>provider/model</code> slugs like <code>openai/gpt-4o-mini</code>.</li>
            </ul>
            <h4>Voice not working?</h4>
            <ul>
                <li>Use <b>Chrome or Edge on desktop</b> (best Web Speech support). Allow microphone when prompted.</li>
                <li>Check <b>HTTPS</b>: <code>chrome://settings/content/microphone</code> or site lock icon → permissions. HTTP on non-localhost is often blocked.</li>
                <li>Try a different <b>language</b> selection (e.g. <code>en-US</code> vs Auto) and speak clearly near the mic.</li>
                <li>Errors like <code>not-allowed</code> mean permission was denied — reset site permissions and reload.</li>
            </ul>
            <h4>Still stuck?</h4>
            <p>Check logs: <code>tail -40 /home/fpp/media/logs/plugin-fpp-AImode.log</code> and open an issue at <a href="https://github.com/jessica12ryan/fpp-AImode/issues" target="_blank">GitHub Issues</a>.</p>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
