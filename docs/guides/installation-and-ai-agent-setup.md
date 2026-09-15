# Установка ModX AI Bridge и настройка AI-агента

Пошаговая инструкция для не-программиста. Версия: Stable `0.11.0` (2026-09-14).

Прочитав эту инструкцию и выполнив шаги по порядку, вы получите работающий «мост» между вашим
сайтом на MODX Revolution и AI-агентом (ChatGPT, Claude, Cursor и т.п.), который сможет безопасно
читать структуру сайта и готовить статьи. Публикация статей описана во второй инструкции
(`docs/guides/publishing-and-ai-prompts.md`).

Инструкция рассчитана на владельца сайта или контент-менеджера. Часть шагов (установка пакета,
выдача токена, запуск очереди) требует доступа в панель MODX и к серверу по SSH. Если у вас нет
SSH — передайте разделы 6 и 7 вашему хостингу/администратору, это две готовые команды.

---

## 0. Что понадобится (требования)

| Что | Требование |
|---|---|
| CMS | MODX Revolution 3.2.x (сертифицировано на 3.2.2-pl) |
| PHP | 8.1 и выше (сертифицировано на 8.2) |
| База данных | MySQL / MariaDB, поддерживаемые вашей версией MODX |
| Веб | HTTPS (обязателен по умолчанию) |
| Доступ | Администратор MODX Manager + SSH на сервер (для шагов 6–7) |
| AI-клиент | Любой клиент с поддержкой MCP (удалённый HTTP), либо REST через curl/SDK |

Время: около 30–60 минут, плюс время хостинга, если нужно настраивать веб-сервер.

> Важно: ModX AI Bridge — это «мост» только для AI. Он не заменяет обычный редактор MODX.
> Вы в любой момент можете редактировать и публиковать материалы штатными средствами MODX.

---

## Шаг 1. Сделайте резервную копию

1. Сделайте бэкап базы данных сайта (через панель хостинга или phpMyAdmin).
2. Сделайте бэкап файлов сайта (как минимум каталогов `core/` и `assets/`).
3. Убедитесь, что умеете восстановиться из бэкапа. Это обязательно: установка добавляет
   таблицы в базу и файлы в `core/components/aibridge/` и `assets/components/aibridge/`.

---

## Шаг 2. Скачайте пакет

1. Откройте страницу релизов:
   `https://github.com/biznesemarket/ModX-AI-Bridge/releases`
2. Найдите релиз **v0.11.0** (Latest) и скачайте ассет **`aibridge-0.11.0.transport.zip`**.
3. При желании проверьте целостность файла. Для версии `0.11.0` контрольная сумма:

   ```text
   sha256: 1f23f9fd62df99d26a0b923301dac17010aa25ee6b9f240774dda4096d9e0bb2
   размер: 148228 байт
   ```

   На Windows это делается командой `Get-FileHash .\aibridge-0.11.0.transport.zip -Algorithm SHA256`,
   на macOS/Linux — командой `shasum -a 256 aibridge-0.11.0.transport.zip`.
4. Не распаковывайте архив вручную и не копируйте файлы поверх установки. Установка выполняется
   только как Transport Package (см. ниже) — это требование безопасности и обновляемости.

---

## Шаг 3. Установите пакет в MODX

1. Войдите в **MODX Manager** под администратором.
2. Меню **Extras → Installer** (в некоторых сборках — **Инструменты → Установщик**).
3. На вкладке **Package Management** нажмите **Add Package → Upload a package** и выберите
   скачанный `aibridge-0.11.0.transport.zip`.
4. Дождитесь загрузки, затем нажмите **Install** напротив пакета.
5. Просмотрите отчёт установки (Resolvers). Установка считается успешной, если нет ошибок.
6. Нажмите **Clear Cache** / очистите кэш MODX.
7. Перезагрузите страницу Manager.

В результате должны появиться:

- namespace `aibridge` (**System → Namespaces**);
- пункт меню **Components → AI Bridge** (в некоторых сборках — **Extras → AI Bridge**);
- настройки с префиксом `aibridge_` в **System → System Settings**.

