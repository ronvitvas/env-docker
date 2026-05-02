# Performance Rules

Bitrix24 boxed installations often contain large CRM datasets, tasks, comments, files, and user fields.

Before implementing code that loops over entities:

1. Check expected data size.
2. Use pagination.
3. Select only required fields.
4. Avoid N+1 queries.
5. Consider cache invalidation.
6. Avoid heavy logic inside frequently fired event handlers.
7. Avoid agents that process unlimited data in one run.
8. Use batch processing for mass operations.

For agents:

- process limited batches
- store cursor/progress
- log failures safely
- avoid running every minute unless justified
- make the operation idempotent
