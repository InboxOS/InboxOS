<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial mail server schema';
    }

    public function up(Schema $schema): void
    {
        // Tenants table
        $this->addSql('
            CREATE TABLE tenants (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                quota_mb INT DEFAULT NULL,
                user_limit INT DEFAULT NULL,
                domain_limit INT DEFAULT NULL,
                settings LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_6DAD7E4A989D9B62 (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Domains table
        $this->addSql('
            CREATE TABLE domains (
                id INT AUTO_INCREMENT NOT NULL,
                tenant_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                enable_spf TINYINT(1) DEFAULT 1 NOT NULL,
                enable_dkim TINYINT(1) DEFAULT 1 NOT NULL,
                enable_dmarc TINYINT(1) DEFAULT 1 NOT NULL,
                enable_mta_sts TINYINT(1) DEFAULT 1 NOT NULL,
                dkim_selector VARCHAR(255) DEFAULT NULL,
                dkim_private_key LONGTEXT DEFAULT NULL,
                dkim_public_key LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                quota_mb INT DEFAULT NULL,
                UNIQUE INDEX UNIQ_8C7BBF9D5E237E06 (name),
                INDEX IDX_8C7BBF9D9033212A (tenant_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Mail users table
        $this->addSql('
            CREATE TABLE mail_users (
                id INT AUTO_INCREMENT NOT NULL,
                domain_id INT DEFAULT NULL,
                tenant_id INT DEFAULT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                roles JSON NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                two_factor_secret VARCHAR(255) DEFAULT NULL,
                is_two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL,
                quota_used INT DEFAULT 0,
                quota_limit INT DEFAULT 1024,
                created_at DATETIME NOT NULL,
                last_login DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_9C3DCB00E7927C74 (email),
                INDEX IDX_9C3DCB00115F0EE5 (domain_id),
                INDEX IDX_9C3DCB00903212A (tenant_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Aliases table
        $this->addSql('
            CREATE TABLE aliases (
                id INT AUTO_INCREMENT NOT NULL,
                domain_id INT DEFAULT NULL,
                source VARCHAR(255) NOT NULL,
                destination VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_CADCC7C3115F0EE5 (domain_id),
                UNIQUE INDEX unique_alias (source, domain_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Mail logs table
        $this->addSql('
            CREATE TABLE mail_logs (
                id INT AUTO_INCREMENT NOT NULL,
                domain_id INT DEFAULT NULL,
                user_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                sender VARCHAR(255) DEFAULT NULL,
                recipient VARCHAR(255) DEFAULT NULL,
                client_ip VARCHAR(255) DEFAULT NULL,
                message VARCHAR(1000) DEFAULT NULL,
                size INT DEFAULT 0 NOT NULL,
                status VARCHAR(50) DEFAULT NULL,
                timestamp DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_48E7B1E7115F0EE5 (domain_id),
                INDEX IDX_48E7B1E7A76ED395 (user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_client_ip (client_ip),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // API keys table
        $this->addSql('
            CREATE TABLE api_keys (
                id INT AUTO_INCREMENT NOT NULL,
                tenant_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                permissions JSON NOT NULL,
                created_at DATETIME NOT NULL,
                last_used DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                UNIQUE INDEX UNIQ_9579321F5F37A13B (token),
                INDEX IDX_9579321F903212A (tenant_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Foreign keys
        $this->addSql('
            ALTER TABLE domains
            ADD CONSTRAINT FK_8C7BBF9D9033212A
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE mail_users
            ADD CONSTRAINT FK_9C3DCB00115F0EE5
            FOREIGN KEY (domain_id) REFERENCES domains (id)
            ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE mail_users
            ADD CONSTRAINT FK_9C3DCB00903212A
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE aliases
            ADD CONSTRAINT FK_CADCC7C3115F0EE5
            FOREIGN KEY (domain_id) REFERENCES domains (id)
            ON DELETE CASCADE
        ');

        $this->addSql('
            ALTER TABLE mail_logs
            ADD CONSTRAINT FK_48E7B1E7115F0EE5
            FOREIGN KEY (domain_id) REFERENCES domains (id)
            ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE mail_logs
            ADD CONSTRAINT FK_48E7B1E7A76ED395
            FOREIGN KEY (user_id) REFERENCES mail_users (id)
            ON DELETE SET NULL
        ');

        $this->addSql('
            ALTER TABLE api_keys
            ADD CONSTRAINT FK_9579321F903212A
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domains DROP FOREIGN KEY FK_8C7BBF9D9033212A');
        $this->addSql('ALTER TABLE mail_users DROP FOREIGN KEY FK_9C3DCB00115F0EE5');
        $this->addSql('ALTER TABLE mail_users DROP FOREIGN KEY FK_9C3DCB00903212A');
        $this->addSql('ALTER TABLE aliases DROP FOREIGN KEY FK_CADCC7C3115F0EE5');
        $this->addSql('ALTER TABLE mail_logs DROP FOREIGN KEY FK_48E7B1E7115F0EE5');
        $this->addSql('ALTER TABLE mail_logs DROP FOREIGN KEY FK_48E7B1E7A76ED395');
        $this->addSql('ALTER TABLE api_keys DROP FOREIGN KEY FK_9579321F903212A');

        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TABLE domains');
        $this->addSql('DROP TABLE mail_users');
        $this->addSql('DROP TABLE aliases');
        $this->addSql('DROP TABLE mail_logs');
        $this->addSql('DROP TABLE api_keys');
    }
}
