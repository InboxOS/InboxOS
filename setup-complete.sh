#!/bin/bash

# Complete setup script for the mail server

set -e

echo "========================================="
echo "Mail Server Complete Setup"
echo "========================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root"
    exit 1
fi

# Install prerequisites
echo "Installing prerequisites..."
apt-get update
apt-get install -y \
    podman \
    podman-compose \
    git \
    curl \
    wget \
    openssl \
    certbot \
    python3-certbot-nginx \
    fail2ban \
    cron \
    unattended-upgrades

# Create user
echo "Creating mailadmin user..."
useradd -m -s /bin/bash mailadmin
usermod -aG docker mailadmin

# Clone repository
echo "Setting up directory structure..."
mkdir -p /opt/email-server
chown mailadmin:mailadmin /opt/email-server

# Copy files
cp -r . /opt/email-server/
chown -R mailadmin:mailadmin /opt/email-server

# Setup systemd services
echo "Setting up systemd services..."
cp systemd/*.service /etc/systemd/system/
cp systemd/*.timer /etc/systemd/system/

systemctl daemon-reload
systemctl enable mail-server.service
systemctl enable mail-server-backup.timer

# Setup firewall
echo "Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 25/tcp
ufw allow 587/tcp
ufw allow 465/tcp
ufw allow 993/tcp
ufw allow 995/tcp
ufw --force enable

# Setup fail2ban
echo "Configuring fail2ban..."
cp scripts/fail2ban-config.sh /tmp/
bash /tmp/fail2ban-config.sh
systemctl enable fail2ban
systemctl start fail2ban

# Setup automatic updates
echo "Configuring automatic updates..."
cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}";
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::MinimalSteps "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "02:00";
EOF

cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF

# Setup monitoring
echo "Setting up monitoring..."
apt-get install -y prometheus-node-exporter
systemctl enable prometheus-node-exporter
systemctl start prometheus-node-exporter

echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Edit /opt/email-server/.env with your domain"
echo "2. Run: cd /opt/email-server && ./deploy.sh"
echo "3. Configure DNS records as shown in dashboard"
echo "4. Access dashboard at: https://yourdomain.com"
echo "5. Access webmail at: https://webmail.yourdomain.com"
echo ""