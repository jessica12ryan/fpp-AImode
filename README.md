# ![icon](icon.png) AI Mode Plugin for FPP (fpp-AImode)

> **Configure and control FPP with natural language via your favorite AI.** Link OpenAI, Anthropic, Google Gemini, Mistral, Grok, OpenRouter, Ollama and more — then describe what you want in plain English.

## What It Does

FPP has dozens of settings, playlists, schedules, and channel outputs. **AI Mode** lets you say:

> *“Create a playlist called Christmas with `Jingle.fseq` and `Wizards.fseq` shuffled, schedule it nightly 6pm–10pm, and set brightness to 80%”*

… and the plugin translates that into the correct FPP API calls, shows you each action, and executes only after you approve.

- **8 providers** — OpenAI, Anthropic, Google (Gemini), Mistral, Grok (xAI), OpenRouter, Ollama (local), Azure OpenAI — switch with one dropdown
- **Token/API-key per provider** with custom base URL & model picker
- **Conversational assistant** — chat history, streaming-style replies, retry
- **Tool calling** — AI proposes structured actions (create playlist, add schedule, update settings, manage outputs, etc.); you review & approve
- **Dry-run mode** — see what *would* happen without changing anything
- **Auto-approve toggle** — for trusted LANs (off by default)
- **FPP-aware system prompt** — plugin injects live FPP context (status, playlists, settings, outputs) so the model knows your setup

## Requirements

- FPP 8+
- `php-curl` (installed automatically via Plugin Manager)
- Network access from FPP to your chosen AI provider (or LAN access for Ollama)
- An API key / token for the provider you want to use

## Installation

### Plugin Manager (Recommended)

1. In FPP UI, go to **Content Setup → Plugin Manager**
2. Paste:
```
https://raw.githubusercontent.com/jessica12ryan/fpp-AImode/main/pluginInfo.json
```
3. Click **Install** next to “AI Mode Plugin for FPP”

### Manual

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-AImode.git fpp-AImode
sudo chown -R fpp:fpp fpp-AImode
```

## Configuration

1. Open **Content Setup → AI Mode → Config**
2. Pick a **Provider** (e.g. `OpenAI`)
3. Paste your **API Key / Token** (from the provider’s dashboard)
4. Choose a **Model** (e.g. `gpt-4o-mini`) or type a custom one
5. Optionally override **Base URL** (needed for Azure, Ollama, or proxies)
6. Tune **System Prompt**, temperature & max tokens
7. Click **Save Settings**, then **Test Connection** — should show `✓ Connected (model: …)`
8. Go to **AI Assistant** tab and start chatting!

### Provider Notes

| Provider | Key prefix | Default Base URL | Example Models |
|---|---|---|---|
| **OpenAI** | `sk-…` | `https://api.openai.com/v1` | `gpt-4o`, `gpt-4o-mini`, `o1-mini` |
| **Anthropic** | `sk-ant-…` | `https://api.anthropic.com` | `claude-3-5-sonnet-20241022`, `claude-3-haiku` |
| **Google** | `AIza…` | `https://generativelanguage.googleapis.com` | `gemini-1.5-flash`, `gemini-1.5-pro`, `gemini-2.0-flash` |
| **Mistral** | … | `https://api.mistral.ai/v1` | `mistral-large-latest`, `mistral-small` |
| **Grok (xAI)** | `xai-…` | `https://api.x.ai/v1` | `grok-2`, `grok-beta` |
| **OpenRouter** | `sk-or-…` | `https://openrouter.ai/api/v1` | `openai/gpt-4o`, `anthropic/claude-3.5-sonnet` |
| **Ollama** | *(none)* | `http://localhost:11434` | `llama3.1`, `qwen2.5`, `mistral` |
| **Azure OpenAI** | Azure key | `https://{endpoint}.openai.azure.com` | your deployment name = model field |

> **Security:** Keys are stored in `/home/fpp/media/plugindata/fpp-AImode/settings.json` with `0600` (plugindata, not config, so crash bundles and backups never carry them in clear). Keys are never logged. Use FPP’s UI password if FPP is exposed.

## Usage

### Chat Assistant

1. **Content Setup → AI Mode → Assistant**
2. Type a prompt: *“What playlists do I have?”* or *“Make a new schedule for Halloween”*
3. The AI replies and may propose **tool calls** (e.g. `create_playlist`, `update_setting`).
4. Review the JSON diff and click **Approve** (or **Approve All**). With dry-run on, no changes are made.
5. Results appear in the chat and in **Logs**.

### What Can It Configure?

The built-in toolset covers most of `/api/*`:

- `get_status`, `get_settings`, `update_settings`
- `list_playlists`, `get_playlist`, `create_playlist`, `delete_playlist`
- `list_schedules`, `create_schedule`, `delete_schedule`
- `get_network_config`, `list_outputs`, `set_output`
- `get_system_info`, `restart_fppd`, `get_fppd_status`

The system prompt tells the model to *always* read before writing and to explain each change.

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `api/plugin/fpp-AImode/status` | Plugin + FPP status + provider detection |
| GET | `api/plugin/fpp-AImode/diagnostics` | Provider/model validation, FPP API checks |
| POST | `api/plugin/fpp-AImode/save` | Save settings JSON |
| POST | `api/plugin/fpp-AImode/test` | Test provider connectivity |
| POST | `api/plugin/fpp-AImode/chat` | Chat with AI (prompt → reply + tool calls) |
| GET | `api/plugin/fpp-AImode/history` | Get conversation history |
| POST | `api/plugin/fpp-AImode/history/clear` | Clear history |
| POST | `api/plugin/fpp-AImode/execute` | Execute a single tool call |
| GET | `api/plugin/fpp-AImode/tools` | List available tools |
| GET | `api/plugin/fpp-AImode/logs` | Last 100 log lines |
| GET | `api/plugin/fpp-AImode/check-updates` | Git SHA check |
| POST | `api/plugin/fpp-AImode/update` / `reinstall` / `uninstall` | Maintenance |
| GET | `api/plugin/fpp-AImode/icon` | Plugin icon |

## Troubleshooting

- **Test Connection fails** → check API key, base URL, and that FPP can reach the internet (`ping api.openai.com` via SSH).
- **Ollama not reachable** → ensure Ollama is running and `OLLAMA_HOST=0.0.0.0` if on another host; set Base URL to `http://<ollama-ip>:11434`.
- **Tool calls not executing** → ensure you clicked **Approve**; check **Logs** for `tool:…` entries.
- **Model not found** → some providers require exact model IDs; try the provider’s model list endpoint.
- **Logs:** `tail -20 /home/fpp/media/logs/plugin-fpp-AImode.log`

## Development

Enable **Developer** UI level (FPP Settings → UI → Developer) to see the **Developer** tab for update/reinstall/uninstall and full diagnostics.

## License

MIT — see [LICENSE.md](LICENSE.md). FPP is © Falcon Christmas. This plugin is not affiliated with or endorsed by the Falcon Christmas project.

## About

AI Mode Plugin for FPP — natural-language setup for Falcon Player.

