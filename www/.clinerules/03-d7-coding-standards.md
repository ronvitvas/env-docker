# Bitrix D7 Coding Standards

Use PHP 8.3+.

Prefer:

- `declare(strict_types=1);` for new standalone PHP classes when compatible with the project.
- `\Bitrix\Main\Loader`
- `\Bitrix\Main\Application`
- `\Bitrix\Main\Config\Option`
- `\Bitrix\Main\Type\DateTime`
- `\Bitrix\Main\ORM\Data\DataManager` where appropriate
- `\Bitrix\Main\EventManager` for event handlers
- namespaces matching module ID and `/lib` structure
- dependency-free service classes for business logic
- DTO/value objects for complex data where appropriate

Avoid:

- direct SQL when ORM or module API exists
- modifying core files
- global state unless existing Bitrix API requires it
- `echo`, `die`, `var_dump` in production code
- `unserialize` on untrusted input
- `eval`, `assert`, dynamic includes based on request data
- raw `$_REQUEST`, `$_GET`, `$_POST` without validation

When using modules:

```php
if (!\Bitrix\Main\Loader::includeModule('crm')) {
    throw new \RuntimeException('CRM module is not available');
}
```

Generated code must include error handling and must avoid hiding failures silently.
