# Ubuntu Server Installation Guide for API Poller

## Overview
This document provides complete installation instructions for deploying the API Poller application on an Ubuntu server. The API Poller is a PHP command-line application that periodically queries RESTful APIs for measurement data from VEMS systems.

## System Requirements

### Minimum Requirements
- Ubuntu 18.04 LTS or newer (20.04 LTS or 22.04 LTS recommended)
- 1 GB RAM (2 GB recommended)
- 1 GB available disk space
- Network connectivity to target API endpoints
- Sudo/root access for initial setup

### Software Dependencies
- PHP 8.1 or higher with CLI support
- PHP Extensions: `curl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `mysqli`
- MySQL 8.0 or MariaDB 10.5+ (for data storage)
- Git (for cloning repository)
- systemd (for service management)
- Optional: Docker and Docker Compose (for containerized deployment)

## Installation Steps

### 1. System Preparation

#### Update system packages
```bash
sudo apt update && sudo apt upgrade -y
```

#### Install required PHP and system packages
```bash
# Install PHP 8.1 and required extensions
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.1 php8.1-cli php8.1-curl php8.1-json php8.1-mbstring php8.1-openssl php8.1-pdo php8.1-mysql php8.1-mysqli

# Install MySQL Server
sudo apt install -y mysql-server-8.0 mysql-client-core-8.0

# Install additional tools
sudo apt install -y git curl unzip flock ca-certificates openssl

# Update CA certificates bundle (for HTTPS API endpoints)
sudo apt install -y ca-certificates
sudo update-ca-certificates
```

#### Configure SSL/TLS for HTTPS APIs
```bash
# Verify SSL/TLS support in PHP
php -m | grep openssl

# Test HTTPS connectivity to a known endpoint
curl -v https://www.google.com

# If you need to add custom CA certificates (optional)
# sudo cp your-custom-ca.crt /usr/local/share/ca-certificates/
# sudo update-ca-certificates
```

#### Verify PHP installation
```bash
php --version
php -m | grep -E "(curl|json|mbstring|openssl|pdo|mysql|mysqli)"
```

#### Configure MySQL Server
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Start and enable MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql

# Verify MySQL is running
sudo systemctl status mysql
```

### 2. Application Deployment

#### Create application directory and user
```bash
# Create dedicated user (recommended for security)
sudo useradd --system --no-create-home --shell /usr/sbin/nologin api-poller

# Create application directory
sudo mkdir -p /opt/api-poller
```

#### Clone repository
```bash
# Navigate to temporary directory
cd /tmp

# Clone the repository
git clone https://github.com/southpark9902/api-poller.git api-poller-src

# Copy application files
sudo cp -r api-poller-src/* /opt/api-poller/
sudo rm -rf api-poller-src
```

#### Set permissions
```bash
# Create storage directory
sudo mkdir -p /opt/api-poller/storage

# Set ownership and permissions
sudo chown -R api-poller:api-poller /opt/api-poller
sudo chmod -R 755 /opt/api-poller
sudo chmod 775 /opt/api-poller/storage
```

### 3. Database Setup

#### Create database and user
```bash
# Connect to MySQL as root
sudo mysql

# Create database and user (run these SQL commands in MySQL prompt)
CREATE DATABASE api_poller;
CREATE USER 'api_poller'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON api_poller.* TO 'api_poller'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Create database tables
```bash
# Create the database schema file
sudo tee /opt/api-poller/schema.sql > /dev/null << 'EOF'
CREATE TABLE IF NOT EXISTS timeseries_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    measurement_id VARCHAR(255) NOT NULL,
    value DECIMAL(15,6) DEFAULT NULL,
    quality_code VARCHAR(50) DEFAULT NULL,
    unit VARCHAR(50) DEFAULT NULL,
    source_api VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_measurement_id (measurement_id),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS api_poll_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    api_endpoint VARCHAR(500) NOT NULL,
    response_status INT DEFAULT NULL,
    records_processed INT DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    execution_time_ms INT DEFAULT NULL,
    INDEX idx_poll_timestamp (poll_timestamp),
    INDEX idx_api_endpoint (api_endpoint)
);
EOF

# Import the schema
mysql -u api_poller -p api_poller < /opt/api-poller/schema.sql
```

### 4. Configuration

#### Create environment configuration
```bash
# Copy environment template (if exists)
if [ -f /opt/api-poller/.env.example ]; then
    sudo cp /opt/api-poller/.env.example /opt/api-poller/.env
fi

# Create systemd environment file
sudo tee /etc/default/api-poller > /dev/null << 'EOF'
# API Poller Environment Configuration
APP_ENV=production
API_BASE=https://your-device.example.com/api/
STORAGE_DIR=/opt/api-poller/storage

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=api_poller
DB_USER=api_poller
DB_PASSWORD=your_secure_password

