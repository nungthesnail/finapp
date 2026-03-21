# Frontend Implementation Guide

## 1. Цель документа

Зафиксировать практические правила и текущий статус миграции frontend на модульную структуру.

## 2. Текущее состояние после F5

```text
src/
  app/
    router/
      index.js
    providers/
      auth-provider.js
    layout/
      app-layout.vue
      admin-layout.vue
      public-layout.vue
  pages/
    app/
      dashboard-page.vue
      accounts-page.vue
      transactions-page.vue
      recurring-page.vue
      plans-page.vue
      analytics-page.vue
      ai-chat-page.vue
      notifications-page.vue
      subscription-page.vue
      support-page.vue
      settings-page.vue
  features/
    ai/
      use-ai-chat.js
    admin/
      use-admin.js
    billing/
      use-billing.js
    finance/
      use-finance.js
    notifications/
      use-notifications.js
    support/
      use-support.js
  shared/
    api/
      client.js
```

## 3. Что сделано в F1-F5

- Root переведен на router-view.
- Session restore реализован через auth-provider.
- Введены guards для private/admin.
- Вынесен API-клиент.
- Реализованы user financial pages (accounts, transactions, recurring, plans, analytics, settings).
- Вынесено финансовое состояние и операции в `features/finance/use-finance.js`.
- Реализован полноценный AI чат с model picker и usage/cost UI.
- Вынесено AI состояние и streaming-обработка в `features/ai/use-ai-chat.js`.
- Реализован Billing UX экран с active subscription, credit balance, ledger history и checkout.
- Добавлен backend контракт `/api/billing/overview` для экрана подписки.
- Реализованы user pages: notifications, support.
- Реализованы admin pages: dashboard, users, tariffs, payments, ai-models, audit, support.
- Добавлены feature-модули notifications/support/admin.

## 4. Правила для следующих этапов

- Сохранить подход feature-state + thin pages/layout.
- Layout остается тонким (без бизнес-логики).
- Любые изменения route-map синхронизировать с `frontend-screens.md`.

## 5. Порядок миграции F6+

- Шаг 1: e2e smoke для critical flows.
- Шаг 2: accessibility pass по всем экранам (focus, labels, contrast, keyboard navigation).
- Шаг 3: унификация loading/empty/error/success состояний в shared UI.

## 6. Чеклист перед merge

- Добавлен/обновлен route и guard при необходимости.
- Пройдена сборка `npm run build` в `apps/web`.
- Документация обновлена при изменении поведения.
- Проверено mobile + desktop отображение затронутых экранов.
