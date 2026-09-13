# Handoff — ModX AI Bridge (Stable `0.1.2`)

Дата: 2026-09-13
Состояние: **STABLE `0.1.2`** (tag `v0.1.2`, GitHub Release опубликован с 3 ассетами). `main` = `5fdb98d`
(релизная документация; последний code-коммит `ad92ab5`), дерево чистое.

## 1. Репозиторий

- Локально: `E:\projects\ModX AI Bridge` (Windows, Docker Desktop, Node.js 24, Git Bash).
- Remote: `https://github.com/biznesemarket/ModX-AI-Bridge`, branch `main`.
- `main` = `5fdb98d541f3c70fe671a4edd5dd54fc29334333` (синхронизирован с origin; последний code-коммит
  `ad92ab5`), дерево чистое.
- Теги: `v0.1.0` (`d132c257…`), `v0.1.1` (`2f07e3d6…`), `v0.1.2` (`5695a928…`). GitHub Releases: `v0.1.2`
  (latest), `v0.1.1`, `v0.1.0` — все не prerelease.

Ключевые коммиты (новые сверху):

```text
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

> `v0.1.1` указывает на `6780a0a`, `v0.1.2` — на `c8ac594`.
> Исторические теги/релизы **не переписывать**.

## 2. Что сделано (Iterations 30–48)

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

## 3. Доказательства

- Release/status: `docs/release/0.1.2.md`, `docs/release/0.1.1.md`, `docs/release/0.1.0.md`,
  `docs/release/stable-status.md`, `docs/release/FINAL-INTEGRATION-STATUS.md`.
- Testing: `docs/testing/STATUS.md`, `docs/testing/iteration-40-typescript-sdk-unblock.md`,
  `iteration-41-stable-release.md`, `iteration-44-settings-packaging-0.1.1.md`,
  `iteration-45-provisioning-readiness.md`, `iteration-46-typescript-runtime-tests.md`,
  `iteration-47-supply-chain-pinning.md`, `iteration-48-release-0.1.2.md`.
- Дефекты: `docs/ai-agent/baseline/known-defects.md` (#34 закрыт в 0.1.1).
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
```

CI на `0b97dcd` (`Quality Gates` 34745076673, `MODX Integration` 34745076672) и на `8326fda`
(`Quality Gates` 34745267416: `tests 11 / pass 11 / fail 0`, `TYPESCRIPT SDK: PASS`;
`MODX Integration` 34745267266) — success. Очередь CI пуста.

Полные гейты `Release` 2026-09-13: `98a592f` run `34747385780` (Iteration 47 hardening,
`SUPPLY CHAIN PINS: PASS`); `ad92ab5` run `34748277073` (0.1.2 dispatch → pre-release sha256 `b1b985bf…`);
tag `v0.1.2` (`c8ac594`) run `34748489085` (release sha256 `eedd5f64…`, `PACKAGE REPRODUCIBILITY: PASS`,
`STABLE certification gates passed.`). Evidence-артефакты `release-evidence-{98a592f,ad92ab5,c8ac594}…`
(90 дней). Репозиторий: `sha_pinning_required=true` (проверено).

Артефакты: `aibridge-0.1.2.transport.zip` sha256
`eedd5f643fe97b378f5a4304fb3296b61a88db045d78302887417d88f8cb582d` (139585 bytes; GitHub Release `v0.1.2`,
digest ассета совпадает; локальная сборка == CI-артефакт). `aibridge-0.1.1.transport.zip` sha256
`26ccdee1fb27097694dc735795f86e2c75a66136310e2a1e9ca248465c7a729a`; `aibridge-0.1.0.transport.zip` sha256
`859c6599…` (историч.).

## 4. Как работать в этой среде

Windows-хост: Docker + Node.js + Git Bash, но **нет PHP/Composer**. Полный гейт — на CI (`ubuntu-latest`);
локально PHP-команды — внутри контейнера `modx`. Тесты/runtime используют установленную копию пакета, а не
workspace. После правок `core/`, `assets/`, `_build/` — пересобрать и переустановить:

