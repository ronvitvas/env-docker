# Bitrix Review Diff

Use this workflow to review code changes.

## Checklist

1. Does the diff edit `/bitrix` core?
2. Does the diff add secrets?
3. Does it require PHP >= 8.3 and remain compatible?
4. Does it use REST only where appropriate?
5. Does it use `/local/modules` for business logic?
6. Does it keep component templates presentation-only?
7. Are module dependencies checked with `Loader::includeModule`?
8. Are inputs validated?
9. Are permission checks present?
10. Are SQL queries safe?
11. Are logs sanitized?
12. Is there a rollback plan?
13. Are validation commands provided?

## Output format

- Critical issues
- High issues
- Medium issues
- Low issues
- Suggested patch plan
- Validation commands
