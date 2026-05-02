# Bitrix Analyze Task

Use this before implementing any non-trivial Bitrix24 change.

## Step 1: Understand the request

Restate:
- business goal
- technical goal
- affected entity: CRM, tasks, users, bizproc, component, module, REST, etc.

## Step 2: Search project context

Use available tools:
- Qdrant MCP/code index
- file search
- grep/search in repository
- local index in `ai-workspace/.ai/bitrix-index` if available

Search for:
- local modules
- local components
- event handlers
- agents
- CRM fields
- REST integrations
- business process docs

## Step 3: Check architecture decision

Choose one:
- REST for external boundary
- local module/service for internal business logic
- event handler for event-based behavior
- agent/cron for scheduled processing
- component for UI
- component template only for display changes

## Step 4: Check constraints

Confirm:
- no core edits
- PHP >= 8.3
- module availability
- on-premise caveats
- permissions
- caching
- performance
- rollback path

## Step 5: Produce implementation plan

Return:
- files to read first
- files to change
- risks
- validation commands
- rollback plan

Do not edit files during this workflow unless explicitly asked.
