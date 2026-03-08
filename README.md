# FinWiseAi

AI-финансовый консультант (монорепо): frontend, backend, инфраструктура и документация.

## Быстрый старт (Этап 0)

Требования:
- Docker + Docker Compose

Запуск:

```bash
docker compose up -d
```

Проверка:
- Web: `http://localhost:8080`
- API health: `http://localhost:8080/api/health`

Остановка:

```bash
docker compose down
```

## Структура

- `apps/web` - frontend (Vue/PWA, на этапе 0 каркас).
- `apps/api` - backend (Laravel target, на этапе 0 минимальный PHP API stub).
- `docs` - документация и архитектурные материалы.
- `infra` - docker и CI-конфигурация.
- `scripts` - dev/ci/db скрипты.

