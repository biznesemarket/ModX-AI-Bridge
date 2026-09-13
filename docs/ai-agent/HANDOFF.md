# Handoff — ModX AI Bridge (Stable `0.6.0`)

Дата: 2026-09-13
Состояние: **STABLE `0.6.0`** (tag `v0.6.0`, GitHub Release опубликован с 3 ассетами). `main` = `cb90ba0`
(релизная документация `0.6.0`), дерево чистое.

## 1. Репозиторий

- Локально: `E:\projects\ModX AI Bridge` (Windows, Docker Desktop, Node.js 24, Git Bash).
- Remote: `https://github.com/biznesemarket/ModX-AI-Bridge`, branch `main`.
- `main` = `cb90ba0bfaca27d5019cad31b7db7205b5c1ac68` (синхронизирован с origin; релизные code-коммиты
  `c5b886b`/`bb3f621`), дерево чистое.
- Теги: `v0.1.0` (`d132c257…`), `v0.1.1` (`2f07e3d6…`), `v0.1.2` (`5695a928…`), `v0.1.3` (`9d3312a8…`),
  `v0.2.0` (`1a8dfa10…`), `v0.3.0` (`4de95181…`), `v0.4.0` (`0e70bd4d…`), `v0.5.0` (`a16fa8e6…`),
  `v0.6.0` (`c7646622…`). GitHub Releases: `v0.6.0` (latest), `v0.5.0`, `v0.4.0`, `v0.3.0`, `v0.2.0`,
  `v0.1.3`, `v0.1.2`, `v0.1.1`, `v0.1.0` — все не prerelease.

Ключевые коммиты (новые сверху):

```text
cb90ba0 Iteration 56: Record Stable 0.6.0 certification      (tag v0.6.0)
bb3f621 Iteration 56: Cut 0.6.0 minor release
c5b886b Iteration 56: Add template-variable filter to resource list
69d7526 Iteration 55: Update handoff for Stable 0.5.0
b91a3ac Iteration 55: Record Stable 0.5.0 certification      (tag v0.5.0)
1796c5d Iteration 55: Cut 0.5.0 minor release
5d22df5 Iteration 55: Add MCP resource template for resource read-back
503dd1a Iteration 54: Update handoff for Stable 0.4.0
2c55b4c Iteration 54: Record Stable 0.4.0 certification      (tag v0.4.0)
07f3c72 Iteration 54: Cut 0.4.0 minor release
c4548e7 Iteration 54: Add filtered resource list API and MCP tool
6125baf Iteration 53: Update handoff for Stable 0.3.0
c6bc5ad Iteration 53: Record Stable 0.3.0 certification      (tag v0.3.0)
dc3bd8a Iteration 53: Cut 0.3.0 minor release
21abdbb Iteration 53: Add template variables to read-back projection
4009414 Iteration 52: Update handoff for Stable 0.2.0
ab125e4 Iteration 52: Record Stable 0.2.0 certification      (tag v0.2.0)
350fc28 Iteration 52: Cut 0.2.0 minor release
a35004e Iteration 51: Add resource read-back API and aibridge_version setting
5fe3d14 Iteration 50: Record Stable 0.1.3 certification      (tag v0.1.3)
93fa4fd Iteration 50: Cut 0.1.3 patch release
6d5099e Iteration 49: TypeScript SDK live HTTP E2E
5fdb98d Iteration 48: Correct 0.1.2 artifact checksum after README packaging
c8ac594 Iteration 48: Record Stable 0.1.2 certification      (tag v0.1.2)
ad92ab5 Iteration 48: Cut 0.1.2 patch release
98a592f Iteration 47: Update handoff for supply-chain pinning closure
93ac2bd Iteration 47: Pin container images and CI actions (supply chain)
8326fda Iteration 46: TypeScript SDK runtime tests
0b97dcd Iteration 45: Deterministic MODX provisioning readiness marker
6780a0a Iteration 44: Record Stable 0.1.1 release and evidence
db949b5 Iteration 44: Fix system settings packaging and cut 0.1.1
1f1005b Iteration 43: Drop directory entries from reproducible transport packages
487a4bf Iteration 43: Reproducible transport package builds
99ae6d7 Iteration 42: CI gate hardening and release-evidence retention
7dc27d0 Iteration 41: Add Stable 0.1.0 release record    (tag v0.1.0)
160a659 Iteration 41: Finalize 0.1.0 Stable transport package
642c681 Iteration 40: mark TypeScript SDK check executable
77bb849 Iteration 40: Unblock TypeScript SDK certification gate
```

