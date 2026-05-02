# Testing and Validation

After editing PHP code, propose or run these checks depending on approval settings:

```bash
make -f ai-workspace/Makefile.ai php-version
make -f ai-workspace/Makefile.ai lint
make -f ai-workspace/Makefile.ai static
make -f ai-workspace/Makefile.ai ai-index
```

For changed files, at minimum run:

```bash
php -l path/to/changed.php
```

Mandatory process rule for PHP edits:

- after **each** patch affecting `*.php`, run `php -l` for every changed PHP file **before** making the next patch
- if lint fails, stop further edits and fix syntax first
- do not continue with new refactors while syntax is broken

For REST integrations:

- verify method docs first
- add a dry-run or smoke test when safe
- do not test against production without explicit approval

For DB changes:

- create reversible migrations
- document rollback
- avoid destructive SQL
- never run write SQL on production without explicit approval
