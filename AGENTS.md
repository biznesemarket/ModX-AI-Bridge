# ModX AI Bridge — инструкции для AI-агента

Этот файл является обязательным operational contract для любого AI-агента, работающего с репозиторием ModX AI Bridge.

## 1. Цель агента

Агент работает не как генератор отдельных файлов, а как инженер проекта. Перед изменениями он обязан:

1. определить текущую итерацию и release status;
2. изучить существующую архитектуру и связанные документы;
3. найти фактическую реализацию через поиск по коду;
4. не создавать параллельную архитектуру, если соответствующий service/pipeline уже существует;
5. сохранить SecurityDecisionPipeline для любой mutation-операции;
6. проверять PHP syntax и доступные deterministic tests после изменений;
7. не объявлять runtime/Stable PASS без фактического выполнения;
8. описывать все ограничения среды и незапущенные проверки.

## 2. Главные invariants

- AI не получает прямого доступа к MODX DB для обычных операций.
- REST и MCP используют Application Services.
- Manager UI не выполняет прямой CRUD; mutation идёт через queue/execution layer.
- Mutation проходит authentication → authorization → policy → idempotency → contract/QA → snapshot → transaction → MODX → cache → verification → audit.
- Approval не заменяет security pipeline.
- Token secrets не сохраняются и не выводятся в plaintext.
- Profile isolation обязательна.
- Delete и publish считаются опасными операциями и не должны самовольно становиться permissive.
- Runtime failure нельзя превращать в PASS.
- Если тест невозможно выполнить, статус должен быть BLOCKED/NOT RUN, а не PASS.

## 3. Рабочий цикл агента

Перед coding:

```text
READ → MAP → PLAN → CHANGE → LINT → TEST → REVIEW → REPORT
```

После coding:

```text
DIFF → SYNTAX → UNIT/CONTRACT → RUNTIME IF AVAILABLE → SECURITY REVIEW → CHANGELOG
```

## 4. Что запрещено

Запрещено:

- переписывать архитектуру ради упрощения одной задачи;
- обходить SecurityDecisionPipeline;
- выполнять SQL mutation вместо соответствующего service/processor;
- хранить bearer token в логах, audit или exception;
- принимать profile_id из тела запроса как достаточное доказательство права доступа;
- отключать QA ради прохождения теста;
- считать Docker runtime успешным без запуска;
- удалять существующие tests/documentation без причины;
- менять API contract без versioning/change note;
- использовать реальные production secrets в tests.

## 5. Definition of Done

Задача считается выполненной только если:

- код реализован в правильном architectural layer;
- нет duplicate implementation;
- security boundary сохранён;
- обработаны ошибки;
- добавлены/обновлены tests;
- документация обновлена;
- PHP syntax проходит;
- доступные deterministic checks проходят;
- runtime tests либо прошли, либо явно отмечены BLOCKED;
- changelog/status обновлены.

## 6. Приоритет источников

При конфликте информации использовать следующий порядок:

1. фактический код текущего checkout;
2. tests и schemas;
3. текущие architecture/security contracts;
4. release/version documents;
5. более старые iteration documents;
6. предположения агента — только явно обозначенные как assumptions.

## 7. Формат отчёта агента

Каждый завершённый task должен сообщать:

- что изменено;
- какие файлы изменены;
- почему выбран именно этот layer;
- какие tests выполнены;
- какие tests не выполнены и почему;
- security implications;
- migration implications;
- backward compatibility;
- remaining risks;
- следующий рекомендуемый шаг.