---

## Шаг 4. Проверьте установку (без включения API)

1. Откройте **Components → AI Bridge**. Должна открыться консоль с вкладками
   System / Profiles / Tokens / Policies / Jobs / Audit / Fingerprints / Resources.
   На вкладке **System** видны версия Bridge, версия MODX, версия PHP, окружение и статус
   готовности (readiness).
2. Проверьте публичный «health» сайта. Откройте в браузере:

   ```text
   https://ВАШ-ДОМЕН/api/ai/v2/health
   ```

   Ожидаемый ответ: `{"status":"ok","component":"modx-ai-bridge","version":"0.11.0", ...}`.

   Если вы видите 404 — значит на веб-сервере не добавлено перенаправление `/api/ai/v2/*`.
   Передайте хостингу один из готовых конфигов из репозитория:
   - для Apache: `docker/modx/apache/aibridge-api.conf`
   - для Nginx: `deploy/nginx/aibridge-api.conf`

   Для диагностики можно временно открыть «прямой» адрес:
   `https://ВАШ-ДОМЕН/assets/components/aibridge/api/index.php/health`.

3. Проверьте **готовность** (readiness): `https://ВАШ-ДОМЕН/api/ai/v2/ready`.
   Ответ `200` и `"status":"ready"` означает, что база и схема в порядке. Ответ `503` — есть
   проблема, смотрите поле `checks`.

> Пока не включайте REST/MCP: сначала нужно настроить безопасность (шаг 5).

---

## Шаг 5. Настройте параметры

Откройте **System → System Settings**, введите в фильтр `aibridge_` и вы увидите список настроек
Bridge. Меняйте только перечисленные ниже.

### 5.1. Минимальный безопасный набор (чтобы всё заработало)

| Настройка | Рекомендуемое значение | Зачем |
|---|---:|---|
| `aibridge_rest_enabled` | `Yes` | Включает REST API (`/api/ai/v2/*`). |
| `aibridge_mcp_enabled` | `Yes` | Включает MCP для AI-агентов (`POST /api/ai/v2/mcp`). |
| `aibridge_queue_enabled` | `Yes` | Включает очередь заданий. Изменения через REST и Manager-консоль обрабатывает worker (шаг 7). |
| `aibridge_ip_allowlist` | `["IP_АГЕНТА"]` | Список разрешённых IP/CIDR. **Пустой список запрещает всем** (fail closed). |
| `aibridge_require_https` | `Yes` | Запрещает небезопасный HTTP. Оставляйте включённым. |
| `aibridge_environment` | `production` | Метка окружения. |

### 5.2. Настройки безопасности (значения по умолчанию — уже безопасные)

| Настройка | По умолчанию | Комментарий |
|---|---:|---|
| `aibridge_approval_required_for_publish` | `Yes` | Публикация требует подтверждения человеком. **Рекомендуется оставить Yes.** |
| `aibridge_allow_resource_delete` | `No` | Удаление ресурсов запрещено. Включайте только при осознанной необходимости. |
| `aibridge_allow_setting_write` | `No` | Запись системных настроек запрещена. Не включайте без крайней необходимости. |
| `aibridge_security_fail_closed` | `Yes` | При сомнении — запрещать, а не разрешать. |
| `aibridge_audit_enabled` | `Yes` | Журнал аудита. Рекомендуется держать включённым. |
| `aibridge_blocked_operations` | `["settings.write"]` | Операции, которые запрещены всегда. |
| `aibridge_token_expiry_days` | `90` | Срок жизни нового токена в днях. |
| `aibridge_rate_limit_per_minute` | `60` | Лимит запросов на токен в минуту. |
| `aibridge_max_request_body_bytes` | `1048576` | Максимальный размер запроса (1 МБ). |
| `aibridge_idempotency_ttl_seconds` | `86400` | Время хранения ключей идемпотентности (1 сутки). |

### 5.3. Технические (не меняйте без необходимости)

`aibridge_core_path`, `aibridge_assets_url`, `aibridge_version` (`0.11.0`), `aibridge_api_version` (`v2`).