> `v0.1.1` указывает на `6780a0a`, `v0.1.2` — на `c8ac594`, `v0.1.3` — на `5fe3d14`, `v0.2.0` — на `ab125e4`,
> `v0.3.0` — на `c6bc5ad`, `v0.4.0` — на `2c55b4c`, `v0.5.0` — на `b91a3ac`, `v0.6.0` — на `cb90ba0`.
> Исторические теги/релизы **не переписывать**.

## 2. Что сделано (Iterations 30–56)

- **30–39** — runtime-сертификация: mutation/rollback E2E, approval workflow, multi-site изоляция,
  security regression, observability, PHP SDK, recovery drill, performance, RC `0.1.0-rc1`.
  Детали: `docs/testing/iteration-30..39-*.md`.
- **40 — TypeScript SDK разблокирован.** Не было `package-lock.json` (`npm ci` не мог пройти); import без
  расширения при NodeNext; ключ идемпотентности выводился как узкий тип; скрипт был `100644`, а вызывается
  как `./` (exit 126). Добавлен `workflow_dispatch` в `release.yml`.
- **41 — `0.1.0`.** Финализация пакета (release-суффикс убран → `aibridge-0.1.0.transport.zip`), тег `v0.1.0`.
- **42 — CI-гигиена.** `Quality Gates` `deterministic` гоняет PHP `sdk` + TypeScript-гейт (pinned Node 24);
  Node 24 actions (`checkout@v7`, `setup-node@v7`, `ramsey/composer-install@v4`); `Release` `certify`
  выгружает `dist/**` как `release-evidence-<sha>`.
- **43 — Воспроизводимость.** `_build/build.php`: детерминированные `guid` для всех vehicle (xPDO иначе
  `md5(uniqid(rand(), true))`; namespace-vehicle создаётся вручную через `registerNamespace(..., false)`);
  нормализация архива (сортировка, удаление directory-entries, фиксированный `SOURCE_DATE_EPOCH`,
  default `315532800`). `scripts/verify-package-reproducibility.php` — шаг 15 в `scripts/test-modx.sh`.
- **44 — Defect #34 + `0.1.1`.** `_build/elements/settings.php` строил настройки через
  `(new modSystemSetting($modx))->fromArray(...)`, но `xPDOObject::fromArray()` возвращает void → массив
  `null` → 19 настроек `aibridge_*` не попадали в пакет. Исправлено; версия поднята до `0.1.1`; пакет
  `aibridge-0.1.1.transport.zip` сертифицирован и опубликован.
- **45 — Провижининг.** Гонка: `entrypoint.sh` копирует MODX `cp -a`, а `test-modx.sh` ждал
  `config.core.php`, который появляется в середине копирования → `setup/config.xml: No such file or
  directory`. Теперь entrypoint создаёт `/var/www/html/.modx-ready` после полного копирования, а
  `test-modx.sh` ждёт этот маркер и явно падает, если его нет.
- **46 — Runtime-тесты TS SDK.** `sdk/typescript/tests/runtime/client.test.mjs` — 11 тестов на `node:test`
  против собранного `dist` с fake `fetch`; `npm test` = `node --test`; без новых зависимостей.
- **47 — Supply-chain pinning.** Образы закреплены по digest (`php:8.2-apache`, `composer:2` в
  `docker/modx/Dockerfile`; `mysql:8.0` в обоих compose), все `uses:` — по 40-символьному commit SHA с
  комментарием тега. Добавлены `.github/dependabot.yml` (недельные `github-actions`/`docker` PR) и гейт
  `scripts/verify-supply-chain-pins.sh` (вызывается из `scripts/quality-gate.sh`). `sha_pinning_required`
  включён на уровне репозитория. Application-код и transport-архив не менялись (sha256 `0.1.1` актуален).
