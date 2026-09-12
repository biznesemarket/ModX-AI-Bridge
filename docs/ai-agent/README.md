# ModX AI Bridge — руководство по работе с AI-агентом

## Назначение

Документ описывает рабочий процесс AI-агента при разработке, тестировании, стабилизации и выпуске ModX AI Bridge. Он рассчитан на автономного coding/repository agent, который может читать и изменять файлы, запускать команды, выполнять тесты и готовить commit/PR.

Bridge — security-sensitive MODX Extra. Поэтому основная задача агента — не только написать код, но и доказать, что изменение не разрушило архитектурные и security invariants.

## 1. Модель проекта

Проект состоит из нескольких слоёв:

```text
External AI / Application
        │
   REST / MCP / SDK
        │
 Authentication / Authorization
        │
 SecurityDecisionPipeline
        │
 Application Services
        │
 Domain / Contracts / QA / Policy
        │
 Queue / Execution / Verification
        │
 Infrastructure / xPDO
        │
 MODX
```

Manager UI является ещё одним клиентом Application/Manager boundary, но не должен обходить execution/security layers.

## 2. Что агент должен изучить перед первой задачей

Минимальный обязательный набор:

- `AGENTS.md`;
- `README.md`;
- `docs/architecture/`;
- `docs/security/`;
- `docs/execution/`;
- `docs/queue/`;
- `docs/workflow/`;
- `docs/testing/`;
- `docs/release/`;
- `composer.json`;
- `core/components/aibridge/bootstrap.php`;
- schema;
- processors/controllers;
- Application Services;
- tests.

После чтения агент должен построить карту: «какой вход → какой service → какая security boundary → какая persistence → какие tests».

## 3. Как начинать задачу

Не начинать с создания файла.

Сначала:

```text
1. Parse task
2. Identify affected capability
3. Search existing implementation
4. Read relevant contracts
5. Identify invariants
6. Define minimal change
7. Define tests
8. Implement
```

Если пользователь просит, например, добавить новый API endpoint, сначала найти существующий router, API controller, request validator, auth middleware и Application Service. Endpoint не должен содержать бизнес-логику.

## 4. Architectural placement

### HTTP/API

HTTP layer отвечает за parsing request, status code, headers, authentication context и serialization.

Он не должен напрямую выполнять xPDO mutation.

### Application

Application Service координирует use case.

### Domain/Contract

Здесь находятся правила состояния, Content Contract, validation, policy decisions и state machines.

### Infrastructure

Здесь MODX/xPDO, persistence, clock, filesystem, transport adapters и другие внешние зависимости.

### Processor

MODX Processor — адаптер входа в Application layer. Он не должен становиться новым центром бизнес-логики.

## 5. Mutation protocol

Любая операция, которая меняет MODX, должна быть проверена как минимум по этой схеме:

```text
Principal
 ↓
Authentication
 ↓
Authorization
 ↓
Profile isolation
 ↓
Policy
 ↓
Idempotency
 ↓
Content Contract / QA
 ↓
Approval when required
 ↓
Snapshot
 ↓
Transaction
 ↓
MODX mutation
 ↓
Cache invalidation
 ↓
Verification
 ↓
Audit
```

Если новая операция не может пройти этот путь, задача должна быть остановлена и архитектурная проблема описана пользователю.

## 6. Работа с БД

Schema является источником структуры persistence layer. Изменение таблиц выполняется через migration/schema process, а не ручным SQL в production.

Для новой таблицы агент должен определить:

- primary key;
- foreign/profile boundary;
- indexes;
- uniqueness;
- nullable semantics;
- retention;
- secret fields;
- migration rollback strategy.

Нельзя добавлять поле без определения его жизненного цикла и security implications.

## 7. Работа с токенами и секретами

Plaintext bearer tokens допустимы только в момент выдачи клиенту, если это предусмотрено API. После этого хранится hash/metadata.

Агент обязан проверять:

- token hash не попадает в response;
- Authorization header не попадает в logs;
- exceptions не содержат token;
- audit payload проходит redaction;
- fixtures не содержат реальные secrets.

## 8. Multi-site

`profile_id` — security boundary, а не просто фильтр UI.

Каждый repository/service query, связанный с site-scoped сущностью, должен проверяться на profile isolation.

Особое внимание:

```text
Token A + Profile A ≠ Token A + Profile B
Job A ≠ Job B
Audit A ≠ Audit B
Schema A ≠ Schema B
Policy A ≠ Policy B
Snapshot A ≠ Snapshot B
```

## 9. Queue

Worker должен быть идемпотентным и устойчивым к повторной доставке.

Агент должен проверять:

- claim atomicity;
- stale lock recovery;
- retry limit;
- timeout;
- cancellation;
- idempotency key;
- duplicate job handling;
- profile boundary;
- audit;
- failure state.

## 10. Approval

Approval относится к конкретному Change Request.

Недостаточно проверить только `approval_id`. Должна быть проверена связь approval → change и статус approval.

Публикация и опасные операции требуют human-in-the-loop согласно текущей policy.

## 11. Verification и rollback

После mutation нельзя считать операцию успешной только потому, что MODX API вернул `true`.

Нужно проверить expected state против actual state.

Если verification failed:

```text
mutation result uncertain
        ↓
mark failed
        ↓
prevent unsafe retry
        ↓
rollback if policy allows
        ↓
audit
```

Rollback не должен молча восстанавливать сущности, если identity/relations могут измениться.

## 12. Тестирование

Порядок проверки:

1. syntax;
2. unit;
3. contract;
4. security regression;
5. integration;
6. concurrency;
7. E2E;
8. release certification.

Если среда не содержит Docker, runtime tests должны иметь статус BLOCKED.

## 13. Как разбирать ошибку

Не исправлять только последнюю строку stack trace.

Алгоритм:

```text
Error
 ↓
First failing assertion / HTTP response
 ↓
Call path
 ↓
Architectural layer
 ↓
Root cause
 ↓
Minimal fix
 ↓
Regression test
```

Если ошибка возникает только при реальном MODX, не подменять её mock-ом без отдельного integration regression test.

## 14. Git workflow

Рекомендуемый цикл:

```text
feature branch
 ↓
small logical commits
 ↓
local checks
 ↓
security review
 ↓
PR
 ↓
CI
 ↓
merge
```

Commit должен описывать изменение, а не проблему среды.

## 15. Что делать при неопределённости

Агент не должен угадывать.

Если неизвестно:

- какая версия MODX реально используется;
- существует ли processor;
- какой scope разрешён;
- является ли поле public;
- должна ли операция быть idempotent;
- можно ли rollback;

нужно найти доказательство в коде/tests/docs. Если доказательства нет — зафиксировать assumption или запросить уточнение.
