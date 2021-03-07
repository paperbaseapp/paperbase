![Paperbase logo](./.github/logo.svg)

![Docker Cloud Build Status](https://img.shields.io/docker/cloud/build/paperbaseapp/paperbase?style=for-the-badge)
![License](https://img.shields.io/badge/license-AGPL%20v3-green?style=for-the-badge)
![latest release](https://img.shields.io/github/v/release/paperbaseapp/paperbase?include_prereleases&style=for-the-badge)
![Built with science](https://img.shields.io/badge/built%20with-science-lightgrey?style=for-the-badge)

## Quick start

Read [the docs](https://docs.paperbase.app) on how to set up your own Paperbase instance.

## Development setup

### Prerequisites

- Docker
- Docker Compose

1. Copy `.env.example` to `.env`
2. Fill `APP_KEY` (32 random chars) and `DB_PASSWORD`
3. `docker-compose up`
4. Create a new user with `docker-compose exec app php artisan user:create`
5. You can access Paperbase at `http://paperbase.localhost`
