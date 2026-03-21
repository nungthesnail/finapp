# Frontend Screens

## 1. Route map (proposed)

- `/landing` — маркетинговый экран и вход в продукт.
- `/privacy` — политика конфиденциальности.
- `/help` — справка и FAQ.
- `/auth/login` — вход.
- `/auth/register` — регистрация.
- `/app` — dashboard.
- `/app/accounts` — счета.
- `/app/transactions` — операции.
- `/app/recurring` — периодические операции.
- `/app/plans` — бюджетные планы.
- `/app/analytics` — аналитика.
- `/app/ai` — AI чат.
- `/app/notifications` — уведомления.
- `/app/subscription` — подписка и кредиты.
- `/app/support` — поддержка.
- `/app/settings` — профиль и настройки.
- `/admin` — админ dashboard.
- `/admin/users` — пользователи.
- `/admin/tariffs` — тарифы.
- `/admin/payments` — платежи и подписки.
- `/admin/ai-models` — реестр AI-моделей.
- `/admin/audit` — журнал аудита.
- `/admin/support` — консоль поддержки.

## 2. Экранные контракты (минимум)

### Dashboard
- Виджеты: текущий баланс, доходы/расходы за период, net.
- Источники: `/api/analytics/summary`, `/api/accounts`.

### Transactions
- Таблица операций с фильтрами.
- Быстрое добавление расхода/дохода с default categories.
- Источники: `/api/transactions`, `/api/users/category-defaults`.

### AI Chat
- Список чатов + активный чат + потоковый ответ.
- Выбор модели из реестра.
- После `done` события показывать usage: `input_tokens`, `output_tokens`, `cached_input_tokens`, `total_cost_rub`, `balance_after_rub`.
- Источники: `/api/ai/chats`, `/api/ai/models`, `/api/ai/chats/{id}/messages/stream`.

### Subscription & Credits
- Текущая подписка, доступный кредит, история списаний.
- Источники: `/api/billing/overview`, `/api/tariffs`, `/api/subscriptions/checkout`.

### Notifications
- Список уведомлений, mark-as-read, push subscription flow.
- Источники: `/api/notifications`, `/api/notifications/{id}/read`, `/api/push/subscriptions`.

### Support (User)
- Список тикетов, история сообщений, отправка сообщения.
- Источники: `/api/support/chats`, `/api/support/chats/{id}/messages`.

### Admin Dashboard/Users/Payments/Audit/Support
- Просмотр статистики, пользователей, подписок/платежей, аудит-логов, переписки поддержки.
- Действия: cancel subscription, credit adjustment, admin reply.
- Источники: `/api/admin/*`, `/api/admin/support/chats*`.

### Admin AI Models
- CRUD реестра моделей и тарифов токенов.
- Источники: `/api/admin/ai/models`.

## 3. Общие состояния для каждого экрана

- `loading`: skeleton или progress.
- `empty`: понятный placeholder + CTA.
- `error`: user-friendly сообщение + retry.
- `success`: подтверждение действия (toast/in-page).
