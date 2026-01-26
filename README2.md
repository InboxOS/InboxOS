# Podman-based Email Server with Modern Web UI

A complete, self-hosted email server solution built with Podman containers, featuring:
- docker-mailserver for reliable email delivery
- Symfony 7.4 dashboard for management
- Horde 6 webmail interface
- Automatic TLS via Let's Encrypt
- Comprehensive security features

## Features

### Security
- TLS 1.3 encryption for all connections
- MTA-STS enforcement for secure server-to-server connections
- SPF, DKIM, and DMARC authentication
- Two-factor authentication (2FA) for admin dashboard
- Fail2ban for brute force protection
- ClamAV and SpamAssassin integration
- DANE support for TLSA records

### Management
- Modern Symfony dashboard with Chart.js statistics
- Domain and user management
- Quota management per user/tenant
- Backup and restore functionality
- Dashboard statistics with Chart.js
- Multi-tenancy support

### Webmail
- Horde 6 with modern interface
- Calendar, contacts, tasks
- Sieve filter management
- Mobile-friendly design

## Quick Start

### Prerequisites
- Podman or Docker installed
- Domain name with proper DNS setup
- Ports 25, 80, 443, 587, 993 open

### Installation

1. Clone the repository:
```bash
git clone https://github.com/your-repo/email-server.git
cd email-server

2. Configure environment variables:
```bash
cp env.example .env
nano .env
```

Edit the `.env` file with your configuration:
- `DOMAIN`: Your domain name (e.g., example.com)
- `ADMIN_EMAIL`: Admin email address
- `MYSQL_ROOT_PASSWORD`: MySQL root password
- `MYSQL_PASSWORD`: MySQL application password
- `REDIS_PASSWORD`: Redis password
- `APP_SECRET`: Random secret for Symfony (generate with `openssl rand -base64 32`)

3. Run the setup script:
```bash
sudo ./setup-complete.sh
```

Or manually:
```bash
# Create necessary directories
./deploy.sh

# Or run containers manually
podman-compose build
podman-compose up -d
```

4. Initialize the database:
```bash
podman-compose exec symfony-dashboard php bin/console doctrine:database:create
podman-compose exec symfony-dashboard php bin/console doctrine:schema:update --force
podman-compose exec symfony-dashboard php bin/console doctrine:fixtures:load
```

5. Create admin user:
```bash
podman-compose exec symfony-dashboard php bin/console app:create-admin --email=your-admin@example.com --password=ChangeMe123
```

6. Configure DNS records as shown in the dashboard (MX, SPF, DKIM, DMARC)

## Usage

### Accessing the Dashboard

- **Dashboard**: https://yourdomain.com
- **Webmail**: https://webmail.yourdomain.com
- **API**: https://yourdomain.com/api/

### Default Credentials

- **Username**: admin@yourdomain.com
- **Password**: ChangeMe123 (change immediately!)

### API Usage

```bash
# List domains
curl -H "Authorization: Bearer YOUR_API_KEY" https://yourdomain.com/api/domains

# Create domain
curl -X POST -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"newdomain.com"}' \
  https://yourdomain.com/api/domains
```

## Maintenance

### Backup

```bash
# Manual backup
podman-compose exec backup ./backup-scripts/backup.sh

# Automated backups run daily at 2 AM via systemd timer
```

### Restore

```bash
# Stop services first
podman-compose down

# Restore from backup
./backup-scripts/restore.sh 20240115_120000

# Start services
podman-compose up -d
```

### Certificate Management

```bash
# Renew Let's Encrypt certificates
podman-compose exec certbot certbot renew

# Check certificate status
podman-compose exec nginx ./check-certs.sh
```

### Monitoring

- **Dashboard Statistics**: Chart.js-powered graphs for mail traffic and storage usage
- **Logs**: All services log to `/var/log/mail/` and individual container logs
- **Health checks**: Built-in health checks for all services

## Security Features

### Email Security
- **SPF**: Sender Policy Framework validation
- **DKIM**: DomainKeys Identified Mail signing
- **DMARC**: Domain-based Message Authentication
- **MTA-STS**: Strict Transport Security for mail
- **TLS**: Mandatory encryption for all connections

### System Security
- **Fail2ban**: Brute force protection
- **Firewall**: UFW with restricted ports
- **2FA**: Two-factor authentication for admin accounts
- **Rate limiting**: API and web interface rate limiting

## Troubleshooting

### Common Issues

1. **Emails not delivering**:
   - Check DNS records (MX, SPF, DKIM)
   - Verify firewall allows ports 25, 587, 465
   - Check mail logs: `podman-compose logs mailserver`

2. **SSL certificate issues**:
   - Ensure DNS points to your server
   - Check Let's Encrypt logs: `podman-compose logs certbot`
   - Verify ports 80/443 are open

3. **Dashboard not accessible**:
   - Check nginx logs: `podman-compose logs nginx`
   - Verify SSL certificates are valid
   - Check Symfony logs: `podman-compose logs symfony-dashboard`

### Logs and Debugging

```bash
# View all logs
podman-compose logs

# View specific service logs
podman-compose logs mailserver
podman-compose logs symfony-dashboard

# Enter container for debugging
podman-compose exec mailserver bash
```

## Development

### Local Development Setup

```bash
# Install dependencies
composer install
npm install

# Start development server
symfony server:start

# Build assets
npm run build
```

### Testing

```bash
# Run PHP tests
php bin/phpunit

# Run frontend tests
npm test
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Review logs for error details