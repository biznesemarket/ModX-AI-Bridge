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
| 8 | MCP publish | `approval_id` без `change_id`; в `callTool()` pipeline не получал `approval_id`/`change_id`, поэтому tool всегда отклонялся | повторно закрыто в 0.9.2 (forwarding + `McpPublishApprovalTest`) |
| 9 | `processors/mcp.class.php` | Не было guard'а; principal/scopes и `_client_ip` брались из request | повторно закрыто в 0.9.2 (`AdminProcessor` guard, trusted principal/IP) |
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
| 36 | `RestApi::applicationResult()`, `Queue/Worker` | Сырые `$e->getMessage()` попадают в API-ответ и `error_json` job'а (внутренние/DB-сообщения) | исправлено (0.10.0; generic-сообщения, диагностика redacted в audit/log) |
| 37 | `Model/mysql/IdempotencyKey.php`, `RateLimitBucket.php` | Unique-индексы не включают `profile_id` (`key,principal,operation` / `bucket_key,window_start`) → кросс-профильные коллизии при shared principal | исправлено (0.10.0; `profile_id` первым в индексах, миграция 003, lookup по профилю) |
| 38 | `Api/RestApi::mutate()` / MCP `idempotency_key` | Длина ключа не ограничена, а колонки `varchar(190)` → insert падает 500 на длинном ключе | исправлено (0.10.0; лимит 190 → `400 idempotency_key_invalid`, MCP `maxLength`) |
| 39 | `Validators/RequestValidator`, `Middleware/{Authentication,Authorization,RateLimit}Middleware`, `Services/{Asset,Schema}Service`, `src/Processors/*` стабы | Мёртвый/заглушечный код без вызовов (кроме self-reference) | исправлено (0.10.0; стабы удалены, `static-contract` запрещает возврат) |
| 40 | `Queue/Worker::runOnce()` | Timeout проверяется после завершения handler'а, а не прерывает его; job может быть помечен failed/requeued уже после мутации | исправлено (0.10.0; timeout терминальный, `JobDeadline` проверяется до мутации) |
| 41 | `Services/CacheInvalidationService` | `invalidateResource()` сбрасывает весь `db`-кеш MODX вместо ресурса | исправлено (0.10.0; кеш страницы ресурса + map контекста) |
| 42 | `Manager/OperationsConsoleService::changes()/approvals()` | Возвращают сырой `toArray()` без проекции (в отличие от остальных list-методов) | исправлено (0.10.0; явные проекции, JSON декодируется) |
| 43 | `Manager/OperationsConsoleService` | Конструктор `private modX $modx` без `readonly`; мелкие стилевые несогласованности | исправлено (0.10.0; `readonly`, проекции переписаны) |
| 44 | `SecurityDecisionPipeline::isApprovedForChange()` | Approval не привязан к profile/operation/resource и многоразовый → cross-profile/replay publish через MCP/REST | исправлено (0.9.3) |
| 45 | `processors/mcp.class.php` + `McpServer` handlers | Trusted `channel` не пробрасывался в execution-pipeline → manager-MCP мутации падали `ip_not_allowed` (fail closed) | исправлено (0.9.3) |
| 46 | `ChangeRequestService::create()` | `after_json` строился из сырого input с `published` → post-execution verification падал после коммита остальных полей | исправлено (0.9.3) |
