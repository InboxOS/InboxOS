#!/bin/bash

# Fail2Ban configuration for mail server

set -e

CONFIG_DIR="/etc/fail2ban"

echo "Configuring Fail2Ban..."

# Create fail2ban configuration
cat > $CONFIG_DIR/jail.d/mailserver.conf << 'EOF'
[postfix]
enabled = true
port = smtp,465,submission
filter = postfix
logpath = /var/log/mail/mail.log
maxretry = 5
bantime = 3600
findtime = 600

[dovecot]
enabled = true
port = imap,imaps,pop3,pop3s
filter = dovecot
logpath = /var/log/mail/mail.log
maxretry = 5
bantime = 3600
findtime = 600

[sasl]
enabled = true
port = smtp,465,submission,imap,imaps,pop3,pop3s
filter = sasl
logpath = /var/log/mail/mail.log
maxretry = 3
bantime = 7200
findtime = 600

[roundcube]
enabled = true
port = http,https
filter = roundcube
logpath = /var/log/mail/mail.log
maxretry = 5
bantime = 3600
findtime = 600
EOF

# Create filters
cat > $CONFIG_DIR/filter.d/postfix.conf << 'EOF'
[Definition]
failregex = ^%(__prefix_line)sNOQUEUE: reject: RCPT from [^[]*\[<HOST>\]: 554 5\.7\.1 .*$
            ^%(__prefix_line)swarning: [-._\w]+\[<HOST>\]: SASL .* authentication failed$
            ^%(__prefix_line)sNOQUEUE: reject: RCPT from [^[]*\[<HOST>\]: 450 4\.7\.1 .*$
ignoreregex =
EOF

cat > $CONFIG_DIR/filter.d/dovecot.conf << 'EOF'
[Definition]
failregex = ^%(__prefix_line)s(?:Info|Warning|Error): (?:pam_unix|auth): authentication failure; .* rhost=<HOST>(?:\s+user=.*)?\s*$
            ^%(__prefix_line)s(?:Info|Warning|Error): (?:pam_unix|auth): .* authentication failure; .* rhost=<HOST>\s*$
ignoreregex =
EOF

cat > $CONFIG_DIR/filter.d/sasl.conf << 'EOF'
[Definition]
failregex = ^%(__prefix_line)swarning: [-._\w]+\[<HOST>\]: SASL (?:LOGIN|PLAIN|(?:CRAM|DIGEST)-MD5) authentication failed(: [ A-Za-z0-9+/]*=*)?\s*$
ignoreregex =
EOF

# Start fail2ban
if [ -f "/etc/init.d/fail2ban" ]; then
    /etc/init.d/fail2ban restart
fi

echo "Fail2Ban configuration completed!"