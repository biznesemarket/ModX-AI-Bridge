# Handoff — ModX AI Bridge (Iterations 21–35 завершены)

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

## 2. Что сделано (Iterations 21–29)

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

## 3. Что осталось (Iterations 36–39)

1. **30 ✅ Mutation E2E + rollback:** Manager update/preview/delete/publish через approval+queue;
   verification mismatch → FAILED (`NonRetryableJobException`); rollback drill (поля + TV) PASS;
   автопересоздание удалённых запрещено. Defect #7 закрыт на уровне `Authorization` **и**
   `ResourceRollbackProcessor` (principal/channel/permission). Defect #25 (publish expected-state) закрыт.
   Evidence: `docs/testing/iteration-30-mutation-rollback.md`.
2. **31 ✅ Approval workflow E2E:** `draft→submit→reject` (терминальность, reason, audit);
   привязка approval↔change (approval нельзя применить к другому change); execute только для `approved`;
   `approve→execute→verify→audit` (completed терминален); approval decision lifecycle; publish без
   approval отклоняется даже для manager. Полная матрица `ChangeState` — unit. Evidence:
   `docs/testing/iteration-31-approval-workflow.md`.
3. **32 ✅ Multi-site изоляция:** `TokenAuthenticator` требует активный профиль токена; snapshot-rollback,
   change-переходы, approval create/decide и approved-execute проверяют profile ownership на сервере;
   матрица Token/Job/Audit/Schema/Fingerprint/Snapshot/Change/Approval + IDOR. Evidence:
   `docs/testing/iteration-32-multi-site-isolation.md`.
4. **33 ✅ Security regression на runtime:** auth bypass (scheme/unknown/revoked/expired), scope escalation,
   profile escape через body `profile_id`, rate limit 429 + `Retry-After`, idempotency conflict/replay,
   отсутствие утечки секретов в response/audit; front controller → `400 invalid_json`, oversized → `413`.
   Evidence: `docs/testing/iteration-33-security-regression.md`.
5. **34 ✅ Observability/readiness:** inbound `X-Request-Id` propagation + echo, structured error envelope,
   worker audit `request_id`, публичный `GET /ready` (200/503), redaction свободного текста в worker-логах,
   расширенный `scripts/readiness.php`. Evidence: `docs/testing/iteration-34-observability.md`.
6. **35 ⚠ SDK:** PHP PASS (10 тестов, OpenAPI выровнен: добавлены `/health`, `/ready`, `/profiles`,
   `/resources/preview`, `/resources/{id}/publish`, `202` + `Idempotency-Key`); TypeScript **BLOCKED**
   (`node`/`npm` недоступны, `tsc` не запускался — не PASS). Evidence:
   `docs/testing/iteration-35-sdk-certification.md`.
7. **36 Upgrade/recovery drill:** backup → upgrade → migrate → smoke → rollback → restore.
8. **37 Performance & limits.**
9. **38 Release Candidate:** артефакт `0.1.0-rc1` + SHA-256.
10. **39 Stable certification:** `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` + тег `v0.1.0`.
    Важно: TypeScript BLOCKED ⇒ Stable невозможен, пока не поднят Node-тулчейн.

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

Начать с **Iteration 36** (upgrade/recovery drill: backup → upgrade → migrate → smoke → rollback →
restore), затем **Iteration 37** (performance & limits). TS SDK остаётся BLOCKED до появления Node.
Работать по плану v2; каждый шаг закрывать отчётом по формату `AGENTS.md` §7 с фактическими результатами
гейтов (PASS/FAIL/BLOCKED), затем коммит + push.
