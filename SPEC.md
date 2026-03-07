# SPEC: AI-финансовый консультант

Версия: 1.0  
Статус: Draft for implementation  
Основа: `DRAFT.md`

## 1. Цель продукта

Создать web-приложение для ведения личных финансов с ИИ-консультантом, который:
- анализирует доходы и расходы пользователя;
- помогает планировать бюджет;
- контролирует выполнение финансовых планов;
- дает рекомендации для экономии.

Критерий ценности: пользователь за минимальное число шагов фиксирует финоперации и получает практические действия по улучшению бюджета.

## 2. Акторы и роли

- `USER`: основной пользователь приложения.
- `ADMIN`: оператор поддержки и управления системой.
- `AI_AGENT`: LLM-агент, выполняющий аналитические и CRUD-действия через разрешенные инструменты.

## 3. Scope

### 3.1 MVP (обязательно)

- Регистрация/авторизация.
- Профиль пользователя.
- Счета (создание/редактирование/просмотр).
- Доходы и расходы (ручной ввод, редактирование, фильтрация, история).
- Периодические доходы/расходы.
- Планы расходов (по периоду, категориям, покупкам).
- Дашборд: таблицы и графики доходов/расходов.
- Чат с AI-агентом со streaming-ответом.
- Базовые AI-функции: прогноз, рекомендации по оптимизации, работа с планами/операциями.
- Подписка и тарифы.
- Баланс кредитов и списание за AI-запросы.
- Уведомления (in-app + подготовка к push).
- Админ-панель (тарифы, пользователи, подписки, базовая аналитика, чат поддержки).
- Техническая поддержка (чат user-admin).
- Доп. страницы: landing, privacy policy, help.

### 3.2 Post-MVP (можно позже)

- Расширенная аналитика аномалий.
- A/B-тестирование AI-стратегий.
- Расширенные push-каналы и email-кампании.
- Импорт из банковских выписок.

## 4. Функциональные требования

## 4.1 Аутентификация и аккаунт

- Email/password регистрация и логин.
- Восстановление пароля.
- Редактирование профиля.
- Ролевой доступ `USER/ADMIN`.

## 4.2 Финансовые сущности

- Счета: название, тип, валюта, текущий баланс.
- Операции: доход/расход, сумма, категория, дата/время, описание, счет.
- Плановые операции: операции с датой в будущем.
- Периодические операции: периодичность (daily/weekly/monthly/custom), дата начала, дата окончания (опционально).

## 4.3 План расходов

- Создание плана на период.
- Лимиты по категориям.
- Целевые покупки в рамках категорий.
- Привязка к конкретному счету (опционально).
- Цель плана: экономия или целевой остаток.
- Контроль исполнения: факт vs план.

## 4.4 Аналитика и визуализация

- Таблица операций с фильтрами (период, тип, категория, счет).
- График динамики доходов/расходов.
- Диаграмма расходов по категориям.
- Отдельный вид прогнозных данных.

## 4.5 AI-агент

- Чат с выбором модели.
- Streaming ответа.
- Tool/function вызовы для безопасных операций над данными пользователя.
- Функции агента: прогноз расходов/доходов.
- Функции агента: создание/редактирование плана расходов.
- Функции агента: контроль выполнения плана по расписанию.
- Функции агента: создание/редактирование/просмотр счетов и операций.
- Ведение истории диалогов в БД.

## 4.6 Подписка, платежи, кредиты

- Тарифы хранятся в БД: название, описание, срок (дни), цена (RUB).
- Пользователь оплачивает тариф через YooMoney.
- После оплаты начисляется кредитный баланс по конфигурируемому коэффициенту.
- Кредиты расходуются на AI-запросы.
- Неиспользованные кредиты могут сгорать через 1 месяц (политика задается конфигом).
- Пользователь видит текущее потребление и историю.

## 4.7 Уведомления

- In-app список уведомлений.
- Типы: системные, финплан, подписка, AI-контроль.
- Расписание для AI-контроля (periodic jobs).
- Готовность к push-уведомлениям.

## 4.8 Поддержка и админка

- User-admin чат поддержки.
- Админ:
- управляет тарифами;
- видит подписки/платежи/потребление;
- управляет балансами пользователей;
- при необходимости отменяет подписки;
- ведет переписку с пользователями.

## 5. Нефункциональные требования

- Адаптивность: mobile-first.
- Темы: светлая/темная.
- Дизайн: Liquid Glass, градиенты, анимации.
- Цвета: light bg `#FFFFFF`, dark bg `#101010`, primary `#FF7400`, secondary `#FFAA00`, `#FF0000`.
- Производительность: p95 API < 300 ms для CRUD-операций (без AI).
- Производительность: p95 AI first-token latency фиксируется в метриках.
- Безопасность: RBAC.
- Безопасность: защита персональных данных.
- Безопасность: аудит чувствительных действий.
- Надежность: транзакционность денежных и кредитных операций.
- Надежность: идемпотентность webhook-обработчиков оплаты.

