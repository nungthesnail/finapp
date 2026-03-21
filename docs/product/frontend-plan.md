# Frontend Plan

Версия: 1.5  
Статус: F1-F5 выполнены, F6 базовый hardening выполнен  
Область: `apps/web` (Vue.js + PWA)

## 1. Цель

Перевести frontend из монолитного `App.vue` в модульную архитектуру с маршрутизацией, guard-логикой и экранной декомпозицией F2.

## 2. Принципы

- Навигация через Vue Router.
- Разделение на зоны: public/auth/app/admin.
- Единый API-клиент в `shared/api`.
- Единая auth/session модель с восстановлением сессии через `/api/me`.
- Для финансовых экранов единый feature-state (`features/finance/use-finance.js`).
- Mobile-first shell + PWA install flow.

## 3. Этапы

### Этап F1. Каркас приложения (выполнено)
- Router, guards, auth-provider, layouts, base shell.

### Этап F2. Разделение user-экранов (выполнено)
- Вынесены отдельные рабочие страницы:
  - `/app/accounts`
  - `/app/transactions`
  - `/app/recurring`
  - `/app/plans`
  - `/app/analytics`
  - `/app/settings`
- Вынесена финансовая логика в `features/finance/use-finance.js`.
- `dashboard` упрощен до обзорного экрана с метриками и быстрыми переходами.

Критерии готовности F2:
- Финансовые экраны не являются placeholder.
- Бизнес-логика финансов не хранится в layout/root.
- Сборка проходит, маршруты доступны.

### Этап F3. AI UX (выполнено)
- Реализован `pages/app/ai-chat-page.vue`.
- Вынесено состояние AI-чата в `features/ai/use-ai-chat.js`.
- Добавлены:
  - список чатов + выбор активного;
  - создание нового чата;
  - выбор модели из `/api/ai/models`;
  - streaming-ответ;
  - отображение usage/cost после ответа (tokens + стоимость + остаток).

Критерии готовности F3:
- AI-экран не является placeholder.
- Есть отдельный feature-layer для AI, без бизнес-логики в layout.
- UI может выбрать модель до отправки сообщения.

### Этап F4. Billing UX (выполнено)
- Реализован `pages/app/subscription-page.vue`.
- Вынесено billing-состояние в `features/billing/use-billing.js`.
- Добавлены:
  - карточка активной подписки;
  - текущий credit balance;
  - список тарифов + checkout action;
  - таблица ledger;
  - таблица user payments.
- Добавлен backend endpoint `/api/billing/overview`.

Критерии готовности F4:
- Экран подписки не является placeholder.
- Пользователь видит текущую подписку, баланс и историю операций.
- Есть переход в YooMoney checkout через backend endpoint.

### Этап F5. Notifications + Support + Admin UX
- Реализованы рабочие страницы:
  - `/app/notifications`
  - `/app/support`
  - `/admin`
  - `/admin/users`
  - `/admin/tariffs`
  - `/admin/payments`
  - `/admin/ai-models`
  - `/admin/audit`
  - `/admin/support`
- Добавлены feature-модули:
  - `features/notifications/use-notifications.js`
  - `features/support/use-support.js`
  - `features/admin/use-admin.js`
- Подключены ключевые admin actions:
  - credit adjustment;
  - subscription cancel;
  - AI model create/update;
  - support reply/status update.

Критерии готовности F5:
- Основные user/admin сценарии выполняются из UI без ручных API-запросов.

### Этап F6. Hardening UX
- Базовый hardening выполнен:
  - добавлены состояния `loading/empty/error` на новых экранах F5;
  - добавлен `aria-live` для чат-логов поддержки;
  - ручки обновления данных (`Refresh`) для ключевых экранов.
- Полный F6 (e2e smoke + полный accessibility pass по всем экранам) остается следующим шагом.

## 4. Definition of Done для каждого этапа

- Экран имеет route, API-contract и явные состояния `loading/empty/error/success`.
- API-вызовы не хранятся в layout/root.
- Документация обновлена синхронно с изменениями.
