# Bitrix Update Local Index

Use this workflow after significant code changes or before broad analysis.

Run:

```bash
make -f ai-workspace/Makefile.ai ai-index
make -f ai-workspace/Makefile.ai qdrant-docs
```

This creates:

- `ai-workspace/.ai/bitrix-index/portal.json`
- `ai-workspace/.ai/bitrix-index/modules.json`
- `ai-workspace/.ai/bitrix-index/components.json`
- `ai-workspace/.ai/bitrix-index/events.json`
- `ai-workspace/.ai/bitrix-index/agents.json`
- `ai-workspace/.ai/qdrant-payloads/codebase.jsonl`

The project already has Qdrant MCP configured. Use the generated JSONL only if your current Qdrant ingestion workflow expects prepared documents.
