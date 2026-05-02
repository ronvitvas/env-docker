# Project Context

This project is a Bitrix24 self-hosted / boxed installation.

Primary customization paths:
- `/local/modules`
- `/local/components`
- `/local/templates`
- `/local/php_interface`
- `/local/js`
- `/docs`
- `/tests`
- `/migrations`

The assistant must treat this project as a specific on-premise Bitrix24 installation, not as a generic cloud Bitrix24 portal.

## Runtime Requirements

- PHP version must be **8.3 or higher**.
- Generated PHP code must not use constructs incompatible with PHP 8.3+.
- Do not downgrade syntax or dependency requirements below PHP 8.3 unless the user explicitly asks.

## Core Rules

1. Never edit Bitrix core files unless the user explicitly requests an emergency core hotfix.
2. Prefer `/local` over `/bitrix` for all custom code.
3. Before changing behavior, inspect existing local modules, local components, event handlers, agents, CRM custom fields, REST integrations, and project docs.
4. Do not assume cloud-only Bitrix24 behavior is available in this installation.
5. Use D7 APIs where stable and available.
6. Use legacy `CModule` / global APIs only when required by existing code or missing D7 coverage.
7. For REST API work, verify method names, parameters, scopes and response shapes with official documentation. If an official Bitrix24 MCP is available, use it first. If not, use the already configured Perplexity MCP and restrict findings to official Bitrix24 documentation where possible.
8. Use the already configured Qdrant MCP / code index before making architecture-sensitive changes.
9. Never hardcode secrets, webhook URLs, access tokens, user IDs, department IDs, CRM category IDs, stage IDs, or `UF_*` field meanings without checking project context.
10. Always prefer minimal, reviewable diffs.
11. After code changes, propose validation commands.