### 5.4. Как правильно заполнить `aibridge_ip_allowlist`

Это JSON-массив строк — точные IP-адреса или диапазоны CIDR. Примеры:

```json
["127.0.0.1", "10.0.0.0/24"]
```

```json
["203.0.113.10", "2001:db8::/32"]
```

Правила:

- **Пустой список `[]` = запрещено всем.** Это защита по умолчанию.
- AI-агенты в облаке (ChatGPT, Claude и т.п.) обращаются к сайту со своих исходящих IP. Если вы
  их не знаете — попросите поддержку вашего AI-клиента назвать egress-IP, либо используйте
  локальный/собственный агент, либо прокси, IP которого вам известен.
- При ошибке доступа клиент получит `403 ip_not_allowed` — это не поломка, а работа защиты.

> Никогда не публикуйте значение токена и не храните его в репозитории, переписке или документации.
> В настройках Bridge токенов нет — они выдаются отдельно (шаг 6) и хранятся только у вас.

---

## Шаг 6. Создайте профиль и выдайте токен (разовая команда на сервере)

В версии `0.11.0` консоль в Manager умеет менять **статус** профилей/токенов, но не создавать их
(создание оставлено серверному workflow ради безопасности и аудита). Поэтому первичные «профиль»
и «токен» создаются одной командой на сервере.

**Что такое профиль и токен простыми словами:**

- **Профиль (Profile)** — «учётная карточка» вашего сайта в Bridge (имя, ключ сайта, окружение).
  Токен всегда привязан к профилю, поэтому данные одного сайта не смешиваются с другим.
- **Токен (Token)** — «пропуск» для AI-агента. Он показывается **один раз** при создании.
  В базе хранится только его необратимый отпечаток, поэтому восстановить токен нельзя — только
  выдать новый.

### 6.1. Запустите команду на сервере

Подключитесь по SSH и выполните (замените путь на реальный путь к MODX, обычно `/var/www/html`
или `/home/логин/сайт`):

```bash
cat > /tmp/aibridge-provision.php <<'PHP'
<?php
$root = rtrim(getenv('MODX_ROOT') ?: '/var/www/html', '/') . '/';
require_once $root . 'config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new MODX\Revolution\modX();
if (!$modx->initialize('mgr')) { fwrite(STDERR, "MODX init failed\n"); exit(1); }

$core = MODX_CORE_PATH . 'components/aibridge/';
$modx->getLoader()->addPsr4('AIBridge\\', $core . 'src/');
$modx->addPackage('AIBridge\\Model', $core . 'src/', null, 'AIBridge\\');

$config   = AIBridge\Configuration\ConfigFactory::fromModx($modx);
$profiles = new AIBridge\MultiSite\ProfileService($modx);

$siteKey = getenv('AIBRIDGE_SITE_KEY') ?: 'ai-agent';
$profile = $profiles->getBySiteKey($siteKey) ?: $profiles->create([
    'name'        => 'AI Agent',
    'site_key'    => $siteKey,
    'environment' => 'production',
]);

$scopes = ['site:read','resource:read','content:validate','resource:preview','resource:write','resource:publish'];
$issued = (new AIBridge\Security\TokenManager($modx, $config))->issue('ai-agent-token', $scopes, $profile->profileId);

echo "PROFILE_ID=" . $profile->profileId . PHP_EOL;
echo "TOKEN_ID="   . $issued['id'] . PHP_EOL;
echo "TOKEN="      . $issued['token'] . PHP_EOL;
PHP

MODX_ROOT=/var/www/html php /tmp/aibridge-provision.php
```

В выводе вы получите три строки. Пример:

```text
PROFILE_ID=1
TOKEN_ID=3
TOKEN=9f2c...длинная строка из 64 символов...
```

**Сразу сохраните значение `TOKEN` в надёжном месте** (менеджер паролей). Оно больше не будет
показано. Строку `TOKEN_ID` сохраните тоже — по ней вы сможете отозвать токен в консоли
(**Components → AI Bridge → Tokens → Revoke**).

