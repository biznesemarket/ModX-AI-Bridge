# Baseline — Known Defects (Iteration 21)

Register captured during planning review. Items are resolved in the referenced iterations.

| # | Место | Дефект | Статус |
|---|---|---|---|
| 1 | `phpunit.xml` | Отсутствовали сьюты `unit/contract/security`, требуемые CI/скриптами | исправлено в Iteration 21 |
| 2 | `tests/Security/SecurityRegressionTest.php` | Substring-ожидания не совпадали с `config/config.php` | исправлено в Iteration 21 |
| 3 | `tests/Integration/static-contract.php` | Требовался legacy `core/components/aibridge/index.class.php` | исправлено (controllers/) |
| 4 | `composer.json` `lint` | unix-only `find\|xargs`; на Windows гейты выполняются в контейнере | зафиксировано как ограничение |
| 5 | `controllers/*.class.php` | Базовый класс определён в `index.class.php`; MODX 3 грузит action-контроллер изолированно → fatal | исправлено: контроллеры самодостаточны |
| 6 | `Application::securityDecision` | pipeline без `$modx` → publish-approval всегда `approval_invalid` | исправлено |
| 7 | `Authorization` | Отсутствовал `resource.rollback` | исправлено |
| 8 | MCP publish | `approval_id` без `change_id` | Iteration 29 |
| 9 | `processors/mcp.class.php` | Нет аутентификации | Iteration 29 |
| 10 | settings/ConfigFactory | Ключи underscore vs dot; список настроек неполный | исправлено |
| 11 | `migrate.php` | Не подставлялся префикс; два стиля плейсхолдеров | исправлено |
| 12 | `001_initial.sql` | Устарел относительно schema (нет profile_id/queue-полей) | исправлено: пересоздан из схемы, проверен diff-ом |
| 13 | `install-model.resolver.php` | Создавал 9 из 12 классов | исправлено (12 классов) |
| 14 | `_build/build.php` | `newObject('modMenu')` без namespace; bootstrap-resolver не подключён | исправлено |
| 15 | REST | Заглушка, маршрутов `/api/ai/v2/*` нет | исправлено: RestApi + vhost rewrite + тесты |
| 16 | `worker.php` | Placeholder `exit(78)` | исправлено (CLI worker) |
| 17 | `test-queue-concurrency.php` | Legacy путь `core/model/modx/modx.class.php` | исправлено (реальный харнес, forked race PASS) |
| 18 | `test-modx.sh` | PHPUnit без `MODX_ROOT` → интеграция скипалась | исправлено |
| 19 | Manager mutation | `profile_id` не передаётся из процессора | исправлено (процессор + UI) |
| 20 | Тавтологичные тесты | `ApprovalWorkflowSecurityTest`, `ApplicationTest` | исправлено |
| 21 | Версии/статусы | 15/17/18/19; `alpha2` vs `rc1` | исправлено (0.1.0-rc1) |
| 22 | `addPackage` path | Путь metadata подтверждается на runtime | проверено на runtime: корректен |
| 23 | `ModxIntegrationTest` | Namespace path не резолвится | исправлено (`modNamespace::translatePath`) |
| 24 | `entrypoint.sh`/`modx-install.sh` | Подтверждение CLI-установки MODX 3.2.2 | подтверждено: установка PASS |
| 25 | Верификация publish | `after_json` не содержит `published`-поля | Iteration 31 |
| 26 | `TokenManager::issue` | Не сохранялся `profile_id` | исправлено |
| 27 | `OperationsConsoleSecurityTest` | Хрупкие source-text assertions | допустимо, отмечено |
| 28 | Dockerfile | Не хватало libcurl/ftp-расширений для сборки MODX 3.2.2 | исправлено |
| 29 | `RateLimiter`/`IdempotencyService`/`SnapshotService`/`AuditService` | Не писали обязательный `profile_id` (NOT NULL) | исправлено, проверено на runtime |
| 30 | `ResourceExecutionService`/`RollbackService`/`QueueManager` | `getConnection()` использовался как PDO (обёртка xPDO 3.2) | исправлено, проверено на runtime |
| 31 | `IpAllowlist` | Пустой allowlist разрешал всем | исправлено: fail closed |
| 32 | Apache mapping | `Alias` не отдаёт PATH_INFO; server-context rewrite не наследуется vhost | исправлено vhost rewrite |
| 33 | Legacy class names | `modResource`/`modTemplateVar` и инспекторы | заменены на FQCN MODX 3 |
| 34 | `_build/elements/settings.php` | `fromArray()` chained off `new` returns void → array of `null` → all 19 `aibridge_*` settings skipped by `build.php` and never packaged | исправлено (0.1.1) |
| 35 | `sdk/{php,typescript}` `waitForJob()` | Читал только `data.status`/`status`, а реальный ответ `GET /api/ai/v2/jobs/{id}` — `{success, job:{status}}` → поллинг никогда не видел терминального статуса и падал в таймаут. Найдено live HTTP E2E | исправлено (Iteration 49), закреплено runtime-тестами |