# SSL/TLS Configuration
SSL_VERIFY_PEER=true
SSL_VERIFY_HOST=true
SSL_CAFILE=/etc/ssl/certs/ca-certificates.crt
# Uncomment and set if using custom CA certificate
# SSL_CAFILE=/path/to/your/custom-ca.crt
EOF
```

#### Edit configuration (adjust as needed)
```bash
sudo nano /etc/default/api-poller
```

**Important Configuration Notes:**
- Set `API_BASE` to your actual VEMS API endpoint URL (must use HTTPS for secure communication)
- Ensure `STORAGE_DIR` points to a writable directory
- Set `APP_ENV=production` for production deployments
- Update `DB_PASSWORD` with the actual database password you created
- Keep `SSL_VERIFY_PEER=true` and `SSL_VERIFY_HOST=true` for secure HTTPS connections
- If using self-signed certificates or custom CA, set `SSL_CAFILE` to the appropriate certificate file
- Ensure database credentials are kept secure
- Never commit `.env` files containing secrets to source control

### 5. Create Main Entry Point (if missing)

If the `api-poller.php` file doesn't exist in the repository, create it:

```bash
sudo tee /opt/api-poller/api-poller.php > /dev/null << 'EOF'
<?php
/**
 * Main entry point for API Poller
 * Runs the timeseries search poller
 */

// Include autoloader
require_once __DIR__ . '/autoload.php';

// Load environment
if (file_exists(__DIR__ . '/.env')) {
    load_dotenv(__DIR__ . '/.env');
}

// Run the timeseries search poller
require_once __DIR__ . '/api-pollers/timeseries-search.php';
EOF

sudo chown api-poller:api-poller /opt/api-poller/api-poller.php
sudo chmod 755 /opt/api-poller/api-poller.php
```

### 6. Systemd Service Setup

#### Create service unit file
```bash
sudo tee /etc/systemd/system/api-poller.service > /dev/null << 'EOF'
[Unit]
Description=API Poller (one-shot)
After=network.target

[Service]
Type=oneshot
User=api-poller
WorkingDirectory=/opt/api-poller
EnvironmentFile=/etc/default/api-poller
# Use flock to prevent overlapping runs
ExecStart=/usr/bin/flock -n /var/lock/api-poller.lock /usr/bin/php /opt/api-poller/api-poller.php
TimeoutStartSec=120
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
```

#### Create timer unit file (runs every 10 minutes)
```bash
sudo tee /etc/systemd/system/api-poller.timer > /dev/null << 'EOF'
[Unit]
Description=Run API poller every 10 minutes
Requires=api-poller.service

[Timer]
OnBootSec=1min
OnUnitActiveSec=10min
Persistent=true

[Install]
WantedBy=timers.target
EOF
```

#### Create lock file
```bash
sudo mkdir -p /var/lock
sudo touch /var/lock/api-poller.lock
sudo chown api-poller:api-poller /var/lock/api-poller.lock
sudo chmod 644 /var/lock/api-poller.lock
```

### 7. Enable and Start Services

#### Reload systemd and enable timer
```bash
sudo systemctl daemon-reload
sudo systemctl enable api-poller.timer
sudo systemctl start api-poller.timer
```

#### Verify installation
```bash
# Check timer status
sudo systemctl status api-poller.timer

# List all timers to confirm it's active
systemctl list-timers --all | grep api-poller

# Test manual run
sudo systemctl start api-poller.service
sudo systemctl status api-poller.service
```

## Alternative Installation Methods

### Docker Deployment (Optional)

If you prefer containerized deployment:

#### Install Docker and Docker Compose
```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Log out and back in to apply group membership
```

#### Deploy with Docker Compose
```bash
cd /opt/api-poller
sudo docker-compose up -d
```

## Management and Monitoring

### Service Management Commands
```bash
# Start the poller immediately
sudo systemctl start api-poller.service

# Stop the scheduled timer
sudo systemctl stop api-poller.timer

# Restart the timer
sudo systemctl restart api-poller.timer

# Disable the timer (stop all scheduled runs)
sudo systemctl disable api-poller.timer
```

### Monitoring and Logs
```bash
# View service logs
sudo journalctl -u api-poller.service -f

# View timer logs
sudo journalctl -u api-poller.timer -f

# Check recent service runs
sudo journalctl -u api-poller.service --since "1 hour ago"

# View storage directory contents
ls -la /opt/api-poller/storage/
```

### Log Rotation (Optional)
```bash
# Create logrotate configuration
sudo tee /etc/logrotate.d/api-poller > /dev/null << 'EOF'
/opt/api-poller/storage/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    su api-poller api-poller
}
EOF
```

## Security Considerations

### File Permissions
- Application files should be owned by `api-poller:api-poller`
- Configuration files should be readable only by the service user and root
- Storage directory must be writable by the service user

### Network Security
- Ensure firewall rules allow outbound HTTPS/HTTP to API endpoints
- Always use HTTPS for API communication in production
- Verify SSL/TLS certificates are valid and not expired
- Keep CA certificate bundle updated: `sudo update-ca-certificates`
- Consider using VPN or private networks for API communication
- For self-signed certificates, properly configure `SSL_CAFILE` in environment
- Monitor certificate expiration dates and renew before expiry
- Use strong TLS versions (TLS 1.2+ recommended)

### Secrets Management
- Never commit `.env` files with credentials to source control
- Use strong, unique API keys when available
- Regularly rotate API credentials
- Consider using systemd credentials for sensitive values

## Troubleshooting

### Common Issues

#### Service won't start
```bash
# Check service status
sudo systemctl status api-poller.service -l

