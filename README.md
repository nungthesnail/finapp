# FinWiseAi

Monorepo for AI financial assistant.

## Stack

- Frontend: Vue 3 + Vite (`apps/web`)
- Backend: Laravel 12 (`apps/api`)
- DB: MySQL 8 container (Laravel tests use SQLite in-memory)
- Reverse proxy: Nginx (`localhost:8080`)

## Run locally

```bash
docker compose up -d
```

Open:
- Web: `http://localhost:8080`
- API health: `http://localhost:8080/api/health`

Demo admin:
- phone: `+79990000000`
- password: `admin123`

Stop:

```bash
docker compose down
```

## Backend checks

```bash
docker compose exec -T api php artisan migrate:fresh --seed --force
docker compose exec -T api php artisan test
```

## Frontend check

```bash
docker compose exec -T web npm run build
```

