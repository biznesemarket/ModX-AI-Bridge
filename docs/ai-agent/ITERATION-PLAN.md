# План итераций для AI-агента

## Принцип

После завершения архитектурных Iteration 1–20 проект должен перейти в режим инженерной стабилизации. Новые функции добавляются только через небольшие контролируемые итерации.

Каждая итерация должна иметь:

- цель;
- входные условия;
- список файлов/слоёв;
- implementation tasks;
- tests;
- security checks;
- migration impact;
- documentation update;
- exit criteria;
- explicit blockers.

---

## A0 — Repository Reconnaissance

Цель: агент полностью понимает checkout.

Задачи:

- прочитать AGENTS.md;
- inventory files;
- проверить git status;
- определить PHP/Composer/MODX target;
- найти все processors/services/models/tests;
- построить dependency map;
- определить текущий release status.

Выход:

```text
REPO MAP READY
```

Нельзя начинать feature implementation до завершения A0.

---

## A1 — Build & Static Baseline

Цель: зафиксировать baseline до изменений.

Проверки:

- PHP syntax;
- Composer validation;
- shell syntax;
- JSON/YAML/XML parsing;
- существующие deterministic tests;
- schema consistency.

Результат сохраняется в `docs/ai-agent/baseline/`.

---

## A2 — MODX Runtime Bring-Up

Цель: впервые получить фактический MODX 3.2.x runtime.

Порядок:

1. Docker availability;
2. MySQL;
3. fresh MODX installation;
4. Composer/vendor;
5. xPDO schema generation;
6. Transport Package build;
7. Extra installation;
8. bootstrap;
9. Manager.

Критерий: Bridge загружается без fatal error.

---

## A3 — Database & Migration Certification

Проверить:

- initial install;
- all migrations;
- migration ordering;
- duplicate migration protection;
- upgrade from previous state;
- rollback documentation;
- indexes/foreign keys;
- profile scoping.

---

## A4 — REST E2E

Проверить реальным HTTP клиентом:

- health/readiness;
- authentication;
- invalid token;
- expired token;
- scope denial;
- profile isolation;
- schema;
- fingerprint;
- content contract;
- validation;
- mutation job creation;
- job status;
- error format.

---

## A5 — MCP E2E

Проверить:

- initialize;
- capability discovery;
- tools/list;
- resources/list;
- resources/read;
- prompts/list/get;
- tools/call;
- invalid arguments;
- security denial;
- profile isolation;
- mapping to Application Services.

---

## A6 — Resource Mutation E2E

Сценарий:

```text
create
 → QA
 → snapshot if applicable
 → transaction
 → MODX
 → verification
 → audit
```

Проверить update, preview, publish и delete отдельно.

---

## A7 — Queue Concurrency

Минимум:

- two workers, one job;
- lease expiry;
- stale job recovery;
- retry;
- duplicate delivery;
- timeout;
- cancellation;
- max attempts;
- idempotency.

Ожидаемый результат: одна mutation не выполняется дважды из-за race condition.

---

## A8 — Approval Workflow E2E

Проверить:

```text
draft
 → diff
 → QA
 → pending approval
 → reject
```

и:

```text
pending
 → approve
 → execute
 → verify
 → audit
```

Дополнительно проверить mismatched approval/change.

---

## A9 — Verification & Rollback E2E

Создать controlled failure после mutation и проверить:

- mismatch detection;
- failed state;
- rollback decision;
- snapshot integrity;
- TV restore;
- audit;
- no unsafe automatic deletion restore.

---

## A10 — Multi-Site Isolation

Создать минимум два независимых profiles/sites и проверить cross-access matrix:

| Actor | Profile A | Profile B |
|---|---:|---:|
| Token A | ALLOW | DENY |
| Token B | DENY | ALLOW |
| Job A | A only | DENY |
| Audit A | A only | DENY |

---

## A11 — Manager Operations E2E

Проверить:

- Operations Console;
- profiles;
- tokens;
- policies;
- jobs;
- audit;
- fingerprints;
- Resource Explorer;
- preview;
- create/update/delete/publish workflow;
- permission denial.

---

## A12 — Security Regression

Проверить:

- auth bypass;
- scope escalation;
- profile escape;
- IDOR;
- secret leakage;
- audit leakage;
- rate limit;
- idempotency replay;
- approval replay;
- dangerous operation denial;
- malformed JSON;
- oversized body;
- unexpected content types.

---

## A13 — SDK Contract Certification

PHP SDK:

- API errors;
- timeouts;
- auth;
- idempotency;
- jobs;
- typed result mapping.

TypeScript SDK:

- build;
- type checks;
- runtime client tests.

---

## A14 — Upgrade/Recovery Drill

Проверить:

```text
old release
 → backup
 → upgrade
 → migration
 → smoke test
 → rollback
 → restore
```

Не считать rollback документацию доказательством rollback. Нужен фактический drill.

---

## A15 — Performance & Limits

Проверить:

- large resource;
- large HTML;
- many TVs;
- many resources;
- queue depth;
- rate limit;
- API body limit;
- slow job;
- audit growth.

Зафиксировать thresholds и observed values.

---

## A16 — Observability Certification

Проверить:

- request ID propagation;
- structured errors;
- audit events;
- worker events;
- readiness;
- failure diagnostics;
- secret redaction;
- log correlation.

---

## A17 — Release Candidate

Собрать Transport Package.

Проверить:

- version;
- package signature;
- file inventory;
- migrations;
- generated models;
- SHA-256;
- install/upgrade path.

---

## A18 — Stable Certification

Stable возможен только при:

- runtime PASS;
- all mandatory security PASS;
- migration PASS;
- API/MCP PASS;
- queue concurrency PASS;
- rollback PASS;
- multi-site PASS;
- release artifact PASS;
- no unresolved critical/high defects.

Любой BLOCKED mandatory gate означает `NOT STABLE`.

---

## A19 — Post-Stable Maintenance

После Stable новые изменения идут только через:

```text
issue
 → impact analysis
 → mini-iteration
 → regression
 → release candidate
 → certification
```

---

## Стандартный шаблон каждой итерации

```text
Iteration: AXX
Goal:
Scope:
Out of scope:
Affected layers:
Affected files:
Security invariants:
Migration impact:
Implementation steps:
Tests:
Runtime requirements:
Expected evidence:
Rollback plan:
Exit criteria:
Blockers:
Status:
```
