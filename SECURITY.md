# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| main | ✅ |

## Reporting a Vulnerability

Please open a GitHub issue or email the maintainer. Do not disclose API keys or tokens in issues.

## Security Notes for fpp-AImode

- API keys are stored plaintext in `plugindata/fpp-AImode/settings.json` with `0600` permissions (not in `config/`, so crash bundles and backups don't carry them). Protect your FPP host and use FPP's UI password if exposed to a network.
- Keys are **never logged** — log redaction replaces keys with `***`.
- Prompts and FPP context (status/settings/playlist names) are sent to the **configured provider only** when you click Send/Test. No background transmission.
- Tool calls that modify FPP run with **auto-approve on by default** (each tool executes immediately after the AI reply). Uncheck Auto-Approve in Config to require manual approval per call. Dry-run shows the diff without executing.
- Ollama/local providers keep data on your LAN; cloud providers send data off-device — choose accordingly.
- The plugin only calls local FPP APIs at `http://localhost/api/*` via the proxied Apache path (never the raw `fppd` port `32322`); it does not open inbound ports.
- Keep FPP and the plugin updated; check the Developer tab for updates.

