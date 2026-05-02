# Bitrix24 Architecture Decision Rule

This is a Bitrix24 self-hosted / boxed installation.

The assistant must choose the implementation approach according to this rule:

> REST API is for external boundaries.  
> Local modules are for internal business logic.  
> Components are for UI.  
> Component templates are for presentation only.

## Use REST API when

Use REST API only when the task involves:

- external integrations
- incoming webhooks
- outgoing webhooks
- local applications
- OAuth integrations
- external systems such as 1C, ERP, CRM middleware, BI, telephony, website, mobile app
- cross-system communication
- public or private API boundary
- integration code running outside the Bitrix24 PHP runtime

If REST is used, the assistant must:

1. Verify method names, parameters, scopes, events, examples, and response format.
2. Use official Bitrix24 REST documentation. Prefer official Bitrix24 MCP if configured; otherwise use the already configured Perplexity MCP and official docs.
3. Remember that on-premise Bitrix24 may differ from cloud Bitrix24.
4. Never invent REST methods, fields, scopes, events, or payloads.
5. Never hardcode webhook URLs, access tokens, refresh tokens, client secrets, or application secrets.
6. Add error handling, logging, and retry policy where appropriate.

## Do not use REST when

Do not call the same Bitrix24 installation through REST from internal PHP code unless the user explicitly requires REST or there is a strong architectural reason.

Avoid REST for:

- internal event handlers
- agents
- local CRM automation
- local components reading data from the same portal
- internal business logic
- mass operations inside the same installation
- code that already runs inside the Bitrix PHP runtime

For internal Bitrix24 code, prefer local PHP APIs, D7, service classes, ORM, events, and agents.

## Use local modules when

Use `/local/modules/vendor.module` for:

- business logic
- service classes
- event handlers
- agents
- ORM tables
- module options
- migrations
- admin pages
- integration backend
- reusable domain logic
- code used by multiple components
- long-lived customizations

Rules for local modules:

1. Use `/local/modules`, not `/bitrix/modules`.
2. Keep business logic in `/lib`.
3. Use namespaces matching module structure.
4. Check module availability with `\Bitrix\Main\Loader::includeModule`.
5. Register persistent event handlers during module installation.
6. Unregister event handlers during uninstall.
7. Keep migrations reversible.
8. Avoid raw SQL when ORM or module API exists.
9. Never put secrets into module files.

## Use components when

Use `/local/components/vendor/name` for:

- pages
- widgets
- forms
- lists
- reports
- custom UI blocks
- AJAX UI
- rendering data prepared by local services

Rules for components:

1. Components may orchestrate UI logic.
2. Components may call service classes from local modules.
3. Components should not contain heavy business logic.
4. Components should not directly implement complex CRM/business rules.
5. Components should not call REST to access the same local portal unless explicitly justified.

## Use component templates only for presentation

Component templates must contain:

- HTML
- minimal PHP for rendering
- escaping
- layout
- frontend initialization
- CSS/JS assets

Component templates must not contain:

- business logic
- SQL queries
- CRM mutations
- REST calls
- event registration
- agent registration
- module installation logic
- permission-changing logic
- secrets
- raw request processing without validation

## Decision order

When implementing a Bitrix24 task, evaluate in this order:

1. Is it an external integration?
   - yes: REST/local app/webhook may be appropriate.
   - no: continue.
2. Does it require internal business logic?
   - yes: use local module/service class.
3. Does it require event-based behavior?
   - yes: use local module event handler.
4. Does it require scheduled processing?
   - yes: use agent/cron via local module.
5. Does it require user interface?
   - yes: use local component.
6. Does it only require display changes?
   - yes: use component template override.
7. Does it appear to require core modification?
   - stop and propose a `/local` alternative first.
