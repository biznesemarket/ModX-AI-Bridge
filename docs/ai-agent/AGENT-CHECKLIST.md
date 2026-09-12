# Ежедневный checklist AI-агента

## Before coding

- [ ] прочитан AGENTS.md
- [ ] git status проверен
- [ ] текущая версия определена
- [ ] найдена существующая реализация
- [ ] прочитаны связанные contracts
- [ ] определены security invariants
- [ ] определён test plan

## During coding

- [ ] business logic находится в правильном layer
- [ ] нет прямого DB bypass
- [ ] profile isolation сохранена
- [ ] secrets redacted
- [ ] ошибки typed/machine-readable
- [ ] idempotency сохранена
- [ ] approval не обходится

## After coding

- [ ] diff reviewed
- [ ] PHP syntax
- [ ] unit/contract tests
- [ ] security regression
- [ ] runtime tests если доступны
- [ ] docs updated
- [ ] changelog updated
- [ ] blockers recorded
- [ ] no false PASS claims

## Before release

- [ ] Transport Package built
- [ ] package checksum
- [ ] migrations tested
- [ ] upgrade tested
- [ ] rollback tested
- [ ] API E2E
- [ ] MCP E2E
- [ ] queue concurrency
- [ ] multi-site isolation
- [ ] secret scan
- [ ] Stable gate PASS
