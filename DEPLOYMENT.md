# Deployment Guide - Smart Hospital Management System

This guide provides step-by-step instructions for deploying SHMS on various hosting platforms.

## 🚀 Quick Deployment Options

### Option 1: Free Hosting (Recommended for Development)
- **InfinityFree** - PHP + MySQL
- **000webhost** - PHP + MySQL
- **AwardSpace** - PHP + MySQL

### Option 2: Static Hosting + Backend as Service
- **Netlify** + **Firebase**
- **Vercel** + **Firebase**
- **GitHub Pages** + **Firebase**

### Option 3: Cloud Platforms
- **AWS EC2** - Full control
- **Google Cloud Platform** - Scalable
- **DigitalOcean** - Developer-friendly

---

## 📋 Step-by-Step Deployment

### Phase 1: Pre-Deployment Preparation

#### 1.1 Environment Setup
```bash
# Clone or download the project
git clone https://github.com/your-repo/shms.git
cd shms

# Install dependencies (if using Composer)
composer install

# Set up environment variables
cp .env.example .env
```

#### 1.2 Database Preparation
```bash
# Export database schema
mysqldump -u username -p smart_hospital > database_backup.sql

# Create production database
mysql -u username -p -e "CREATE DATABASE shms_production"
```

#### 1.3 Configuration Updates
```php
// config/database.php - Production settings
define('DB_HOST', 'your-production-host');
define('DB_NAME', 'shms_production');
define('DB_USER', 'production_user');
define('DB_PASS', 'secure_password');
define('SECURE', true);  // Enable HTTPS
```

---

## 🌐 Platform-Specific Deployment

### A. InfinityFree / 000webhost Deployment

