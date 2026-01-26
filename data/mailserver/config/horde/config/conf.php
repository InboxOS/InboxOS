<?php

// Horde configuration
$conf['backend']['driver'] = 'imap';
$conf['mailer']['params']['host'] = 'mailserver';
$conf['mailer']['params']['port'] = 25;
$conf['mailer']['params']['secure'] = false;

$conf['imap']['driver'] = 'imap';
$conf['imap']['params']['hostspec'] = 'mailserver';
$conf['imap']['params']['port'] = 143;
$conf['imap']['params']['secure'] = false;

$conf['mta']['driver'] = 'smtp';
$conf['mta']['params']['host'] = 'mailserver';
$conf['mta']['params']['port'] = 587;
$conf['mta']['params']['secure'] = 'tls';

$conf['sessionhandler']['params']['hashtable'] = 'redis';
$conf['sessionhandler']['params']['hashtable_params'] = [
    'hostspec' => 'redis',
    'port' => 6379,
    'password' => getenv('REDIS_PASSWORD'),
];

$conf['prefs']['driver'] = 'Sql';
$conf['prefs']['params'] = [
    'phptype' => 'mysql',
    'hostspec' => 'mysql',
    'database' => 'horde',
    'username' => 'horde',
    'password' => getenv('MYSQL_PASSWORD'),
];

$conf['sql']['driver'] = 'pdo_mysql';
$conf['sql']['params'] = [
    'hostspec' => 'mysql',
    'database' => 'horde',
    'username' => 'horde',
    'password' => getenv('MYSQL_PASSWORD'),
];

$conf['auth']['admins'] = [getenv('ADMIN_EMAIL')];
$conf['auth']['driver'] = 'imap';
$conf['auth']['params']['hostspec'] = 'mailserver';
$conf['auth']['params']['port'] = 143;
$conf['auth']['params']['secure'] = false;

// Security
$conf['use_ssl'] = 2;
$conf['cookie']['secure'] = true;
$conf['cookie']['httponly'] = true;

// Logging
$conf['log']['enabled'] = true;
$conf['log']['priority'] = 'INFO';
$conf['log']['ident'] = 'HORDE';
$conf['log']['name'] = '/var/log/horde/horde.log';
$conf['log']['type'] = 'file';

// Cache
$conf['cache']['default_lifetime'] = 86400;
$conf['cache']['params']['driver'] = 'redis';
$conf['cache']['params']['hostspec'] = 'redis';
$conf['cache']['params']['port'] = 6379;
$conf['cache']['params']['password'] = getenv('REDIS_PASSWORD');
