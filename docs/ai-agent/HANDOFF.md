# Handoff — ModX AI Bridge (Stable `0.1.1`, tag `v0.1.1`)

Дата: 2026-09-13
Состояние: **STABLE**. Текущий релиз — `0.1.1` (patch), заменяет `0.1.0`.

## 1. Репозиторий

- Локально: `E:\projects\ModX AI Bridge` (Windows, Docker Desktop, Node.js 24, Git Bash).
- Remote: `https://github.com/biznesemarket/ModX-AI-Bridge`, branch `main`.
- Ключевые коммиты:

```text
db949b5 Iteration 44: Fix system settings packaging and cut 0.1.1
1f1005b Iteration 43: Drop directory entries from reproducible transport packages
487a4bf Iteration 43: Reproducible transport package builds
99ae6d7 Iteration 42: CI gate hardening and release-evidence retention
160a659 Iteration 41: Finalize 0.1.0 Stable transport package
642c681 Iteration 40: mark TypeScript SDK check executable
77bb849 Iteration 40: Unblock TypeScript SDK certification gate
```

- Теги: `v0.1.0`, `v0.1.1`.

## 2. Что сделано (Iterations 42–44)

- **42 — CI-гигиена.** `Quality Gates` job `deterministic` теперь гоняет PHP `sdk` suite и TypeScript-гейт
  (pinned Node 24); workflows переведены на Node 24 actions (`checkout@v7`, `setup-node@v7`,
  `ramsey/composer-install@v4`); `Release` job `certify` выгружает `dist/**` как артефакт
  `release-evidence-<sha>`.
- **43 — Воспроизводимость.** `_build/build.php`: детерминированные `guid` для всех vehicle
  (xPDO иначе `md5(uniqid(rand(), true))`; namespace-vehicle создаётся вручную), нормализация архива
  (сортировка, удаление directory-entries, фиксированный `SOURCE_DATE_EPOCH`, default `315532800`).
  `scripts/verify-package-reproducibility.php` — шаг 15 в `scripts/test-modx.sh`.
- **44 — Исправление упаковки настроек + `0.1.1`.** `_build/elements/settings.php` использовал
  `(new modSystemSetting($modx))->fromArray(...)`, а `xPDOObject::fromArray()` возвращает void → массив
  `null` → 19 настроек `aibridge_*` не попадали в пакет (defect #34). Исправлено; версия поднята до
  `0.1.1`; пакет `aibridge-0.1.1.transport.zip` сертифицирован и опубликован.

## 3. Доказательства

- `docs/release/0.1.1.md` — release record (sha256 `26ccdee1…`, reproducible).
- `docs/release/stable-status.md` — статус STABLE (0.1.1 + история 0.1.0).
- `docs/testing/iteration-40-typescript-sdk-unblock.md`, `iteration-41-stable-release.md`,
  `iteration-44-settings-packaging-0.1.1.md`.
- `docs/testing/STATUS.md`, `docs/release/FINAL-INTEGRATION-STATUS.md`, `docs/ai-agent/baseline/known-defects.md`.

Сертификационные прогоны: `642c681`/`34715583238`, `160a659`/`34716441523`, `1f1005b`/`34743048978`,
`db949b5`/`34743692154` — все `STABLE certification gates passed.`

## 4. Как работать в этой среде

Windows-хост: Docker + Node.js + Git Bash, но **нет PHP/Composer**. Docker-часть гейта — на CI
(`ubuntu-latest`); локально PHP-команды — внутри контейнера `modx`. После правок `core/`, `assets/`,
`_build/` пересобрать и переустановить пакет:

```powershell
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php >/dev/null && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.1.1.transport.zip'
```

Полезные команды:

```powershell
# воспроизводимость пакета (2 сборки, сравнение sha256)
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php scripts/verify-package-reproducibility.php'

# TypeScript SDK локально (Git Bash + Node)
& "C:\Program Files\Git\bin\bash.exe" -lc 'cd "/e/projects/ModX AI Bridge" && bash scripts/sdk-typescript-check.sh'

# полный сертификационный гейт на CI
gh workflow run release.yml --ref main
gh run watch <run-id> --exit-status
```

Правила среды: тестовый рантайм — только `scripts/configure-test-runtime.php`; пустой
`aibridge_ip_allowlist` = запрет всем; `delete` запрещён, `publish` требует approval; секреты не логируются;
profile isolation и SecurityDecisionPipeline обязательны. Паттерн `(new X)->fromArray(...)` невалиден для
xPDO — `fromArray()` возвращает void.

## 5. Остаточные риски

- Кросс-среда воспроизводимость доказана для текущего тулчейна (PHP 8.2.33, zlib 1.3.1); для устойчивости
  во времени стоит пиннить digest образа `php:8.2-apache`.
- `0.1.0` (tag `v0.1.0`) не содержал настроек и не был воспроизводимым — исторический артефакт, не
  переписывался.
- `sdk/typescript`: добавлены runtime-тесты (`tests/runtime/client.test.mjs`, `node --test`, 11 тестов); они
  используют fake `fetch` и проверяют заголовки/идемпотентность/ошибки/`waitForJob`/MCP. Реальный HTTP к
  поднятому MODX из TS-тестов не выполняется (это отдельный E2E-уровень).
- `docs/release/0.1.0.md`, `docs/testing/iteration-38..41-*` — исторические записи, не менять.

## 6. Следующий шаг

Stable `0.1.1` выпущен; обязательных гейтов нет. Дальнейшие изменения — цикл
`READ → MAP → PLAN → CHANGE → LINT → TEST → REVIEW → REPORT` с прогоном
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` и обновлением release/evidence документов.
Кандидаты: pinning образов и actions по SHA, TS-тесты против реального HTTP-эндпоинта, следующий minor
(`0.2.0`).
