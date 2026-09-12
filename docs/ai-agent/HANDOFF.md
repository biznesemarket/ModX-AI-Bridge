# Handoff — ModX AI Bridge (Iterations 21–39: RC `0.1.0-rc1`; Stable BLOCKED)

Дата: 2026-09-12
Состояние: runtime-сертификация в процессе; цель — `Stable` (`0.1.0-rc1`).

## 1. Репозиторий

- Локально: `E:\projects\ModX AI Bridge` (Windows, Docker Desktop).
- Remote: `https://github.com/biznesemarket/ModX-AI-Bridge`, branch `main`, рабочее дерево чистое.
- Коммиты:

```text
e3cb7f0 Fix deterministic model regeneration, package install diagnostics and runtime smoke settings
f589021 CI: run shell gates via bash and mark shell scripts executable
2289972 Iterations 21-29: runtime certification, REST/MCP transports, package and migration fixes
6ad8cc1 Initial import: ModX AI Bridge (iterations 1-19)
```

- CI (коммит `e3cb7f0`): `Quality Gates` — success (deterministic + modx-runtime, 5m23s);
  `MODX Integration` — success (полный runtime-прогон, 4m43s).
- Локальный стек: `modxaibridge-modx-1` (http://localhost:8080), `modxaibridge-db-1`
  (MySQL 8), MODX 3.2.2-pl установлен с нуля.

## 2. Что сделано (Iterations 21–39)

- **21 Baseline:** сьюты `unit/contract/security/integration` в `phpunit.xml`; переписаны
  тавтологичные тесты; версия сведена к `0.1.0-rc1`; реестр дефектов —
  `docs/ai-agent/baseline/known-defects.md`.
- **22 Runtime:** Docker-образ дополнен `libcurl4-openssl-dev`, `ext-ftp`, `libssl-dev`, `pcntl`;
  MODX 3.2.2-pl ставится legacy CLI-конфигом; Apache vhost rewrite для `/api/ai/v2/*`
  (`docker/modx/apache/000-default.conf`, `aibridge-api.conf`), nginx-сниппет — `deploy/nginx/aibridge-api.conf`.
- **23 Модель:** xPDO-модель генерируется детерминированно и закоммичена
  (`src/Model/*`, `src/Model/mysql/*`, `metadata.mysql.php`); Manager-контроллеры самодостаточны;
  package resolver-ы работают через `$transport->xpdo`; меню/настройки заполняют PK; resolver создаёт
  все 12 таблиц схемы.
- **24 Гейты:** `verify-static-contract`, `verify-generated-model`, unit/contract/security, установка
  Transport Package и `verify-modx-runtime` — PASS.
- **25 Миграции:** runner переписан (raw PDO, без транзакций вокруг DDL, подстановка `[[+prefix]]`
  и `{PREFIX}`, идемпотентный ledger `{prefix}aibridge_migrations`); `001_initial.sql` пересоздан из
  схемы и проверен diff-ом (`SCHEMA MATCH`).
- **26 Очередь:** реальный `core/components/aibridge/worker.php` (claim-loop, stale lease, graceful
  shutdown) + `scripts/test-queue-concurrency.php` (atomic claim, lease recovery, forked race — один
  победитель).
- **27–29 Manager/REST/MCP:** Manager-mutation требует явный активный `profile_id`; реализованы
  `AIBridge\Api\RestApi` и MCP-endpoint (`POST /api/ai/v2/mcp`) с порядком
  auth → rate limit → IP allowlist → scopes/authorization → policy → approval → idempotency →
  Application; мутации отвечают `202 {job_id}`; интеграционные тесты
  `tests/Integration/RestApiTest.php` (12) и `McpTransportTest.php` (6).

### Исправленные runtime-баги (не повторять)

- `SecurityDecision` не имел методов `allowed()`/`code()` (production-код вызывал их).
- xPDO 3.2 `getConnection()` — обёртка, raw PDO в `$connection->pdo` (queue/execution/rollback/rate-limit).
- Обязательный `profile_id` не писался в `rate_limits`, `idempotency`, `snapshots`, `audit`.
- Legacy-имена `modResource`/`modTemplateVar` и инспекторы → FQCN MODX 3.
- `ip_allowlist`: пустой список теперь fail-closed (запрет).
- `composer build-schema` c `--update` мог записать `[+class-header+]` — генерация всегда с нуля,
  `verify-generated-model` проверяет содержимое.
- Устаревший кеш `system_settings` для web-процессов → `cacheManager->refresh()`.

## 3. Итог: Release Candidate готов, Stable BLOCKED

Выполнено (Iterations 30–38): **30** mutation E2E + rollback (Defect #7/#25 закрыты); **31** approval
workflow E2E (`draft→submit→reject`, approval↔change, execute-only-approved, terminal states); **32**
multi-site изоляция (активный профиль токена, snapshot/workflow profile ownership, IDOR-матрица); **33**
security regression (auth bypass, scope escalation, profile escape, rate limit, replay, secret leakage,
malformed JSON 400, oversized 413); **34** observability/readiness (`X-Request-Id`, `/ready`, worker audit
`request_id`, лог-redaction); **35** PHP SDK + OpenAPI-выравнивание (TypeScript — см. ниже); **36**
upgrade/recovery drill; **37** performance & limits; **38** Release Candidate `0.1.0-rc1`. Детали — в
`docs/testing/iteration-30..38-*.md`.

Осталось до Stable (оба обязательны):
1. TypeScript SDK — **BLOCKED**: нет `node`/`npm`; после установки тулчейна
   `bash scripts/sdk-typescript-check.sh` должен напечатать `TYPESCRIPT SDK: PASS`.
2. Однокомандный `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` на host/CI с Docker
   (внутри контейнера `composer test-modx` не запускается — нет Docker CLI).

Тег `v0.1.0` **не создан**; статус — NOT STABLE. Evidence: `docs/release/stable-status.md`,
`docs/testing/iteration-39-stable-certification.md`.

Полный план: `C:\Users\potap\.local\share\kilo\plans\1789231022815-iteration-implementation-plan.md` (v2).
Статусы и evidence: `docs/release/FINAL-INTEGRATION-STATUS.md`, `docs/testing/STATUS.md`,
`docs/testing/iteration-21-26-runtime-certification.md`.

## 4. Как работать в этой среде

Windows-хост без PHP/Composer/POSIX-shell — все команды выполняются внутри контейнера.
После любых правок исходников **обязательно пересобрать и переустановить пакет** (тесты и runtime
используют установленную копию, а не workspace).

```powershell
# поднять стек (если не запущен)
docker compose up -d --build modx

# пересборка + переустановка пакета (после правок core/assets/_build)
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php >/dev/null && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.1.0-rc1.transport.zip'

# тесты
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html vendor/bin/phpunit 2>&1 | tail -5'
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && vendor/bin/phpunit --testsuite unit,contract,security 2>&1 | tail -3'

# модель (только при изменении schema)
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html composer build-schema && composer verify-generated-model'

# runtime-верификация / очередь / REST
docker compose exec -T modx bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/verify-modx-runtime.php'
docker compose exec -T modx bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/test-queue-concurrency.php'
docker compose exec -T modx bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/configure-test-runtime.php; curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api/ai/v2/capabilities'

# SQL (клиент MariaDB в контейнере modx; нужен --skip-ssl)
docker compose exec -T modx bash -lc 'mysql -h db -umodx -pmodx --skip-ssl modx -e "SELECT COUNT(*) FROM modx_aibridge_jobs"'
```

Ограничения и правила:

- тестовый рантайм настраивается только через `scripts/configure-test-runtime.php`
  (REST/MCP on, HTTPS off, allowlist `["127.0.0.1"]`, высокий rate limit);
- пустой `aibridge_ip_allowlist` = запрет всем; `delete`/`publish` по умолчанию запрещены
  (approval-required для publish);
- `composer lint` — unix-only (`find|xargs`), выполнять только в контейнере;
- коммит/пуш — только по явной команде; git identity настроена локально
  (`githubcms` + noreply email), `composer.lock` закоммичен.

## 5. Следующий шаг

Для достижения Stable: (1) установить Node.js/npm и прогнать `bash scripts/sdk-typescript-check.sh`
(ожидается `TYPESCRIPT SDK: PASS`); (2) выполнить
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` на host/CI с Docker, PHP и Composer и
убедиться, что все гейты проходят в одном запуске; (3) только после этого создать тег `v0.1.0`.
До выполнения (1)–(2) статус остаётся NOT STABLE, тег не создавать.
