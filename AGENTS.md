# AGENTS.md

Guidance for automated agents and contributors working in this repo.

## Project overview

This repository is a **self-hosted email server stack** (Docker/Podman) with a **Symfony dashboard**.

- **Root stack**: `docker-compose.yml`
- **Dashboard app**: `symfony-dashboard/` (Symfony 7.4)
- **Reverse proxy**: `nginx/`
- **Mail server**: docker-mailserver (container image)
- **Webmail**: Horde (container image)

## Safety / production rules

- **Never commit secrets**:
  - Do not commit real `.env` values, credentials, private keys, or certificates.
  - Treat `certificates/`, `data/`, `backups/` as sensitive/runtime data.
- **Prefer reproducible changes**:
  - Update `composer.json` via `composer require …` (keeps `composer.lock` consistent).
  - Update Node dependencies via `npm install` / `npm update` in `symfony-dashboard/` if needed.
- **Don’t break existing deployment scripts**: `deploy.sh`, `setup-complete.sh`, `setup-mailserver.sh`.

## Common commands

### Compose (root)

From repo root:

```bash
# build & start
podman-compose up -d --build
# or
docker-compose up -d --build

# logs
podman-compose logs -f

# stop
podman-compose down
```

### Symfony (dashboard)

From `symfony-dashboard/`:

```bash
composer install

# verify configuration
php bin/console lint:twig templates
php bin/console lint:yaml config --parse-tags
php bin/console lint:container

# verify routes
php bin/console debug:router | head -n 40

# warm prod cache
php bin/console cache:warmup --env=prod
```

## 2FA (Scheb 2FA bundle)

This project uses Scheb 2FA TOTP:

- Packages: `scheb/2fa-bundle`, `scheb/2fa-totp`
- Config: `symfony-dashboard/config/packages/scheb_2fa.yaml`
- Routes: `symfony-dashboard/config/routes/scheb_2fa.yaml`
- User entity implements TOTP interface: `symfony-dashboard/src/Entity/MailUser.php`

If 2FA is changed, ensure **all three** stay consistent:
1) entity interface methods  
2) bundle config/routes  
3) templates (2FA form + users list display)

## What to check before finishing a change

- `php bin/console lint:twig templates`
- `php bin/console lint:yaml config --parse-tags`
- `php bin/console lint:container`
- `php bin/console cache:warmup --env=prod`

If changing dependencies:
- `composer install` (or `composer require …`) and ensure `composer.lock` is updated.