> Команда печатает токен в терминал, поэтому он может остаться в истории команд и в логах сессии.
> После сохранения очистите историю/вывод терминала (`history -c` для bash, закрытие окна сессии)
> и убедитесь, что вывод не попал в централизованные логи.

Удалите временный файл:

```bash
rm -f /tmp/aibridge-provision.php
```

### 6.2. Что означает набор прав (scopes)

Токен получает ровно те права, которые перечислены в `$scopes`. Ниже — расшифровка.

| Scope | Что разрешает |
|---|---|
| `site:read` | Читать схему сайта и fingerprint, `/capabilities`, `/profiles` |
| `resource:read` | Читать один ресурс и список ресурсов |
| `content:validate` | Проверять контент по Content Contract (QA) |
| `resource:preview` | Безопасный предпросмотр без записи |
| `resource:write` | Создавать и обновлять ресурсы (**черновики**) |
| `resource:publish` | Публиковать (по умолчанию — только с подтверждением) |
| `resource:delete` | Удалять ресурсы (по умолчанию запрещено политикой) |
| `settings:write` | Менять системные настройки (никогда не выдавайте AI) |

Рекомендуемый набор для контент-агента — тот, что в скрипте: `site:read`, `resource:read`,
`content:validate`, `resource:preview`, `resource:write`, `resource:publish`. Scope `resource:delete`
добавляйте только если действительно хотите, чтобы агент мог удалять, и помните, что глобальный
переключатель `aibridge_allow_resource_delete` всё равно должен быть включён.

---

## Шаг 7. Запустите обработчик очереди (worker)

Изменения, отправленные через **REST API и Manager-консоль**, выполняются **асинхронно**: запрос
ставится в очередь и обрабатывается отдельным процессом — worker'ом. Без работающего worker'а такие
задания останутся в статусе `queued`. MCP-инструменты записи (`resource_create`, `resource_update`,
`resource_publish`, `resource_delete`) выполняются синхронно в рамках запроса и worker для них не
нужен.

Ручной запуск (для проверки):

```bash
MODX_ROOT=/var/www/html php /var/www/html/core/components/aibridge/worker.php
```

Полезные режимы: `--once` — обработать одно задание и выйти; `--sleep=5` — пауза при простое;
`--max-iterations=N` — остановиться после N итераций.

Для постоянной работы настройте автозапуск. Пример systemd-юнита (путь и пользователя замените
на свои):

```ini
# /etc/systemd/system/aibridge-worker.service
[Unit]
Description=ModX AI Bridge queue worker
After=network.target

[Service]
Type=simple
User=www-data
Environment=MODX_ROOT=/var/www/html
ExecStart=/usr/bin/php /var/www/html/core/components/aibridge/worker.php --sleep=5
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now aibridge-worker
sudo systemctl status aibridge-worker
```

> Worker должен использовать ту же версию Bridge, что и сайт. После обновления пакета
> перезапустите worker.

---

## Шаг 8. Подключите AI-агента

### 8.1. Параметры подключения

| Параметр | Значение |
|---|---|
| Тип | MCP поверх HTTP (JSON-RPC 2.0), метод POST |
| Адрес | `https://ВАШ-ДОМЕН/api/ai/v2/mcp` |
| Авторизация | Заголовок `Authorization: Bearer ВАШ_ТОКЕН` |
| Content-Type | `application/json` |
| Условия | `aibridge_mcp_enabled = Yes`, HTTPS включён, IP клиента в `aibridge_ip_allowlist` |

Bridge не хранит токены в открытом виде и проверяет права на **каждый** вызов инструмента.

### 8.2. Конфигурация клиента

Точные имена полей зависят от вашего AI-клиента. Если клиент умеет подключаться к удалённому MCP
по HTTP и поддерживает свои заголовки, конфигурация выглядит так:

```json
{
  "mcpServers": {
    "modx-ai-bridge": {
      "type": "http",
      "url": "https://ВАШ-ДОМЕН/api/ai/v2/mcp",
      "headers": {
        "Authorization": "Bearer ВАШ_ТОКЕН"
      }
    }
  }
}
```

