# E2E Test Environment Configuration

## Runtime URLs

All E2E/browser_action testing must use the following address:

| Parameter | Value |
|---|---|
| Port | **8588** (NOT 8589) |
| Protocol | `http` |
| Primary hostname | `dev.bx:8588` |

## Base URL

```
http://dev.bx:8588
```

## Common Admin URLs

| Page | URL |
|---|---|
| Модерация идей | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_moderation.php` |
| Идеи список | `http://dev.bx:8588/bitrix/admin/uds_ideabank2.php` |
| Категории | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_categories.php` |
| Статусы | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_statuses.php` |
| Награды | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_rewards.php` |
| Конкурсы | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_contests.php` |
| Челленджи | `http://dev.bx:8588/bitrix/admin/uds_ideabank2_challenges.php` |

## Rules

1. **Always use `http://dev.bx:8588`** for E2E/browser testing. Do not use `localhost:8588` unless the user explicitly asks for fallback testing.
2. **Always use port 8588** for `browser_action` launch URLs. Never use 8589.
3. **Always use `http://` protocol** (not `https://`) for local development server.
4. When testing admin pages, use full path: `http://dev.bx:8588/bitrix/admin/{filename}.php`
5. For runtime/E2E checks, use the browser (`browser_action`), not `curl`, because UI behavior, redirects, cookies, authorization and CAPTCHA must be verified as a real user scenario.
6. If authentication is required for E2E tests and the user has already provided test credentials, the assistant MUST attempt login before reporting access issues.
7. If a CAPTCHA is shown during browser testing, the assistant MUST inspect the browser screenshot, recognize the CAPTCHA visually when possible, fill it into the form, and continue the test. If the CAPTCHA is unreadable or blocks automation, report the exact blocker with a screenshot instead of replacing the browser check with `curl`.

## Docker context

The development server runs inside a Docker container. The host port 8588 is mapped to the internal web server port.