# Troubleshooting для AI-агента

## 1. Docker unavailable

Симптом:

```text
Docker is required for runtime certification
```

Действие: не объявлять PASS. Выполнить static checks и пометить runtime BLOCKED.

## 2. MODX boot fatal error

Проверять в порядке:

1. PHP version;
2. required extensions;
3. MODX config;
4. namespace registration;
5. autoloader;
6. xPDO package path;
7. service container registration;
8. lexicon/controller loading.

Не лечить symptom изменением global error handler.

## 3. xPDO model missing

Проверить:

- schema XML;
- package namespace;
- PSR-4 prefix;
- generated `Model` files;
- metadata;
- `addPackage()`;
- generated path.

Не создавать ручной metadata как замену parser без документированного основания.

## 4. Transport Package не устанавливается

Проверить:

- package signature;
- version/release;
- vehicle;
- resolver;
- file source paths;
- install order;
- namespace registration.

Сначала сохранить installer output, затем исправлять root cause.

## 5. API 401/403

Различать:

- 401 = authentication problem;
- 403 = authorization/policy problem.

Проверить token status, expiry, scopes, profile binding, policy.

Не увеличивать scopes «для проверки», если это маскирует проблему.

## 6. Profile isolation failure

Это critical security defect.

Остановить feature work. Проверить все query paths, включая:

- direct lookup by ID;
- job lookup;
- snapshot lookup;
- audit lookup;
- schema/fingerprint lookup;
- approval lookup.

## 7. Duplicate queue execution

Проверить:

- atomic claim;
- unique idempotency key;
- lock/lease;
- transaction boundary;
- retry behavior.

Не добавлять простой `sleep()` как race-condition fix.

## 8. Verification mismatch

Сначала установить, mutation действительно произошла или нет.

Запрещено автоматически retry mutation, если состояние неизвестно и операция не доказана idempotent.

## 9. Rollback не сработал

Проверить:

- snapshot completeness;
- allowed restore fields;
- transaction state;
- TV restoration;
- current resource existence;
- audit;
- policy.

Если rollback сам изменяет состояние, создать отдельный rollback incident.

## 10. Secret leakage

Немедленно:

1. остановить тест;
2. удалить secret из fixture/log output;
3. проверить redaction;
4. проверить audit;
5. считать credentials compromised, если secret реально был опубликован.

Не добавлять secret в git для «воспроизводимости».

## 11. Manager UI показывает данные другого profile

Это critical IDOR/profile isolation issue. Исправление должно быть на server side; UI filtering недостаточно.

## 12. PHPUnit отсутствует

Статус — NOT RUN/BLOCKED. Можно выполнить syntax/static checks, но нельзя писать `tests passed`.

## 13. CI отличается от локальной среды

Зафиксировать:

- PHP version;
- Composer version;
- MODX version;
- MySQL version;
- extensions;
- environment variables.

Воспроизвести CI container локально.

## 14. Миграция уже применена частично

Не запускать blind retry. Сначала определить:

- migration state;
- schema state;
- data state;
- transaction status.

Затем написать safe recovery procedure.

## 15. Как писать incident report

```text
Incident:
Environment:
Release:
First failing step:
Observed error:
Expected behavior:
Root cause:
Security impact:
Data impact:
Fix:
Regression test:
Verification evidence:
Remaining risk:
```