- **48 — `0.1.2`.** Патч-релиз 45–47: версия поднята во всех identity (package `_build/config.inc.php`,
  runtime `/health`/MCP/console/readiness, PHP и TS SDK, `package-lock.json`, `scripts/test-modx.sh`),
  новый раздел CHANGELOG. Гейт зелёный дважды: dispatch `34748277073` (`ad92ab5`) и tag-run `34748489085`
  (`c8ac594`). Артефакт `aibridge-0.1.2.transport.zip` sha256
  `eedd5f643fe97b378f5a4304fb3296b61a88db045d78302887417d88f8cb582d` (139585 bytes), локальная сборка == CI.
  GitHub Release `v0.1.2` (3 ассета, latest). Evidence: `docs/testing/iteration-48-release-0.1.2.md`,
  `docs/release/0.1.2.md`.
  - **Грабля:** README встраивается в manifest пакета. В `0.1.2` README обновлён уже после dispatch-гейта
    (в evidence-коммите), поэтому pre-release хеш `b1b985bf…` ≠ релизный `eedd5f64…`. На следующий релиз:
    обновлять **все** version-identity, включая README, в bump-коммите **до** запуска гейта (как в `0.1.1`).
- **49 — TS live-HTTP E2E.** `scripts/ts-live-runtime.php` (container: test-настройки, профиль `ts-live-e2e`,
  scoped-токен, template, JSON-контекст), `scripts/ts-live-check.sh` (host: gateway в allowlist, `npm ci` +
  build, stop-file worker-loop, `node --test tests/live/live.test.mjs`, restore allowlist в trap),
  11 live-тестов (auth/401, capabilities, profile isolation, schema/fingerprint, contract/validate,
  create+update через реальную очередь, delete/publish-отказы, MCP). Шаг 10b в `test-modx.sh`; в
  `modx-integration.yml` добавлен pinned Node 24. Найден и исправлен **Defect #35**: `waitForJob()` в PHP и
  TS SDK читал только `data.status`/`status`, а реальный ответ `GET /api/ai/v2/jobs/{id}` —
  `{success, job:{status}}` → таймаут; теперь `job.status`, закреплено runtime-тестами.
  Evidence: `docs/testing/iteration-49-live-http-e2e.md`.
- **50 — `0.1.3`.** Патч-релиз 49: версия поднята во всех identity **включая README** в bump-коммите (урок
  0.1.2), гейт зелёный дважды — dispatch `34751162661` (`93fa4fd`) и tag-run `34751481747` (`5fe3d14`), оба
  sha256 `0d66d833…` (139631 bytes), локальная сборка совпала. GitHub Release `v0.1.3` (3 ассета, latest).
  Evidence: `docs/testing/iteration-50-release-0.1.3.md`, `docs/release/0.1.3.md`.
- **51 — Read-back API + `aibridge_version`.** `GET /api/ai/v2/resources/{id}` (новая операция
  `resource.read`, scope `resource:read`, через SecurityDecisionPipeline; `resource_not_found` → 404),
  `Services/ResourceReadService` (whitelisted-проекция, deleted исключены, TVs пока нет), MCP-инструмент
  `resource_read`, `getResource()` в PHP/TS SDK, настройка `aibridge_version` в `_build/elements/settings.php`
  (20 настроек), `/health` и `/ready` читают её с code-fallback. Additive: схем/миграций нет.
  Evidence: `docs/testing/iteration-51-readback-api.md`.
- **52 — `0.2.0`.** Minor-релиз 51: bump всех identity включая README и SDK `User-Agent` (`0.1` → `0.2`),
  гейт зелёный дважды — dispatch `34753117255` (`350fc28`) и tag-run `34753425751` (`ab125e4`), оба sha256
  `b8525cda…` (141585 bytes), локальная сборка совпала. GitHub Release `v0.2.0` (3 ассета, latest).
  Evidence: `docs/testing/iteration-52-release-0.2.0.md`, `docs/release/0.2.0.md`.