> Файл конфигурации обычно лежит на диске в открытом виде. Ограничьте права доступа к нему
> (только ваш пользователь), не храните его в облачных синхронизируемых папках и не добавляйте в
> репозитории. Если клиент умеет хранить секреты в системном хранилище паролей — используйте его.

Если клиент умеет только локальный (stdio) MCP, используйте HTTP→stdio-мост, который умеет
передавать заголовок авторизации. Такой мост — сторонняя программа, которая запускается на вашем
компьютере, поэтому **устанавливайте конкретную версию** и проверяйте, что это ожидаемый пакет:

```json
{
  "mcpServers": {
    "modx-ai-bridge": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote@<ВЕРСИЯ>",
        "https://ВАШ-ДОМЕН/api/ai/v2/mcp",
        "--header", "Authorization: Bearer ВАШ_ТОКЕН"
      ]
    }
  }
}
```

> Замените `<ВЕРСИЯ>` на конкретную опубликованную версию пакета — не запускайте мост без версии
> (`-y mcp-remote`), иначе будет выполнена произвольная последняя версия из npm. По возможности предпочитайте официальную поддержку удалённого MCP в вашем
> AI-клиенте, чтобы вообще не ставить сторонний мост.

После сохранения конфигурации перезапустите AI-клиент. В списке инструментов должны появиться
tools Bridge (см. таблицу ниже).

### 8.3. Какие инструменты получает агент

**Инструменты (tools):**

| Инструмент | Scope | Назначение |
|---|---|---|
| `site_schema` | `site:read` | Схема сайта (шаблоны, TV, ресурсы) |
| `site_fingerprint` | `site:read` | Отпечаток структуры сайта |
| `content_contract` | `site:read` | Правила контента для шаблона |
| `content_validate` | `content:validate` | Проверка контента |
| `resource_read` | `resource:read` | Чтение одного ресурса |
| `resource_list` | `resource:read` | Список/поиск ресурсов |
| `resource_preview` | `resource:preview` | Предпросмотр без сохранения |
| `resource_create` | `resource:write` | Создать черновик |
| `resource_update` | `resource:write` | Обновить черновик/ресурс |
| `resource_publish` | `resource:publish` | Опубликовать (нужно подтверждение) |
| `resource_delete` | `resource:delete` | Удалить (запрещено по умолчанию) |

**Ресурсы (resources):** `modx://site/schema`, `modx://site/fingerprint`,
`modx://resource/{id}` (чтение одного ресурса по id).

**Готовый промпт-шаблон** (prompt): `content_authoring` (тема + id шаблона).

> Через MCP инструменты чтения и записи отвечают сразу в рамках вызова. Через REST изменения
> ставятся в очередь и требуют worker'а (шаг 7), а статус получают по `GET /api/ai/v2/jobs/{id}`.
> Практический вывод: если вы работаете только через MCP-агента, worker нужен только для REST и
> Manager-консоли.

### 8.4. Альтернатива: REST без MCP

Если ваш клиент не поддерживает MCP, тот же функционал доступен по REST. Проверка:

```bash
curl -H "Authorization: Bearer ВАШ_ТОКЕН" https://ВАШ-ДОМЕН/api/ai/v2/capabilities
curl -H "Authorization: Bearer ВАШ_ТОКЕН" https://ВАШ-ДОМЕН/api/ai/v2/site/schema
curl -H "Authorization: Bearer ВАШ_ТОКЕН" https://ВАШ-ДОМЕН/api/ai/v2/resources/42
```

Создание черновика (обязателен заголовок `Idempotency-Key`, до 190 символов):

```bash
curl -X POST https://ВАШ-ДОМЕН/api/ai/v2/resources \
  -H "Authorization: Bearer ВАШ_ТОКЕН" \
  -H "Idempotency-Key: my-unique-key-0001" \
  -H "Content-Type: application/json" \
  -d '{"pagetitle":"Тестовая статья","template":1,"content":"<h1>Привет</h1>","parent":0}'
```

Ответ: `202 {"success":true,"job_id":N,"status":"queued",...}`. Дальше статус задания:
`GET /api/ai/v2/jobs/N`.