## 6. Технологии

- Frontend: JavaScript, Vue.js.
- Backend: PHP 8.5, Laravel, REST API, Eloquent.
- БД: MySQL 8.
- LLM: OpenAI API, хранение моделей/тарифов токенов/диалогов в БД, API-ключи в конфиге.
- Платежи: YooMoney.
- Архитектурный стиль: Clean Architecture + разделение ответственности.

## 7. Юридические требования

- Соответствие 152-ФЗ (персональные данные).
- Корректная обработка платежных данных и юридически значимых событий оплаты.
- Логирование согласий и ключевых пользовательских действий.

## 8. Целевая архитектура

- `apps/web` (Vue SPA): UI, routing, state management, графики, чат.
- `apps/api` (Laravel): auth, billing, finance, ai orchestration, admin, notifications.
- Интеграции: OpenAI (chat/tool calls).
- Интеграции: YooMoney (checkout + webhooks).
- Фоновые задачи: контроль планов.
- Фоновые задачи: генерация уведомлений.
- Фоновые задачи: обработка периодических операций.

## 9. Базовая модель данных (минимум)

- `users`, `roles`, `user_roles`
- `accounts`
- `categories`
- `transactions`
- `recurring_transactions`
- `budget_plans`
- `budget_plan_categories`
- `budget_plan_items`
- `ai_conversations`
- `ai_messages`
- `ai_usage_logs` (tokens/cost/model)
- `subscriptions`
- `tariffs`
- `payments`
- `credit_ledger`
- `notifications`
- `support_chats`, `support_messages`

## 10. API (контуры)

- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`
- `GET/PUT /me`
- `GET/POST/PUT/DELETE /accounts`
- `GET/POST/PUT/DELETE /transactions`
- `GET/POST/PUT/DELETE /recurring-transactions`
- `GET/POST/PUT/DELETE /budget-plans`
- `GET /analytics/summary`, `GET /analytics/timeseries`, `GET /analytics/categories`
- `POST /ai/chat/stream`
- `GET /subscriptions/me`, `POST /subscriptions/checkout`
- `POST /payments/yoomoney/webhook`
- `GET /notifications`, `PATCH /notifications/{id}/read`
- `GET/POST /support/chats`, `POST /support/messages`
- `GET/POST/PUT /admin/*`

## 11. Наблюдаемость и эксплуатация

- Structured logging.
- Метрики API и фоновых задач.
- Метрики AI-стоимости (input/output/cached tokens, RUB cost).
- Audit log для админских действий.

## 12. Definition of Done

- Реализованы acceptance-критерии сценария.
- API покрыт тестами (feature + unit).
- Критические UI-сценарии покрыты e2e.
- Обновлена документация (`SPEC.md`, OpenAPI, README).
- Миграции и сиды проходят на чистой БД.

## 13. Предлагаемая файловая структура репозитория

```text
/
  AGENTS.md
  SPEC.md
  README.md
  .editorconfig
  .gitignore
  .env.example
  docker-compose.yml

  docs/
    architecture/
      context.md
      containers.md
      components.md
    adr/
    api/
      openapi.yaml
    product/
      glossary.md
      roadmap.md
    legal/
      privacy-policy.md
      data-processing.md

  apps/
    web/
      package.json
      vite.config.js
      src/
        app/
          router/
          store/
          providers/
        pages/
          landing/
          auth/
          dashboard/
          accounts/
          transactions/
          budget-plans/
          ai-chat/
          notifications/
          support/
          admin/
        widgets/
        features/
        entities/
        shared/
          ui/
          lib/
          api/
          config/
      public/
      tests/
        unit/
        e2e/

    api/
      composer.json
      artisan
      app/
        Domain/
          Finance/
          Budget/
          Subscription/
          AI/
          Notification/
          Support/
          User/
        Application/
          Finance/
          Budget/
          Subscription/
          AI/
          Notification/
          Support/
          User/
        Infrastructure/
          Persistence/
          LLM/
          Payments/
          Queue/
          Security/
        Interfaces/
          Http/
            Controllers/
            Requests/
            Resources/
          Console/
          Jobs/
      bootstrap/
      config/
      database/
        migrations/
        seeders/
        factories/
      routes/
        api.php
        console.php
      storage/
      tests/
        Unit/
        Feature/

  infra/
    docker/
      web/
      api/
      mysql/
      nginx/
    ci/
      github-actions/
        api-ci.yml
        web-ci.yml
        e2e.yml

  scripts/
    dev/
    ci/
    db/
```

## 14. Этапы реализации

1. Foundation: auth, профиль, каркас UI, базовый CRUD счетов и операций.
2. Planning: периодические операции, планы расходов, аналитика.
3. AI Core: чат, tool calling, списание кредитов, логирование стоимости.
4. Billing: тарифы, YooMoney, подписка, кредитный ledger.
5. Admin + Support + Notifications.
6. Hardening: безопасность, тесты, оптимизация, юридические страницы.