```powershell
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php >/dev/null && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.1.2.transport.zip'
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

# PHP deterministic внутри контейнера
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && composer validate --no-check-publish --strict && composer lint && composer verify-static-contract && composer test -- --testsuite unit,contract,security && composer test -- --testsuite sdk'

# runtime-верификация / SQL (клиент MariaDB; нужен --skip-ssl)
docker compose exec -T modx bash -lc 'MODX_ROOT=/var/www/html php /workspace/modx-ai-bridge/scripts/verify-modx-runtime.php'
docker compose exec -T modx bash -lc 'mysql -h db -umodx -pmodx --skip-ssl modx -N -e "SELECT COUNT(*) FROM modx_system_settings WHERE \`key\` LIKE \"aibridge\\_%\""'
```

Инварианты/грабли (не повторять):

- xPDO: `fromArray()` возвращает **void** — нельзя чейнить `(new X)->fromArray(...)`; собирать в два шага.
- xPDO: vehicle-имена случайны (`md5(uniqid(rand(), true))`) — для воспроизводимости передавать `guid` в
  атрибутах `createVehicle`; `registerNamespace` guid не принимает.
- Zip-нормализация: только файловые записи + фиксированный `SOURCE_DATE_EPOCH`; пустые каталоги git не хранит.
- Тестовый рантайм — только `scripts/configure-test-runtime.php`; пустой `aibridge_ip_allowlist` = запрет всем;
  `delete` запрещён, `publish` требует approval; секреты не логируются; profile isolation и
  SecurityDecisionPipeline обязательны.
- `composer lint` — unix-only (`find|xargs`), только в контейнере. Entrypoint `/var/www/html/.modx-ready` —
  маркер готовности MODX; `test-modx.sh` ждёт именно его.
- Supply chain: любой `uses:` — только полный 40-символьный commit SHA; образы — только `@sha256:` digest.
  После добавления/обновления action или образа прогонять `bash scripts/verify-supply-chain-pins.sh`.
  `sha_pinning_required=true`, поэтому незакреплённый action ломает запуск workflow (это намеренно).
- Релиз: `README.md` встраивается в manifest transport-пакета, поэтому его правка меняет sha256 архива.
  Обновлять README (и все прочие version-identity) в bump-коммите **до** certification-прогона, иначе
  pre-release и tag-run артефакты разойдутся (случай `0.1.2`).
- Коммит/пуш — по явной команде; git identity настроена локально (`githubcms`), `composer.lock` в репозитории.

## 5. Остаточные риски

- Digest-пины образов и SHA-пины actions обновляются только через Dependabot-PR (`.github/dependabot.yml`,
  раз в неделю); без просмотра этих PR пины устаревают и накапливают известные CVE.
- `sha_pinning_required=true`: любой новый `uses:` без полного 40-символьного SHA делает workflow
  невалидным — обходить через отключение настройки не следует, нужно закреплять по SHA.
- TS-тесты используют fake `fetch` — live-HTTP E2E против поднятого MODX из TS нет (серверная сторона
  покрыта PHP runtime/integration).
- `0.1.0` (tag `v0.1.0`) не содержал настроек и не был воспроизводимым — исторический артефакт.
- README встроен в manifest пакета — любая правка меняет sha256 артефакта; фиксировать README до гейта
  (см. §4).
- Исторические записи не менять: `docs/release/0.1.0.md`, `docs/testing/iteration-38..41-*`,
  `docs/testing/iteration-39-stable-certification.md`.

## 6. Что делать дальше

Stable `0.1.2` выпущен и опубликован (GitHub Release, 3 ассета); обязательных гейтов нет. `main` =
релизная документация `0.1.2`; нерелизных коммитов поверх нет.

Приоритетные кандидаты:

1. **TS live-HTTP E2E** (опционально): прогон SDK против реального `/api/ai/v2/*` на поднятом MODX.
2. **Следующий minor `0.2.0`**: определиться с ветвлением (`develop`) и составом; новый CHANGELOG-раздел.
3. Мелочи: нет MODX-настройки `aibridge_version` (используются кодовые дефолты) — при желании добавить в
   `_build/elements/settings.php`.

Выполнено: supply-chain pinning + `sha_pinning_required` (Iteration 47) и релиз `0.1.2` (Iteration 48).

Рабочий цикл: `READ → MAP → PLAN → CHANGE → LINT → TEST → REVIEW → REPORT`; после кодинга —
`DIFF → SYNTAX → UNIT/CONTRACT → RUNTIME IF AVAILABLE → SECURITY REVIEW → CHANGELOG` (см. `AGENTS.md` §3, §7).
