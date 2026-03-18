# API (Laravel)

Key endpoints implemented for stages 1-2:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/me`
- `PUT /api/me`
- `GET /api/admin/users`
- `GET|POST|PUT|DELETE /api/accounts`
- `GET|POST|PUT|DELETE /api/income-categories`
- `GET|POST|PUT|DELETE /api/expense-categories`
- `GET|PUT /api/users/category-defaults`
- `GET|POST|PUT|DELETE /api/transactions`
- `GET|POST|PUT|DELETE /api/recurring-transactions`
- `GET|POST|PUT|DELETE /api/budget-plans`
- `GET /api/analytics/summary`
- `GET /api/analytics/timeseries`
- `GET /api/analytics/categories`
- `GET /api/ai/chats`
- `POST /api/ai/chats`
- `GET /api/ai/chats/last-active`
- `GET /api/ai/chats/{id}/messages`
- `POST /api/ai/chats/{id}/messages/stream`
- `GET /api/tariffs`
- `POST /api/subscriptions/checkout`
- `POST /api/payments/yoomoney/webhook`
- `GET /api/notifications`
- `PATCH /api/notifications/{id}/read`
- `POST /api/push/subscriptions`
- `GET|POST /api/support/chats`
- `GET|POST /api/support/chats/{id}/messages`
- `GET /api/admin/dashboard`
- `GET /api/admin/support/chats`
- `GET|POST /api/admin/support/chats/{id}/messages`
- `POST /api/admin/subscriptions/{id}/cancel`
- `POST /api/admin/users/{id}/credit-adjustment`
- `GET /api/admin/audit-logs`

Recurring processing command:

```bash
php artisan app:process-recurring
```

Technical AI control command:

```bash
php artisan app:ai-control-plan
```

YooMoney integration:
- checkout is implemented via `YooMoneyClient` (`sandbox` behavior when `YOOMONEY_ENABLED=false`);
- webhook endpoint is idempotent and finalizes payment -> subscription + credit ledger;
- live integration test with external gateway is intentionally deferred.

Stage 5 additions:
- in-app notifications with read status;
- browser push subscription endpoint for PWA clients;
- support chat between `USER` and `ADMIN`;
- admin dashboard/actions with audit log writes.
