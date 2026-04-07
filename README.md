# Documantraa - Investigation & Security Services Website

A professional PHP-based website for investigation and security services with an integrated CRM system.

## 🚀 Features

### Public Website
- **Homepage**: Hero section, services showcase, statistics, testimonials, FAQ, contact form
- **About Page**: Company information, mission, vision, team details
- **Services**: Dynamic service listing and individual service detail pages
- **Privacy Policy & Terms of Service**: Dedicated legal pages
- **SEO-Friendly URLs**: Clean URLs with slug-based routing
- **Responsive Design**: Mobile-first, fully responsive layout
- **Modern UI**: Professional design with animations and smooth transitions

### CRM System
- **Multi-Role Authentication**: Admin, HR, Office Staff, Employee, Freelancer
- **Dashboard**: Role-based dashboards with analytics
- **Project Management**: Track projects, field visits, and investigations
- **User Management**: Comprehensive user and employee management
- **Attendance System**: Track employee attendance and work hours
- **Payroll Management**: Handle salaries, advances, deductions
- **Field Visits**: Track field investigations with points and travel allowances
- **Reports**: Generate various reports and analytics
- **Settings**: Customizable site settings, colors, logos, and content

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)

## 🛠️ Installation

### 1. Database Setup
```sql
-- Create database
CREATE DATABASE documantraa;

-- Import the database schema
-- Use the SQL files in the /database directory
```

### 2. Configuration
```php
// Update config/db.php with your database credentials
$host = 'localhost';
$dbname = 'documantraa';
$username = 'your_username';
$password = 'your_password';
```

### 3. File Permissions
```bash
# Set proper permissions for upload directories
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/field_visits/
chmod 755 assets/images/uploads/
chmod 755 assets/images/services/
```

### 4. Apache Configuration
Ensure `.htaccess` is enabled and `mod_rewrite` is active:
```bash
# Enable mod_rewrite
sudo a2enmod rewrite

# Restart Apache
sudo service apache2 restart
```

## 🔐 Default Admin Access

After installation, create an admin user using:
```php
// Run grant_admin_rights.php to create/update admin user
// Default credentials (change immediately):
Email: admin@documantraa.in
Password: (set during first run)
```

## 📁 Project Structure

```
/doc
├── admin/              # Admin panel (if separate)
├── assets/            # CSS, JS, images, fonts
│   ├── css/
│   ├── images/
│   └── favicon/
├── config/            # Database configuration
├── crm/              # CRM system files
├── database/         # Database schemas and migrations
├── docs/             # Documentation
├── includes/         # Reusable PHP components
│   ├── navbar.php
│   ├── footer.php
│   ├── top-bar.php
│   └── functions.php
├── migrations/       # Database migrations
├── tools/           # Utility scripts
├── uploads/         # User uploaded files
├── index.php        # Homepage
├── about.php        # About page
├── services.php     # Services listing
├── service-detail.php  # Individual service page
├── privacy-policy.php  # Privacy policy
├── terms-of-service.php # Terms of service
├── router.php       # Custom router for development
└── .htaccess       # Apache configuration
```

## 🎨 Customization

### Site Settings
Access the CRM admin panel to customize:
- Site name and logo
- Contact information
- Color scheme
- Hero images
- Service content
- Legal pages content

### Adding Services
1. Login to CRM as Admin
2. Navigate to Services section
3. Add new service with title, description, icon, and image
4. Service will automatically appear on the website

## 🔒 Security Features

- ✅ Password hashing with PHP's `password_hash()`
- ✅ SQL injection prevention with PDO prepared statements
- ✅ XSS protection with `htmlspecialchars()`
- ✅ CSRF protection on forms
- ✅ Session security
- ✅ File upload validation
- ✅ Directory listing disabled
- ✅ Sensitive file protection
- ✅ Security headers in .htaccess

## 🚀 Production Deployment

### Pre-Deployment Checklist

1. **Database**
   - [ ] Backup existing database
   - [ ] Update database credentials in `config/db.php`
   - [ ] Run all migrations

2. **Security**
   - [ ] Change default admin password
   - [ ] Enable HTTPS (uncomment in .htaccess)
   - [ ] Update `config/db.php` with production credentials
   - [ ] Remove or secure `grant_admin_rights.php`
   - [ ] Set proper file permissions (755 for directories, 644 for files)

3. **Configuration**
   - [ ] Update site URL in settings
   - [ ] Configure email settings (if using email features)
   - [ ] Test all forms and submissions
   - [ ] Verify all links work correctly

4. **Performance**
   - [ ] Enable GZIP compression (already in .htaccess)
   - [ ] Enable browser caching (already in .htaccess)
   - [ ] Optimize images
   - [ ] Minify CSS/JS if needed

5. **Testing**
   - [ ] Test all public pages
   - [ ] Test CRM login for all roles
   - [ ] Test form submissions
   - [ ] Test file uploads
   - [ ] Check mobile responsiveness
   - [ ] Verify SEO meta tags

## 📱 Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## 🆘 Support

For support, contact:
- Email: support@documantraa.in
- Website: https://documantraa.in

## 📄 License

Proprietary - All rights reserved

## 👨‍💻 Development

Built with:
- PHP 8.2
- MySQL
- Bootstrap 5.3
- Vanilla JavaScript
- AOS (Animate On Scroll)

---

**Last Updated**: December 2024
**Version**: 1.0.0