- **53 — `0.3.0`.** Minor-релиз 53: TV-поля в read-back. `ResourceReadService` добавляет карту `tvs` (имя TV →
  `string|null`; структурированные значения JSON-кодируются) для TV, привязанных к шаблону ресурса; та же
  операция `resource.read`/scope `resource:read`, без новых роутов/настроек. `resource.read` добавлен в
  `CapabilityCatalog`; TS SDK типизирует проекцию (`ResourceProjection`/`ResourceTvValues`/`ResourceReadResponse`);
  README, `aibridge_version` и SDK `User-Agent` (`0.2` → `0.3`) подняты в bump-коммите. Гейты зелёные дважды —
  dispatch `34756679369` (`dc3bd8a`) и tag-run `34757060107` (`c6bc5ad`), оба sha256
  `9c3e547daace05e226480e3d31fe13e4d11a27ecd154e4eb8ac5b9125d132106` (142077 bytes), локальная сборка
  совпала. GitHub Release `v0.3.0` (3 ассета, latest). Evidence:
  `docs/testing/iteration-53-readback-tvs.md`, `docs/release/0.3.0.md`.
- **54 — `0.4.0`.** Minor-релиз 54: фильтрованный список ресурсов. `ResourceReadService::list()` +
  `GET /api/ai/v2/resources` (операция `resource.list`, scope `resource:read`): summary-проекция (без
  `content`/TV) + `count/total/limit/offset`, фильтры `parent`, `template`, `context_key`, `published`, `q`,
  `limit` (1..100, default 25), `offset`, `sort`, `dir`; невалидные значения → `400 invalid_filter`. Та же
  операция у MCP `resource_list`, `listResources()` в обоих SDK и запись `resource.list` в CapabilityCatalog.
  README, `aibridge_version` и SDK `User-Agent` (`0.3` → `0.4`) подняты в bump-коммите. Гейты зелёные
  дважды — dispatch `34758755913` (`07f3c72`) и tag-run `34759141180` (`2c55b4c`), оба sha256
  `778d061a4c013f19c06e04814f5edd42fbab76f993864dd59634bffc417c864e` (143830 bytes), локальная сборка
  совпала. GitHub Release `v0.4.0` (3 ассета, latest). Evidence:
  `docs/testing/iteration-54-resource-list.md`, `docs/release/0.4.0.md`.
- **55 — `0.5.0`.** Minor-релиз 55: MCP resource template `modx://resource/{id}`. `McpRegistry` получил
  `resourceTemplate()`/`resolveResource()` (извлечение `id`), `resources/read` резолвит шаблоны и отдаёт
  read-back-проекцию под той же операцией `resource.read`/scope `resource:read`; отсутствующий ресурс →
  JSON-RPC `-32002` (`reason: resource_not_found`). Добавлен метод `resources/templates/list` и
  `resourceTemplates()` в PHP/TS MCP-клиентах; `McpServer::capabilities()` отдаёт шаблоны. README,
  `aibridge_version` и SDK `User-Agent` (`0.4` → `0.5`) подняты в bump-коммите. Гейты зелёные дважды —
  dispatch `34761433276` (`1796c5d`) и tag-run `34761844858` (`b91a3ac`), оба sha256
  `48c8b23344fd1d6dcae2c67a529c13d44cb7d1b6b7a837c3e459621e4ff2506d` (144677 bytes), локальная сборка
  совпала. GitHub Release `v0.5.0` (3 ассета, latest). Evidence:
  `docs/testing/iteration-55-mcp-resource-uri.md`, `docs/release/0.5.0.md`.
- **56 — `0.6.0`.** Minor-релиз 56: TV-фильтр в списке. `ResourceReadService::list()` принимает `tv_name`
  (ресурсы с явным значением TV) и `tv_value` (LIKE); `tv_value` без `tv_name` или неизвестный `tv_name` →
  `400 invalid_filter`, известный TV без совпадений → пустой результат. Тот же фильтр у MCP `resource_list`
  и в OpenAPI. Найден и обойдён xPDO-баг: частичный `select()` на коллекции теряет PK и вызывает
  неограниченную lazy-загрузку (memory exhaustion). README, `aibridge_version` и SDK `User-Agent`
  (`0.5` → `0.6`) подняты в bump-коммите. Гейты зелёные дважды — dispatch `34762964883` (`bb3f621`) и
  tag-run `34763292926` (`cb90ba0`), оба sha256
  `19c1398bd4461b24df660ea87db41df365dec07a7b133ce0c4bad18bce53808d` (145059 bytes), локальная сборка
  совпала. GitHub Release `v0.6.0` (3 ассета, latest). Evidence:
  `docs/testing/iteration-56-list-tv-filter.md`, `docs/release/0.6.0.md`.

