# Podman/Docker Email Server + Symfony Dashboard

Self-hosted email server stack with:
- `docker-mailserver` (SMTP/IMAP/POP3)
- Symfony 7.4 dashboard (admin UI)
- Optional webmail (Horde) via a compose profile

## Quick Start (localhost, no certificates)

This mode is intended for local development/testing. **No certificates are created** when `DOMAIN=localhost`.

```bash
cp .env.example .env

# Required for localhost dev:
# - DOMAIN=localhost
# - MAILSERVER_SSL_TYPE=none
# - ADMIN_PASSWORD=...  (dashboard login password)
nano .env

./deploy.sh
```

Open the dashboard:
- `http://localhost:${HTTP_PORT:-8080}/login`

## Quick Start (production with Let’s Encrypt)

Rootful (recommended) is simplest because it can bind privileged ports (25/80/443/...).

```bash
cp .env.example .env
nano .env   # Set DOMAIN=example.com, ADMIN_EMAIL, passwords, SERVER_IP, etc.

sudo ./deploy.sh
```

Open the dashboard:
- `https://example.com`

## Rootless Podman notes (important)

Rootless Podman cannot bind ports <1024. The stack supports **high-port overrides** via `.env`:
- `HTTP_PORT`, `HTTPS_PORT`
- `SMTP_PORT`, `SUBMISSION_PORT`, `SMTPS_PORT`
- `IMAP_PORT`, `IMAPS_PORT`
- `POP3_PORT`, `POP3S_PORT`

See `.env.example` for defaults.

## Compose profiles

- **`letsencrypt`**: enables `certbot` (used automatically by `deploy.sh` when `DOMAIN != localhost`)
- **`webmail`**: enables `horde` (optional; may require changing the image if it’s not publicly pullable)

Manual examples:

```bash
# Production TLS services
podman-compose --profile letsencrypt up -d --build

# Optional webmail (if configured)
podman-compose --profile webmail up -d
```
