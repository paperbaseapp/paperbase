# Paperprism

## Development setup

### Prerequisites

- Docker
- Docker Compose

1. Copy `.env.example` to `.env`
2. Fill `APP_KEY` (32 random chars) and `DB_PASSWORD`
3. `docker-compose up`
4. Create a new user with `docker-compose exec app php artisan user:create`
5. You can access Paperprism at `http://paperprism.localhost`