## 3. Доказательства

- Release/status: `docs/release/0.6.0.md`, `docs/release/0.5.0.md`, `docs/release/0.4.0.md`,
  `docs/release/0.3.0.md`, `docs/release/0.2.0.md`, `docs/release/0.1.3.md`, `docs/release/0.1.2.md`,
  `docs/release/0.1.1.md`, `docs/release/0.1.0.md`, `docs/release/stable-status.md`,
  `docs/release/FINAL-INTEGRATION-STATUS.md`.
- Testing: `docs/testing/STATUS.md`, `docs/testing/iteration-40-typescript-sdk-unblock.md`,
  `iteration-41-stable-release.md`, `iteration-44-settings-packaging-0.1.1.md`,
  `iteration-45-provisioning-readiness.md`, `iteration-46-typescript-runtime-tests.md`,
  `iteration-47-supply-chain-pinning.md`, `iteration-48-release-0.1.2.md`,
  `iteration-49-live-http-e2e.md`, `iteration-50-release-0.1.3.md`, `iteration-51-readback-api.md`,
  `iteration-52-release-0.2.0.md`, `iteration-53-readback-tvs.md`, `iteration-54-resource-list.md`,
  `iteration-55-mcp-resource-uri.md`, `iteration-56-list-tv-filter.md`.