#### Step 1: Account Setup
1. Sign up at [InfinityFree](https://infinityfree.net) or [000webhost](https://www.000webhost.com)
2. Create a new website
3. Note your database credentials

#### Step 2: File Upload
```bash
# Method 1: File Manager (Recommended for beginners)
1. Login to hosting control panel
2. Open File Manager
3. Upload all SHMS files to public_html directory

# Method 2: FTP (Advanced)
1. Use FileZilla or similar FTP client
2. Connect with provided FTP credentials
3. Upload files to public_html directory
```

#### Step 3: Database Setup
1. Access phpMyAdmin from control panel
2. Create new database (e.g., `smart_hospital`)
3. Import `database/database.sql`
4. Update `config/database.php` with your credentials

#### Step 4: Permissions
```bash
# Set write permissions for upload directories
chmod 755 uploads/
chmod 755 logs/
```

#### Step 5: Final Configuration
1. Update `config/database.php` with production settings
2. Test by visiting `your-domain.com`
3. Login with admin credentials

### B. Netlify + Firebase Deployment

#### Step 1: Firebase Setup
1. Create Firebase project at [Firebase Console](https://console.firebase.google.com)
2. Enable Firestore Database
3. Set up security rules
4. Note your Firebase configuration

#### Step 2: Frontend Preparation
```javascript
// assets/js/firebase-config.js
const firebaseConfig = {
  apiKey: "your-api-key",
  authDomain: "your-project.firebaseapp.com",
  projectId: "your-project-id",
  storageBucket: "your-project.appspot.com",
  messagingSenderId: "your-sender-id",
  appId: "your-app-id"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
```

#### Step 3: Netlify Deployment
```bash
# Install Netlify CLI
npm install -g netlify-cli

# Login to Netlify
netlify login

# Deploy site
netlify deploy --prod --dir=.
```

#### Step 4: Firebase Functions (Backend)
```javascript
// functions/index.js
const functions = require('firebase-functions');
const admin = require('firebase-admin');

admin.initializeApp();

exports.api = functions.https.onRequest((request, response) => {
  // Handle API requests
  response.json({status: 'ok'});
});
```

#### Step 5: Environment Variables
```bash
# Netlify environment variables
FIREBASE_API_KEY=your-api-key
FIREBASE_PROJECT_ID=your-project-id
```

### C. AWS EC2 Deployment

#### Step 1: EC2 Instance Setup
```bash
# Launch EC2 instance (Ubuntu 20.04 LTS)
1. Choose t2.micro (Free Tier eligible)
2. Configure security groups (HTTP, HTTPS, SSH)
3. Generate key pair
4. Launch instance
```

#### Step 2: Server Configuration
```bash
# SSH into instance
ssh -i your-key.pem ubuntu@your-ec2-ip

# Update system
sudo apt update && sudo apt upgrade -y

# Install LAMP stack
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql -y

# Install additional PHP extensions
sudo apt install php-curl php-json php-mbstring php-xml -y
```

#### Step 3: Database Setup
```bash
# Secure MySQL
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
CREATE DATABASE smart_hospital;
CREATE USER 'shms_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON smart_hospital.* TO 'shms_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import database schema
mysql -u shms_user -p smart_hospital < database.sql
```

#### Step 4: Apache Configuration
```bash
# Configure virtual host
sudo nano /etc/apache2/sites-available/shms.conf

# Add configuration
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/shms
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

# Enable site and rewrite module
sudo a2ensite shms
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Step 5: Deploy Application
```bash
# Clone repository
cd /var/www/html
sudo git clone https://github.com/your-repo/shms.git

# Set permissions
sudo chown -R www-data:www-data shms/
sudo chmod -R 755 shms/
sudo chmod -R 777 shms/uploads/ shms/logs/
```

#### Step 6: SSL Certificate (Let's Encrypt)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Obtain SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

---

## 🔧 Configuration for Production

### Security Settings
```php
// config/database.php - Production security
define('SECURE', true);
define('HASH_COST', 12);
define('TOKEN_LENGTH', 32);

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
```

### Email Configuration
```php
// Add to config/database.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
```

### Performance Optimization
```php
// Enable caching
define('ENABLE_CACHE', true);
define('CACHE_DURATION', 3600); // 1 hour

// Optimize database queries
define('DB_PERSISTENT', true);
```

---

## 🚦 Post-Deployment Checklist

### Security Verification
- [ ] HTTPS is properly configured
- [ ] Database credentials are secure
- [ ] File permissions are correct
- [ ] Error display is disabled in production
- [ ] Admin password has been changed

### Functionality Testing
- [ ] User registration works
- [ ] Login functionality works
- [ ] Dashboard loads correctly
- [ ] Patient management works
- [ ] Appointment booking works
- [ ] File uploads work
- [ ] Email notifications work

### Performance Checks
- [ ] Page load time < 3 seconds
- [ ] Mobile responsiveness verified
- [ ] Database queries optimized
- [ ] Images are optimized

### Monitoring Setup
- [ ] Error logging enabled
- [ ] Uptime monitoring configured
- [ ] Database backup scheduled
- [ ] SSL certificate renewal set

---

## 🔄 Maintenance & Updates

### Regular Maintenance Tasks

#### Daily
```bash
# Check error logs
tail -f logs/error.log

# Monitor disk space
df -h

# Check database performance
mysql -u root -p -e "SHOW PROCESSLIST;"
```

#### Weekly
```bash
# Database backup
mysqldump -u root -p smart_hospital > backups/weekly_$(date +%Y%m%d).sql

# Clear old logs
find logs/ -name "*.log" -mtime +7 -delete

# Update system packages
sudo apt update && sudo apt upgrade -y
```

#### Monthly
```bash
# Security updates
sudo apt update && sudo apt upgrade -y

# Review access logs
sudo tail -1000 /var/log/apache2/access.log

# Performance optimization
mysql -u root -p -e "OPTIMIZE TABLE patients, appointments, users;"
```

### Update Process
```bash
# 1. Backup current version
mysqldump -u root -p smart_hospital > backups/pre_update_$(date +%Y%m%d).sql
tar -czf backups/code_backup_$(date +%Y%m%d).tar.gz .

# 2. Download new version
git pull origin main

# 3. Update database if needed
mysql -u root -p smart_hospital < database/updates/v1.1.0.sql

# 4. Clear cache
rm -rf cache/*

# 5. Test functionality
# Run through functionality checklist

# 6. Monitor for issues
tail -f logs/error.log
```

---

## 🚨 Troubleshooting Common Issues

### Database Connection Issues
```php
// Error: "Connection failed"
// Solutions:
1. Check database credentials in config/database.php
2. Verify database server is running
3. Check firewall settings
4. Test with mysql command line
```

### File Upload Issues
```php
// Error: "File upload failed"
// Solutions:
1. Check upload directory permissions (755)
2. Verify PHP upload_max_filesize setting
3. Check disk space
4. Ensure proper file types are allowed
```

### Email Not Sending
```php
// Error: "Email failed to send"
// Solutions:
1. Verify SMTP credentials
2. Check firewall blocking SMTP ports
3. Test with external SMTP service
4. Enable debug mode for error details
```

### Performance Issues
```php
// Error: "Slow page loading"
// Solutions:
1. Enable database query caching
2. Optimize large images
3. Implement CDN for static assets
4. Add database indexes
```

### SSL Certificate Issues
```bash
# Error: "SSL certificate not working"
# Solutions:
1. Check certificate expiration
2. Verify domain points to correct IP
3. Test SSL configuration: https://www.ssllabs.com/ssltest/
4. Restart web server after changes
```

---

## 📊 Monitoring & Analytics

### Basic Monitoring Setup
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs -y

# Set up log rotation
sudo nano /etc/logrotate.d/shms
```

### Google Analytics Integration
```html
<!-- Add to includes/header.php -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'GA_MEASUREMENT_ID');
</script>
```

### Error Monitoring
```php
// Add to config/database.php
function logError($message) {
    $log_file = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
```

---

## 🌍 Domain & DNS Configuration

### Domain Setup
1. Purchase domain from registrar (Namecheap, GoDaddy, etc.)
2. Point nameservers to hosting provider
3. Wait for DNS propagation (24-48 hours)

### DNS Records
```
# A Record (IPv4)
@    IN    A    192.168.1.1
www  IN    A    192.168.1.1

# AAAA Record (IPv6) - Optional
@    IN    AAAA    2001:db8::1

# MX Record (Email)
@    IN    MX    10    mail.your-domain.com

# TXT Record (SPF/DKIM)
@    IN    TXT    "v=spf1 include:_spf.google.com ~all"
```

### Subdomain Setup
```
# API subdomain
api  IN    CNAME    your-domain.com

# CDN subdomain
cdn  IN    CNAME    cdn-provider.com

# Admin subdomain
admin IN    CNAME    your-domain.com
```

---

## 🎯 Scaling Considerations

### When to Scale
- Page load time > 3 seconds
- Database queries > 100ms
- Concurrent users > 100
- Disk usage > 80%

### Scaling Options
```bash
# Horizontal Scaling
1. Load balancer setup
2. Multiple web servers
3. Database replication
4. CDN implementation

# Vertical Scaling
1. Upgrade server resources
2. Optimize database
3. Implement caching
4. Code optimization
```

### Cloud Migration
```bash
# AWS Migration
1. Create AWS account
2. Set up RDS database
3. Deploy to EC2
4. Configure load balancer
5. Migrate data

# Google Cloud Migration
1. Create GCP project
2. Set up Cloud SQL
3. Deploy to Compute Engine
4. Configure load balancing
5. Migrate data
```

---

## 📞 Support Resources

### Documentation
- [User Manual](docs/user-manual.md)
- [Admin Guide](docs/admin-guide.md)
- [API Documentation](docs/api-documentation.md)

### Community Support
- [GitHub Issues](https://github.com/your-repo/shms/issues)
- [Discord Community](https://discord.gg/shms)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/shms)

### Professional Support
- Email: support@shms.com
- Phone: +1-234-567-8900
- Response time: 24-48 hours

---

## ✅ Final Deployment Checklist

### Pre-Launch
- [ ] All features tested
- [ ] Security audit completed
- [ ] Performance optimized
- [ ] Backup system configured
- [ ] Monitoring enabled

### Launch Day
- [ ] DNS configured
- [ ] SSL certificate installed
- [ ] Database imported
- [ ] Error logging tested
- [ ] User access verified

### Post-Launch
- [ ] Monitor for 24 hours
- [ ] Check error logs
- [ ] Verify all features
- [ ] Collect user feedback
- [ ] Plan improvements

---

**🎉 Congratulations! Your Smart Hospital Management System is now deployed and ready to use!**

For additional support, please refer to our comprehensive documentation or contact our support team.