# Check system logs
sudo journalctl -u api-poller.service --since "10 minutes ago"

# Verify PHP syntax
sudo -u api-poller php -l /opt/api-poller/api-poller.php
```

#### Timer not running
```bash
# Check timer status
sudo systemctl list-timers | grep api-poller

# Reload systemd if timer was modified
sudo systemctl daemon-reload
sudo systemctl restart api-poller.timer
```

#### Permission errors
```bash
# Fix ownership
sudo chown -R api-poller:api-poller /opt/api-poller

# Check storage directory permissions
ls -la /opt/api-poller/storage/
```

#### Database connectivity issues
```bash
# Test database connection
mysql -u api_poller -p -h localhost api_poller

# Check MySQL service status
sudo systemctl status mysql

# View MySQL error logs
sudo tail -f /var/log/mysql/error.log

# Check database tables exist
mysql -u api_poller -p api_poller -e "SHOW TABLES;"
```

#### SSL/TLS Certificate issues
```bash
# Test SSL certificate validity for your API endpoint
openssl s_client -connect your-api-endpoint.com:443 -servername your-api-endpoint.com

# Check certificate expiration
echo | openssl s_client -connect your-api-endpoint.com:443 2>/dev/null | openssl x509 -dates -noout

# Verify CA certificates are up to date
sudo update-ca-certificates --verbose

# Test HTTPS connection with curl (detailed output)
curl -v -I https://your-api-endpoint.com/api/

# If using self-signed certificates, add --insecure flag for testing
curl --insecure -v -I https://your-api-endpoint.com/api/

# Check PHP OpenSSL configuration
php -r "echo 'OpenSSL version: ' . OPENSSL_VERSION_TEXT . "\n";"
php -r "var_dump(openssl_get_cert_locations());"
```

#### API connectivity issues
```bash
# Test API connectivity manually
sudo -u api-poller curl -v "https://your-api-endpoint.com/api/health"

# Check DNS resolution
nslookup your-api-endpoint.com

# Verify SSL/TLS certificates
openssl s_client -connect your-api-endpoint.com:443 -servername your-api-endpoint.com
```

### Performance Tuning

For high-frequency polling or large result sets:

#### Increase timeout values
```bash
# Edit service file
sudo nano /etc/systemd/system/api-poller.service

# Add or modify:
# TimeoutStartSec=300
# Environment=HTTP_TIMEOUT=30
```

#### Storage optimization
```bash
# Compress old result files
find /opt/api-poller/storage -name "*.json" -mtime +7 -exec gzip {} \;

# Set up automatic cleanup of old files
echo '0 2 * * * find /opt/api-poller/storage -name "*.json.gz" -mtime +30 -delete' | sudo crontab -u api-poller -
```

## Maintenance

### Regular Tasks
1. Monitor log files for errors or warnings
2. Check available disk space in storage directory
3. Verify API connectivity and credentials
4. Monitor SSL certificate expiration dates
5. Update CA certificates bundle regularly
6. Update system packages regularly
7. Review and rotate log files

### Updates
```bash
# Update application code
cd /opt/api-poller
sudo git pull origin main
sudo systemctl restart api-poller.timer

# Update system packages
sudo apt update && sudo apt upgrade -y
```

## Quick Installation Summary

For experienced users, here's a condensed installation checklist:

1. **Install dependencies**: `sudo apt install -y php8.1 php8.1-cli php8.1-curl php8.1-json php8.1-mbstring php8.1-openssl php8.1-pdo php8.1-mysql php8.1-mysqli mysql-server-8.0 git curl unzip flock ca-certificates openssl`
2. **Setup MySQL**: `sudo mysql_secure_installation` and create database/user as shown above
3. **Create user**: `sudo useradd --system --no-create-home --shell /usr/sbin/nologin api-poller`
4. **Clone repository**: `git clone https://github.com/southpark9902/api-poller.git /opt/api-poller`
5. **Create database schema**: Import `schema.sql` as shown in Database Setup section
6. **Set permissions**: `sudo chown -R api-poller:api-poller /opt/api-poller && sudo chmod 775 /opt/api-poller/storage`
7. **Configure environment**: Edit `/etc/default/api-poller` with API endpoint and database credentials
8. **Create systemd files**: Copy service and timer unit files from this guide
9. **Enable service**: `sudo systemctl daemon-reload && sudo systemctl enable api-poller.timer && sudo systemctl start api-poller.timer`

This installation guide provides a comprehensive setup for running the API Poller on Ubuntu with proper security, monitoring, and management practices.