- Дефекты: `docs/ai-agent/baseline/known-defects.md` (#34 закрыт в 0.1.1, #35 закрыт в Iteration 49).
- Пакет/сборка: `docs/development/transport-package.md`, `docs/testing/ci-gates.md`.

Сертификационные прогоны (`STABLE certification gates passed.`):

```text
642c681 run 34715583238   (TS gate cleared)
160a659 run 34716441523   (0.1.0 package)
7dc27d0 run 34717494266   (tag v0.1.0)
1f1005b run 34743048978   (reproducible)
db949b5 run 34743692154   (0.1.1 dispatch)
6780a0a run 34744125531   (tag v0.1.1)
98a592f run 34747385780   (Iteration 47 hardening)
ad92ab5 run 34748277073   (0.1.2 dispatch)
c8ac594 run 34748489085   (tag v0.1.2)
93fa4fd run 34751162661   (0.1.3 dispatch)
5fe3d14 run 34751481747   (tag v0.1.3)
350fc28 run 34753117255   (0.2.0 dispatch)
ab125e4 run 34753425751   (tag v0.2.0)
dc3bd8a run 34756679369   (0.3.0 dispatch)
c6bc5ad run 34757060107   (tag v0.3.0)
07f3c72 run 34758755913   (0.4.0 dispatch)
2c55b4c run 34759141180   (tag v0.4.0)
1796c5d run 34761433276   (0.5.0 dispatch)
b91a3ac run 34761844858   (tag v0.5.0)
bb3f621 run 34762964883   (0.6.0 dispatch)
cb90ba0 run 34763292926   (tag v0.6.0)
```

CI на `0b97dcd` (`Quality Gates` 34745076673, `MODX Integration` 34745076672) и на `8326fda`
(`Quality Gates` 34745267416: `tests 11 / pass 11 / fail 0`, `TYPESCRIPT SDK: PASS`;
`MODX Integration` 34745267266) — success. Очередь CI пуста.

Полные гейты `Release` 2026-09-13: `bb3f621` run `34762964883` (0.6.0 dispatch: live HTTP E2E PASS,
`PACKAGE REPRODUCIBILITY: PASS`, sha256 `19c1398b…`, `OK (139 tests, 732 assertions)`); tag `v0.6.0`
(`cb90ba0`) run `34763292926` — тот же sha256. Ранее: `1796c5d` run `34761433276` (0.5.0 dispatch,
sha256 `48c8b233…`, `OK (138 tests, 713 assertions)`); tag `v0.5.0` (`b91a3ac`) run `34761844858`;
`07f3c72` run `34758755913` (0.4.0 dispatch, sha256 `778d061a…`, `OK (135 tests, 700 assertions)`);
tag `v0.4.0` (`2c55b4c`) run `34759141180`; `dc3bd8a` run `34756679369` (0.3.0 dispatch, sha256 `9c3e547d…`,
`OK (130 tests, 663 assertions)`); tag `v0.3.0` (`c6bc5ad`) run `34757060107`; `350fc28` run `34753117255`
(0.2.0 dispatch, sha256 `b8525cda…`, `OK (129 tests, 650 assertions)`); tag `v0.2.0` (`ab125e4`) run
`34753425751`; `93fa4fd` run `34751162661` (0.1.3 dispatch, sha256 `0d66d833…`); tag `v0.1.3` (`5fe3d14`) run
`34751481747`; `98a592f` run `34747385780` (Iteration 47 hardening, `SUPPLY CHAIN PINS: PASS`); `ad92ab5` run
`34748277073` (0.1.2 dispatch → pre-release `b1b985bf…`); tag `v0.1.2` (`c8ac594`) run `34748489085` (sha256
`eedd5f64…`). Evidence-артефакты `release-evidence-{…,bb3f621,cb90ba0}…` (90 дней). Репозиторий:
`sha_pinning_required=true`.

Артефакты: `aibridge-0.6.0.transport.zip` sha256
`19c1398bd4461b24df660ea87db41df365dec07a7b133ce0c4bad18bce53808d` (145059 bytes; GitHub Release `v0.6.0`,
digest ассета совпадает; локальная сборка == CI == tag-run). `aibridge-0.5.0.transport.zip` sha256
`48c8b23344fd1d6dcae2c67a529c13d44cb7d1b6b7a837c3e459621e4ff2506d` (144677 bytes; Release `v0.5.0`);
`aibridge-0.4.0.transport.zip` sha256 `778d061a4c013f19c06e04814f5edd42fbab76f993864dd59634bffc417c864e`
(143830 bytes; Release `v0.4.0`); `aibridge-0.3.0.transport.zip` sha256 `9c3e547d…` (142077 bytes);
`aibridge-0.2.0.transport.zip` sha256 `b8525cda…` (141585 bytes); `aibridge-0.1.3.transport.zip` sha256
`0d66d833…` (139631 bytes); `aibridge-0.1.2.transport.zip` sha256 `eedd5f64…`; `aibridge-0.1.1.transport.zip`
sha256 `26ccdee1…`; `aibridge-0.1.0.transport.zip` sha256 `859c6599…` (историч.).

Iteration 56 (локально, `0.6.0`): `unit,contract,security` 63, `sdk` 11, TS 14, integration 65, live E2E 15
(включая TV-фильтр REST+MCP), reproducibility `19c1398b…`, `SUPPLY CHAIN PINS: PASS`; push-CI на `bb3f621`
(`Quality Gates` 34762960164, `MODX Integration` 34762960169) — success. Ранее Iteration 55: integration 64,
reproducibility `48c8b233…`.

## 4. Как работать в этой среде

Windows-хост: Docker + Node.js + Git Bash, но **нет PHP/Composer**. Полный гейт — на CI (`ubuntu-latest`);
локально PHP-команды — внутри контейнера `modx`. Тесты/runtime используют установленную копию пакета, а не
workspace. После правок `core/`, `assets/`, `_build/` — пересобрать и переустановить:

```powershell
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php >/dev/null && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.6.0.transport.zip'
```

Полезные команды:

```powershell
# полный сертификационный гейт на CI (Docker+PHP+Composer+Node)
gh workflow run release.yml --ref main
gh run watch <run-id> --exit-status

# воспроизводимость пакета (2 сборки, сравнение sha256)
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/verify-package-reproducibility.php'

# TypeScript SDK (build + runtime-тесты) локально
& "C:\Program Files\Git\bin\bash.exe" -lc 'cd "/e/projects/ModX AI Bridge" && bash scripts/sdk-typescript-check.sh'

# TypeScript SDK live HTTP E2E (нужен поднятый стек и Node на хосте; создаёт профиль/токен/ресурсы)
& "C:\Program Files\Git\bin\bash.exe" -lc 'cd "/e/projects/ModX AI Bridge" && bash scripts/ts-live-check.sh'

# PHP deterministic внутри контейнера
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && composer validate --no-check-publish --strict && composer lint && composer verify-static-contract && composer test -- --testsuite unit,contract,security && composer test -- --testsuite sdk'

# runtime-верификация / SQL (клиент MariaDB; нужен --skip-ssl)
docker compose exec -T modx bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/verify-modx-runtime.php'
docker compose exec -T modx bash -lc 'mysql -h db -umodx -pmodx --skip-ssl modx -N -e "SELECT COUNT(*) FROM modx_system_settings WHERE \`key\` LIKE \"aibridge\\_%\""'
```

Инварианты/грабли (не повторять):

- xPDO: `fromArray()` возвращает **void** — нельзя чейнить `(new X)->fromArray(...)`; собирать в два шага.
- xPDO: у `getCollection($class, $criteria, $cacheFlag)` третий аргумент — это cacheFlag, **не** options:
  `['limit'=>..,'sortby'=>..]` молча игнорируется. Для limit/offset/sort строить `newQuery()` и звать
  `limit()`/`sortby()` (Iteration 54).
- xPDO: плоские ключи `OR:field:op` в criteria OR-ят **всю** предыдущую клаузу (включая `deleted = 0`) →
  совпадёт почти всё. Для поиска — вложенная группа `where([[...],[...]], xPDOQuery::SQL_OR)` (Iteration 54).
- xPDO: не делать частичный `select('col')` на коллекции: у гидратированных объектов нет PK, и первое
  обращение к полю вызывает lazy-load с null PK → неограниченный запрос и memory exhaustion (ловили в
  Iteration 56). Забирать объекты целиком и читать поле.
- xPDO: vehicle-имена случайны (`md5(uniqid(rand(), true))`) — для воспроизводимости передавать `guid` в
  атрибутах `createVehicle`; `registerNamespace` guid не принимает.
- Zip-нормализация: только файловые записи + фиксированный `SOURCE_DATE_EPOCH`; пустые каталоги git не хранит.
- После установки **новой версии** пакета в уже запущенный стек — `docker compose restart modx`: opcache
  держит старые классы, и HTTP-ответы показывают прежнюю версию до перезапуска (локально ловили на 0.1.3;
  CI стартует свежие контейнеры, CLI/worker — свежие процессы).
- Тестовый рантайм — только `scripts/configure-test-runtime.php`; пустой `aibridge_ip_allowlist` = запрет всем;
  `delete` запрещён, `publish` требует approval; секреты не логируются; profile isolation и
  SecurityDecisionPipeline обязательны.
- `composer test -- --testsuite integration` и `bash scripts/ts-live-check.sh` **не запускать параллельно**
  на одном стеке: оба перезаписывают `aibridge_ip_allowlist`, live-прогон ловит `403 ip_not_allowed`.
  В `test-modx.sh` они идут последовательно. Установка пакета ре-импортирует дефолты настроек — harness
  переустанавливает тестовые значения сам.
- Read-back: `GET /resources/{id}` требует scope `resource:read`; проекция включает карту `tvs` (TV, привязанные
  к шаблону ресурса; scalar → `string|null`, структурированные → JSON-строка). Те же данные отдаёт MCP
  `resource_read`. `GET /resources` (операция `resource.list`, тот же scope) — список с фильтрами
  (`parent`, `template`, `context_key`, `published`, `tv_name`, `tv_value`, `q`, `limit` 1..100 default 25,
  `offset`, `sort`, `dir`), summary-проекция без `content`/TV + `count/total/limit/offset`; невалидный
  фильтр → `400 invalid_filter`; `tv_name`/`tv_value` матчат только явные TV-значения (не `default_text`).
  MCP: `resource_read`/`resource_list` (tools) и resource template `modx://resource/{id}` +
  `resources/templates/list` (та же операция `resource.read`; отсутствующий ресурс → JSON-RPC `-32002`).
  Read-путь site-level — profile isolation, как в Iteration 51, на него не распространяется.
- `composer lint` — unix-only (`find|xargs`), только в контейнере. Entrypoint `/var/www/html/.modx-ready` —
  маркер готовности MODX; `test-modx.sh` ждёт именно его.
- Supply chain: любой `uses:` — только полный 40-символьный commit SHA; образы — только `@sha256:` digest.
  После добавления/обновления action или образа прогонять `bash scripts/verify-supply-chain-pins.sh`.
  `sha_pinning_required=true`, поэтому незакреплённый action ломает запуск workflow (это намеренно).
- Релиз: `README.md` встраивается в manifest transport-пакета, поэтому его правка меняет sha256 архива.
  Обновлять README (и все прочие version-identity, включая `aibridge_version` в settings.php и `User-Agent`
  SDK при смене minor) в bump-коммите **до** certification-прогона, иначе pre-release и tag-run артефакты
  разойдутся (случай `0.1.2`).
- Коммит/пуш — по явной команде; git identity настроена локально (`githubcms`), `composer.lock` в репозитории.

## 5. Остаточные риски

- Digest-пины образов и SHA-пины actions обновляются только через Dependabot-PR (`.github/dependabot.yml`,
  раз в неделю); без просмотра этих PR пины устаревают и накапливают известные CVE.
- `sha_pinning_required=true`: любой новый `uses:` без полного 40-символьного SHA делает workflow
  невалидным — обходить через отключение настройки не следует, нужно закреплять по SHA.
- TS-тесты используют fake `fetch`; live-HTTP E2E (Iteration 49) закрывает этот пробел, но требует Node на
  хосте и поднятый стек. `resource.delete` запрещён политикой, поэтому live-прогон оставляет созданный
  ресурс в persistent-стеке (CI сносит стек через `docker compose down -v`).
- `scripts/ts-live-runtime.php` печатает токен в stdout — только test/dev, не логировать вывод; harness
  передаёт его в Node через окружение.
- `0.1.0` (tag `v0.1.0`) не содержал настроек и не был воспроизводимым — исторический артефакт.
- Read-back `tvs` отдаёт значения TV как есть (без redaction): это часть контента ресурса, но если в TV
  хранятся секреты, они попадут авторизованному клиенту со scope `resource:read`.
- Латентный xPDO-баг вне scope Iteration 54: `ResourceExplorerService::search/tree` и `RestApi::profiles`
  передают options третьим аргументом `getCollection` (это cacheFlag), поэтому limit/sort там игнорируются, а
  `search()` использует плоские `OR:`-ключи (см. §4) — при доработке менеджерского поиска/профилей это надо
  исправить тем же паттерном `newQuery()`. Поведение намеренно не менялось.
- README встроен в manifest пакета — любая правка меняет sha256 артефакта; фиксировать README до гейта
  (см. §4).
- Исторические записи не менять: `docs/release/0.1.0.md`, `docs/testing/iteration-38..41-*`,
  `docs/testing/iteration-39-stable-certification.md`.

## 6. Что делать дальше

Stable `0.6.0` выпущен и опубликован (GitHub Release, 3 ассета); `main` = `cb90ba0` — релизная документация,
нерелизных коммитов нет. Обязательных гейтов нет.

Приоритетные кандидаты:

1. **Остальное read-back-покрытие**: `publishedon` в `sort`, `context_key` в `/capabilities`, `total`-оптимизация
   (сейчас `tv_name`/`list` собирает id и делает `id:IN`; при больших сайтах — подзапрос) — тем же flow
   (bump всех identity включая README → гейт → evidence → tag → tag-run → GitHub Release).
2. **Менеджерский слой**: починить латентные xPDO-баги `ResourceExplorerService::search/tree` и
   `RestApi::profiles` (options-as-cacheFlag + плоский `OR:`) тем же паттерном, что в Iteration 54.
3. Опционально: ревьюить Dependabot-PR по digest/SHA-пинам.

Выполнено: supply-chain pinning + `sha_pinning_required` (47), релиз `0.1.2` (48), TS live-HTTP E2E +
Defect #35 (49), релиз `0.1.3` (50), read-back API + `aibridge_version` (51), релиз `0.2.0` (52),
TV-поля в read-back + релиз `0.3.0` (53), фильтрованный список + релиз `0.4.0` (54),
MCP resource template + релиз `0.5.0` (55), TV-фильтр списка + релиз `0.6.0` (56).

Рабочий цикл: `READ → MAP → PLAN → CHANGE → LINT → TEST → REVIEW → REPORT`; после кодинга —
`DIFF → SYNTAX → UNIT/CONTRACT → RUNTIME IF AVAILABLE → SECURITY REVIEW → CHANGELOG` (см. `AGENTS.md` §3, §7).
