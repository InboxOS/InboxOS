#!/bin/bash

# Quota warning script for Dovecot

PERCENT=$1
USER=$2

# Send warning email
cat << EOF | /usr/sbin/sendmail -t
To: $USER
From: postmaster@${DOMAIN}
Subject: Mailbox Quota Warning

Your mailbox is ${PERCENT}% full.

Please delete some messages or contact your administrator
to increase your quota.

Current quota usage: ${PERCENT}%
EOF

# Log warning
logger -p mail.info "Quota warning sent to $USER: ${PERCENT}% full"