---

## Шаг 9. Первая проверка в AI-агенте

Откройте чат с подключённым агентом и напишите, например:

```text
Покажи, какие возможности у ModX AI Bridge и какие инструменты тебе доступны.
Затем покажи структуру сайта: какие есть шаблоны и какие TV привязаны к шаблону.
```

Ожидаемое поведение: агент вызовет `site_schema` / `content_contract` и вернёт человекочитаемый
список. Если агент пишет «нет инструментов» — проверьте конфигурацию клиента и перезапустите его.

Безопасная проверка записи (создание черновика, который не попадёт на сайт, пока его не
опубликуют):

```text
Создай черновик статьи с заголовком «Тестовая статья AI Bridge», шаблон 1, без публикации.
Скажи, какой id получился и в каком статусе задание.
```

Подробные сценарии и много готовых промптов — во второй инструкции
(`docs/guides/publishing-and-ai-prompts.md`).

---

## Шаг 10. Эксплуатация и безопасность

- **Ротация токена.** Выдайте новый токен той же командой из шага 6 (с другим именем), затем
  отзовите старый: **Components → AI Bridge → Tokens → Revoke**. Или сделайте это администратором.
- **Срок жизни.** Токен истекает через `aibridge_token_expiry_days` (по умолчанию 90 дней).
- **Аудит.** Все операции пишутся в журнал аудита: **AI Bridge → Audit** (и при
  `aibridge_audit_enabled = Yes`). Токены и секреты в журнал не попадают.
- **Мониторинг очереди.** Вкладка **Jobs** показывает статусы и прогресс. Зависшие задания
  переотправляются worker'ом автоматически.
- **Проверки.** `/api/ai/v2/health` — «жив ли Bridge», `/api/ai/v2/ready` — «готов ли принимать
  работу».
- **Чего агент не может по умолчанию:** удалять ресурсы, менять настройки, публиковать без
  подтверждения. Это защита, а не баг.

---

## Частые проблемы

| Симптом | Причина | Что делать |
|---|---|---|
| `404` на `/api/ai/v2/...` | Нет перенаправления на веб-сервере | Передайте хостингу `deploy/nginx/aibridge-api.conf` (Nginx) или `docker/modx/apache/aibridge-api.conf` (Apache) |
| `503 rest_disabled` | `aibridge_rest_enabled = No` | Включите настройку |
| `503 mcp_disabled` | `aibridge_mcp_enabled = No` | Включите настройку |
| `401 authentication_failed` | Пустой/неверный/истёкший токен | Выдайте новый токен, проверьте заголовок `Authorization: Bearer ...` |
| `403 ip_not_allowed` | IP клиента не в `aibridge_ip_allowlist` | Добавьте IP/CIDR клиента; список не должен быть пустым |
| `429 rate_limited` | Превышен лимит запросов | Подождите или поднимите `aibridge_rate_limit_per_minute` |
| `400 idempotency_key_required` | Нет заголовка `Idempotency-Key` у изменяющего запроса | Добавьте уникальный ключ (8–190 символов) |
| `400 https_required` | Запрос по HTTP при включённом HTTPS | Используйте HTTPS |
| Задание вечно `queued` (REST/Manager) | Не запущен worker | Запустите worker (шаг 7) |
| `content_qa_failed` | Контент не прошёл проверку (нет H1, `<script>` и т.п.) | Посмотрите `errors`/`warnings` в ответе и исправьте |
| `approval_required` при публикации | Публикация защищена подтверждением | См. вторую инструкцию, раздел о публикации |
| Показывается старая версия | Кэш/opcache держит старые классы | Очистите кэш MODX, перезапустите PHP/веб-сервер и worker |

---

## Что дальше

- Публикация статей, работа с черновиками, SEO, TV и большой набор готовых промптов:
  `docs/guides/publishing-and-ai-prompts.md`.
- Технические детали: `docs/configuration/settings-reference.md`,
  `docs/mcp/tools.md`, `docs/api/contracts.md`, `docs/security/token-management.md`.
