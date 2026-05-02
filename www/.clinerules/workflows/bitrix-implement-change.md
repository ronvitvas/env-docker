# Bitrix Implement Change

Use this workflow when the user asks to implement a change.

## Step 1: Analyze first

Follow `bitrix-analyze-task.md`.

## Step 2: Prepare minimal diff

Before editing:
- identify exact files
- avoid broad rewrites
- avoid formatting-only changes in unrelated files
- avoid core paths

Patch safety guardrails:
- prefer small, sequential patches over one large rewrite
- if patch tool reports fuzzy/ambiguous apply, immediately re-read full target file and run syntax check before next patch
- do not stack multiple risky patches without intermediate validation

## Step 3: Implement

Rules:
- use PHP 8.3+
- prefer D7 APIs
- keep business logic in local module/service classes
- keep component templates presentation-only
- validate inputs
- sanitize logs
- add error handling

## Step 4: Validate

Run or propose:

```bash
make -f ai-workspace/Makefile.ai php-version
make -f ai-workspace/Makefile.ai lint
make -f ai-workspace/Makefile.ai static
make -f ai-workspace/Makefile.ai ai-index
```

Mandatory in-progress validation (not only final):
- after each PHP patch, run `php -l` on every changed PHP file
- if any file fails lint, stop and fix syntax immediately
- only continue with next patch after clean lint

Critical-file extra checks (for installer/admin entrypoints like `install/index.php`, `admin/*.php`, `options.php`):
- re-open full file after each patch and verify method boundaries are intact
- verify there are no duplicate method declarations
- verify exception paths return explicit failure where caller expects strict checks

## Step 5: Report

Return:
- changed files
- summary
- validation results
- rollback plan
- risks / follow-up checks
