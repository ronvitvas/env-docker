# No Core Modification Policy

Forbidden edit paths:
- `/bitrix/modules/*`
- `/bitrix/components/bitrix/*`
- `/bitrix/templates/.default/*`
- `/bitrix/js/*`
- `/bitrix/admin/*`
- `/bitrix/tools/*`

These paths may be read for analysis, but must not be edited.

## If a task appears to require core modification

Stop and propose alternatives first:

1. `/local` module
2. event handler
3. custom component
4. component template override
5. local template
6. D7 service class
7. agent
8. REST/local app integration
9. business process or automation rule

Only edit core if the user explicitly says:

- `Разрешаю правку ядра`
- `Разрешаю emergency core hotfix`

Even then:
- explain the risk
- create a minimal patch
- document rollback
- never hide the fact that core was modified
