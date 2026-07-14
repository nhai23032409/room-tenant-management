# 🚀 Quick Installation Guide

## Prerequisites

- **PHP 7.4+** with PDO extension
- **MySQL 5.7+** or MariaDB
- **Apache** or **Nginx** web server
- **Web browser** with JavaScript enabled

## Installation Steps

### 1. Download & Extract
```bash
# Download the application files
# Extract to your web server directory (e.g., /var/www/html/, htdocs/, etc.)
```

### 2. Set Permissions
```bash
# Set proper permissions for uploads directory
chmod 755 uploads/
chown www-data:www-data uploads/  # On Ubuntu/Debian
```

### 3. Run Setup
1. Open your web browser
2. Navigate to your application URL (e.g., `http://localhost/tenant-management/`)
3. The setup wizard will automatically start
4. Follow the 6-step setup process:
   - **Step 1**: Test database connection
   - **Step 2**: Create database
   - **Step 3**: Install tables
   - **Step 4**: Generate configuration
   - **Step 5**: Create admin account
   - **Step 6**: Complete setup

### 4. Access the Application
- **Main App**: `http://yourdomain.com/mobile_app.php`
- **Room Management**: `http://yourdomain.com/room_management.php`
- **Setup (if needed)**: `http://yourdomain.com/setup.php`

## Default Login Credentials

After setup, you can use your custom admin credentials or the default ones:
- **Email**: admin@tenantmanagement.com
- **Password**: admin123

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Check MySQL service is running
- Verify database credentials
- Ensure PHP PDO extension is installed

**Permission Denied**
- Check file permissions: `chmod 755` for directories, `chmod 644` for files
- Ensure web server has write access to uploads directory

**Setup Already Completed**
- Delete `setup_complete.lock` file to re-run setup
- Or access the app directly at `mobile_app.php`

### Quick Fix Commands
```bash
# Fix permissions
chmod -R 755 .
chmod -R 644 *.php
chmod 755 uploads/

# Check PHP extensions
php -m | grep -i pdo
php -m | grep -i mysql

# Restart web server
sudo systemctl restart apache2  # Ubuntu/Debian
sudo systemctl restart nginx    # If using Nginx
```

## Production Deployment

Before deploying to an existing database, run `database/production_fixes.sql` once. New installations only need `database/tenant_management.sql`.

1. **Update .htaccess**: Change domain in hotlinking protection
2. **Set strong passwords**: Change default admin credentials
3. **Configure SSL**: Enable HTTPS for secure connections
4. **Database security**: Use dedicated database user with limited privileges
5. **Regular backups**: Set up automated database backups

## Support

- **Email**: jerrykoroth@gmail.com
- **Documentation**: README.md
- **GitHub Issues**: Create an issue for bug reports

---

**🎉 Your tenant management system is ready to use!**
