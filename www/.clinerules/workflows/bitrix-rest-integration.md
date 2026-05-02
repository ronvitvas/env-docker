# Bitrix REST Integration

Use this workflow for REST API, webhooks, local applications, OAuth integrations, and external systems.

## Step 1: Verify documentation

Use exact REST documentation before generating code:

1. Use official Bitrix24 REST MCP if configured.
2. Otherwise use the already configured Perplexity MCP and prefer official Bitrix24 docs.
3. For boxed installations, verify local method availability where possible.

Check:
- method name
- endpoint shape
- parameters
- scopes
- events
- response format
- examples
- errors
- REST 3.0 requirements if relevant

## Step 2: Check whether REST is appropriate

REST is appropriate for external boundaries.

Do not use REST for internal PHP code running in the same Bitrix24 installation unless explicitly required.

## Step 3: Generate integration

Include:
- config via env/options, not hardcoded secrets
- sanitized logging
- error handling
- retry policy for transient failures
- request/response wrapper
- tests or smoke-test instructions

## Step 4: Validate

Run or propose:
- PHP lint
- static analysis
- smoke test on dev portal only
- no production writes without approval

## Step 5: Document

Update docs with:
- methods used
- scopes
- setup steps
- failure modes
- rollback/revocation plan
