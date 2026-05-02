# Security Rules

Check every generated or modified code path for:

- hardcoded webhook URLs
- access tokens / refresh tokens / client secrets in repository
- raw request input without validation
- SQL concatenation
- missing permission checks
- unsafe file upload
- `eval`, `assert`, `create_function`
- `unserialize` on untrusted input
- direct core modification
- excessive agent frequency
- missing CSRF/session checks in admin pages
- leaking PII in logs

## Secrets

Never put secrets into code. Use one of:

- environment variables
- Bitrix module options with appropriate access controls
- deployment secret manager
- server-side config outside repository

## Logging

Logs must be sanitized. Never log:

- webhook URLs
- tokens
- passwords
- full personal data payloads
- full CRM comments containing personal data unless explicitly required and approved
