# Production Deployment Guide

## Overview
This guide covers the steps to deploy your Game Configuration API to production with all security enhancements applied.

## Pre-Deployment Checklist

### 1. Environment Setup
- [ ] Copy `.env.example` to `.env` and configure with production values
- [ ] Set secure database credentials
- [ ] Generate a strong JWT secret (minimum 32 characters)
- [ ] Configure appropriate CORS origins for your domains
- [ ] Set production environment variables

### 2. Database Setup
- [ ] Create the database using `sql/schema.sql`
- [ ] Run the login attempts table migration: `sql/login_attempts_table.sql`
- [ ] Load test data if needed: `sql/test_data.sql`
- [ ] Change the default admin password immediately after first login

### 3. File Permissions
- [ ] Set appropriate permissions on `.env` (600)
- [ ] Ensure `logs/` directory is writable (755)
- [ ] Verify `cache/` and `rate_limits/` directories are writable (755)

### 4. Security Configuration
- [ ] Update `.htaccess` with your domain
- [ ] Configure HTTPS/SSL certificate
- [ ] Set up firewall rules
- [ ] Configure backup strategy

## Environment Variables

Create a `.env` file with the following configuration:

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=game_config_api
DB_USERNAME=your_secure_db_user
DB_PASSWORD=your_secure_db_password
DB_CHARSET=utf8mb4

# Security Configuration
JWT_SECRET=your-very-secure-jwt-secret-key-minimum-32-characters-long
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://game.yourdomain.com

# API Configuration
API_VERSION=1.0.0
ENVIRONMENT=production

# Rate Limiting
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

# Session Configuration
SESSION_LIFETIME=1800
```

## Security Enhancements Applied

### 1. Authentication & Authorization
- ✅ Brute force protection with configurable limits
- ✅ Stronger API key hashing (SHA384 + RIPEMD160)
- ✅ Session timeout management
- ✅ Secure password hashing with bcrypt

### 2. Input Validation
- ✅ Comprehensive input sanitization
- ✅ JSON schema validation
- ✅ Size limits on configuration values
- ✅ Configuration key format validation

### 3. Database Security
- ✅ Singleton connection pattern
- ✅ Prepared statements for all queries
- ✅ Connection pooling enabled
- ✅ Secure error handling

### 4. API Security
- ✅ Rate limiting per IP
- ✅ CORS protection
- ✅ Security headers
- ✅ Request validation

## Deployment Steps

### 1. Setup Environment
```bash
# Copy environment template
cp .env.example .env

# Edit with production values
nano .env

# Set proper permissions
chmod 600 .env
```

### 2. Initialize Database
```bash
# Create database and tables
mysql -u root -p < sql/schema.sql

# Create login attempts table
mysql -u root -p < sql/login_attempts_table.sql

# Optional: Load test data
mysql -u root -p < sql/test_data.sql
```

### 3. Configure Web Server

#### Apache Configuration
Ensure `.htaccess` is enabled and `mod_rewrite` is active:

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /path/to/game-config-api
    
    # Enable .htaccess
    AllowOverride All
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/game-config-api;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # PHP-FPM configuration
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # URL rewriting
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### 4. Post-Deployment
1. **Change Admin Password**: Login to the admin panel and immediately change the default password
2. **Verify HTTPS**: Ensure all requests are redirected to HTTPS
3. **Test API Endpoints**: Verify all API functionality works correctly
4. **Monitor Logs**: Check error logs for any issues
5. **Setup Monitoring**: Configure uptime and performance monitoring

## Monitoring & Maintenance

### Log Files
- Application logs: `logs/error.log`
- PHP errors: Configured in `.htaccess`
- Access logs: Web server access logs

### Backup Strategy
1. **Database Backup**: Daily automated backups
2. **File Backup**: Weekly full backup of application files
3. **Configuration Backup**: Backup `.env` file securely

### Security Monitoring
- Monitor failed login attempts
- Watch for unusual API usage patterns
- Regular security updates
- Periodic security audits

## Performance Optimization

### Database Optimization
- Add indexes for frequently queried columns
- Configure query cache
- Monitor slow queries

### Caching
- Enable output caching for static content
- Consider Redis for session storage
- Implement API response caching where appropriate

## Troubleshooting

### Common Issues
1. **Database Connection Errors**: Check `.env` configuration
2. **Permission Errors**: Verify file/directory permissions
3. **API Key Issues**: Regenerate API keys if needed
4. **Rate Limiting**: Adjust limits in configuration

### Debug Mode
Never enable debug mode in production. If needed for troubleshooting:
```bash
# Temporary debug (remove immediately after use)
sed -i 's/production/development/' .env
```

Remember to revert back to production mode!

## Support
For issues or questions:
1. Check the error logs first
2. Verify all environment variables are set correctly
3. Ensure all security configurations are in place
4. Test with the Unity client integration examples