# Handoff — ModX AI Bridge (Stable `0.1.0`, tag `v0.1.0`)

Дата: 2026-09-12
Состояние: **STABLE**. Release Candidate `0.1.0-rc1` сертифицирован и заменён финальным пакетом `0.1.0`.

## 1. Репозиторий

- Локально: `E:\projects\ModX AI Bridge` (Windows, Docker Desktop, Node.js 24, Git Bash).
- Remote: `https://github.com/biznesemarket/ModX-AI-Bridge`, branch `main`.
- Ключевые коммиты финальной сессии:

```text
160a659 Iteration 41: Finalize 0.1.0 Stable transport package
642c681 Iteration 40: mark TypeScript SDK check executable
77bb849 Iteration 40: Unblock TypeScript SDK certification gate
fd2b158 Iteration 39: Stable certification attempt (blocked by TypeScript SDK gate)
```

## 2. Что сделано (Iterations 40–41)

- **40 — TypeScript SDK разблокирован.** Причина BLOCKED была не только в отсутствии Node: пакет
  `sdk/typescript` не имел `package-lock.json` (то есть `npm ci` не мог пройти в принципе); `client.ts`
  использовал import без расширения при `module: NodeNext`; ключ идемпотентности выводился как узкий
  template-literal тип; `scripts/sdk-typescript-check.sh` был `100644`, а вызывается как `./...` (exit 126).
  Исправлено; добавлен `workflow_dispatch` в `release.yml`.
- **Однокомандный гейт.** На GitHub Actions `ubuntu-latest` (Docker + PHP 8.2 + Composer + Node) выполнен
  `AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` — зелёный, `STABLE certification gates passed.`
  (commit `642c681`, run `34715583238`).
- **41 — Финализация `0.1.0`.** `_build/config.inc.php` `release` → `''`; `_build/build.php` и
  `scripts/release-candidate.php` безопасно собирают сигнатуру без завершающего `-`; `scripts/test-modx.sh`
  ставит `aibridge-0.1.0.transport.zip`. Гейт повторно зелёный на `160a659` (run `34716441523`),
  `RELEASE: aibridge-0.1.0` / `STABLE certification gates passed.`
- **Stable объявлен, создан тег `v0.1.0`.**

## 3. Доказательства

- `docs/release/0.1.0.md` — release record пакета `0.1.0`.
- `docs/release/stable-status.md` — статус STABLE и вывод гейта.
- `docs/testing/iteration-40-typescript-sdk-unblock.md`,
  `docs/testing/iteration-41-stable-release.md` — evidence.
- `docs/testing/STATUS.md`, `docs/release/FINAL-INTEGRATION-STATUS.md` — обновлены на STABLE.

## 4. Как работать в этой среде

Windows-хост: есть Docker + Node.js + Git Bash, но **нет PHP/Composer**. Docker-часть гейта исполняется на
CI (`ubuntu-latest`), локально PHP-команды — внутри контейнера `modx`. После правок `core/`, `assets/`,
`_build/` пересобрать и переустановить пакет (тесты/runtime используют установленную копию):

```powershell
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && MODX_ROOT=/var/www/html php _build/build.php >/dev/null && MODX_ROOT=/var/www/html php scripts/install-package.php /var/www/html/core/packages/aibridge-0.1.0.transport.zip'
```

Полезные команды:

```powershell
# TypeScript SDK локально (Git Bash + Node)
& "C:\Program Files\Git\bin\bash.exe" -lc 'cd "/e/projects/ModX AI Bridge" && bash scripts/sdk-typescript-check.sh'

# PHP deterministic внутри контейнера
docker compose exec -T modx bash -lc 'cd /workspace/modx-ai-bridge && composer validate --no-check-publish --strict && composer lint && composer verify-static-contract && composer test -- --testsuite unit,contract,security && composer test -- --testsuite sdk'

# Полный сертификационный гейт на CI (нужен Docker+PHP+Composer+Node)
gh workflow run release.yml --ref main
gh run watch <run-id> --exit-status
```

Правила среды сохраняются: тестовый рантайм — только через `scripts/configure-test-runtime.php`; пустой
`aibridge_ip_allowlist` = запрет всем; `delete` запрещён, `publish` требует approval; секреты не логируются;
profile isolation обязательна; snapshot/audit обязательны для mutation.

## 5. Остаточные риски / follow-up

- Transport-архивы не байт-воспроизводимы (встроенные timestamps): каждый build фиксирует собственный
  SHA-256 в `dist/<package>.release.json`; `dist/` gitignored.
- Тег `v0.1.0` при push запускает `release.yml` (verify → certify → package) как официальный release-гейт;
  проверить, что прогон тега зелёный (не только `main`).
- `sdk/typescript` покрыт только `tsc` build + type-check контракта; полноценного runtime-теста клиента нет.
- `docs/testing/iteration-38..39-*` и `docs/release/0.1.0-rc1.md` — исторические записи, их не менять.

## 6. Следующий шаг

Stable достигнут; новых обязательных гейтов нет. Дальнейшие изменения — только через обычный цикл
`READ → MAP → PLAN → CHANGE → LINT → TEST → REVIEW → REPORT` с прогоном
`AIBRIDGE_RUNTIME=1 bash scripts/certification/stable-gate.sh` и обновлением release/evidence документов.
