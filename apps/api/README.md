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

Recurring processing command:

```bash
php artisan app:process-recurring
```

