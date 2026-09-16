# Contributing to fpp-AImode

Thanks for considering a contribution!

## Development Setup

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-AImode.git fpp-AImode
cd fpp-AImode
```

FPP loads plugins from `/home/fpp/media/plugins/<name>`. No build step — PHP is interpreted.

## Code Style

- Follow the existing `fpp-haCommands` / `fpp-ListenLive` style: header comment block, `tabs.inc` inclusion, `jGrowl` for toasts, `api/plugin/fpp-AImode/*` endpoints.
- Keep `api.php` functions prefixed `ai*` / `aim*`.
- Validate all `$_POST` / `$_GET` inputs; escape shell args with `escapeshellarg`.
- Redact API keys in logs.

## Testing

- Use **Config → Test Connection** for each provider (you’ll need a real or mocked key; Ollama can be tested locally without a key).
- Use **Assistant → Send** with dry-run to verify tool calls without side effects.
- Check `/home/fpp/media/logs/plugin-fpp-AImode.log`.
- Run `php -l api.php && php -l config.php && php -l assistant.php` to lint.

## Pull Requests

1. Fork, create a feature branch.
2. Keep changes focused; include before/after screenshots for UI changes.
3. Ensure `pluginInfo.json` versions still cover FPP 8/9/10.
4. Open a PR against `main`.

