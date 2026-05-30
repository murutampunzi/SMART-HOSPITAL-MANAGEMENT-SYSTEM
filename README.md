# Smart Hospital Management System (SHMS)

A comprehensive, advanced, and fully functional hospital management system built with modern web technologies. This system is designed to streamline hospital operations, improve patient care, and enhance administrative efficiency.

## 🏥 Features

### Core Features
- **Multi-Role Authentication System** - Admin, Doctor, Nurse, Receptionist, Pharmacist, Lab Technician, Patient
- **Patient Management** - Complete patient records, medical history, documents
- **Doctor Management** - Profiles, specializations, schedules, patient assignments
- **Appointment System** - Online booking, real-time availability, calendar view
- **Pharmacy Management** - Medicine inventory, stock tracking, prescription handling
- **Laboratory System** - Test requests, result management, status tracking
- **Billing System** - Auto invoice generation, payment tracking, PDF receipts
- **Admin Dashboard** - Analytics, charts, system monitoring
- **Notification System** - Real-time alerts and reminders
- **Messaging System** - Internal communication between staff and patients
- **Medical Records** - Comprehensive patient medical history tracking

### Technical Features
- **Secure Authentication** - JWT/Session-based, password hashing, CSRF protection
- **Role-Based Access Control** (RBAC) - Granular permissions per user role
- **Real-time Updates** - WebSocket support for live notifications
- **Responsive Design** - Mobile, tablet, desktop compatible
- **Dark Mode Support** - User preference theming
- **Advanced Search** - Patient, doctor, appointment search with filters
- **Export Functionality** - PDF, Excel exports for reports
- **Activity Logging** - Comprehensive audit trail
- **File Upload** - Secure document management
- **Data Validation** - Input sanitization and validation

## 🛠 Technology Stack

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with animations
- **JavaScript (ES6+)** - Modern JavaScript features
- **Bootstrap 5** - Responsive UI framework
- **Font Awesome 6** - Icon library
- **Chart.js** - Data visualization
- **jQuery** - DOM manipulation

### Backend
- **PHP 8+** - Server-side logic
- **MySQL** - Database management
- **MySQLi** - Database interface
- **JWT/Session** - Authentication

### Security Features
- **SQL Injection Protection** - Prepared statements
- **XSS Protection** - Input sanitization
- **CSRF Protection** - Token validation
- **Password Hashing** - Bcrypt algorithm
- **Secure Headers** - Security headers implementation
- **Input Validation** - Comprehensive validation

## 📁 Project Structure

```
SMART/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── header.php            # Header template
│   ├── footer.php            # Footer template
│   └── functions.php         # Helper functions
├── assets/
│   ├── css/
│   │   └── style.css         # Custom styles
│   ├── js/
│   │   └── app.js            # Application JavaScript
│   └── images/               # Image assets
├── auth/
│   ├── login.php             # User login
│   ├── register.php          # User registration
│   └── logout.php            # User logout
├── patients/
│   ├── index.php             # Patient listing
│   ├── create.php            # Add patient
│   ├── view.php              # View patient details
│   └── edit.php              # Edit patient
├── doctors/
│   ├── index.php             # Doctor listing
│   ├── create.php            # Add doctor
│   └── edit.php              # Edit doctor
├── appointments/
│   ├── index.php             # Appointment listing
│   ├── create.php            # Create appointment
│   ├── book.php              # Patient booking
│   └── view.php              # View appointment
├── pharmacy/
│   ├── index.php             # Pharmacy management
│   ├── medicines.php         # Medicine inventory
│   └── prescriptions.php     # Prescription management
├── laboratory/
│   ├── index.php             # Lab management
│   ├── tests.php             # Lab tests
│   └── results.php           # Lab results
├── billing/
│   ├── index.php             # Billing overview
│   ├── create.php            # Create invoice
│   └── payments.php          # Payment management
├── messages/
│   ├── index.php             # Message center
│   ├── compose.php           # Compose message
│   └── view.php              # View message
├── database/
│   └── database.sql          # Database schema
├── uploads/
│   ├── patients/             # Patient documents
│   ├── doctors/              # Doctor documents
│   └── lab_results/          # Lab result files
├── api/
│   ├── notifications.php     # API endpoints
│   ├── search.php            # Search API
│   └── user.php              # User API
├── logs/                     # Application logs
├── index.php                 # Landing page
├── dashboard.php             # Main dashboard
└── README.md                 # This file
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional, for dependencies)

### Step 1: Database Setup
1. Create a MySQL database named `smart_hospital`
2. Import the database schema:
   ```bash
   mysql -u username -p smart_hospital < database/database.sql
   ```

### Step 2: Configuration
1. Copy and configure database settings:
   ```php
   // config/database.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'smart_hospital');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```

### Step 3: File Permissions
Set proper permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 logs/
```

### Step 4: Web Server Configuration
#### Apache
Add to `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx
Add to server configuration:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Step 5: Access the Application
1. Open your browser and navigate to `http://localhost/SMART/`
2. Login with default admin credentials:
   - Email: `admin@shms.com`
   - Password: `admin123`

## 👥 User Roles & Permissions

### Administrator (admin)
- Full system access
- User management
- System configuration
- Report generation
- Backup management

### Doctor (doctor)
- Patient management
- Appointment scheduling
- Medical records
- Prescriptions
- Lab test requests

### Nurse (nurse)
- Patient care management
- Appointment assistance
- Medical record updates
- Vital signs tracking

### Receptionist (receptionist)
- Patient registration
- Appointment booking
- Billing management
- Front desk operations

### Pharmacist (pharmacist)
- Medicine inventory
- Prescription fulfillment
- Stock management
- Supplier management

### Lab Technician (lab_technician)
- Lab test processing
- Result entry
- Sample management
- Quality control

### Patient (patient)
- Appointment booking
- View medical records
- Lab results access
- Messaging with doctors

## 🔧 Configuration Options

### System Settings (config/database.php)
```php
// Security
define('SECURE', true);                    // Enable HTTPS
define('HASH_COST', 12);                   // Password hashing cost

// File Upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'jpg', 'png']);

// Application
define('ITEMS_PER_PAGE', 10);              // Pagination
define('SESSION_TIMEOUT', 30);             // Minutes
```

### Email Configuration
```php
// For production, configure SMTP settings
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'user@example.com');
define('SMTP_PASSWORD', 'password');
```

## 🌐 Deployment Options

### Option 1: Traditional Hosting (PHP + MySQL)
- **InfinityFree**, **000webhost**, **Hostinger**
- Upload files to public directory
- Configure database credentials
- Set proper file permissions

### Option 2: Serverless (Firebase + Static Hosting)
- **Netlify**, **Vercel**, **GitHub Pages** for frontend
- **Firebase Firestore** for database
- **Firebase Functions** for backend logic

### Option 3: Cloud Platform
- **AWS EC2** with LAMP stack
- **Google Cloud Platform** with Compute Engine
- **DigitalOcean** with App Platform

## 📊 Database Schema

### Core Tables
- **users** - Authentication and user management
- **patients** - Patient information and records
- **doctors** - Doctor profiles and specializations
- **appointments** - Appointment scheduling
- **prescriptions** - Medical prescriptions
- **medicines** - Pharmacy inventory
- **lab_tests** - Laboratory test definitions
- **lab_results** - Test results and reports
- **invoices** - Billing and payments
- **notifications** - System notifications
- **messages** - Internal messaging
- **activity_logs** - Audit trail

### Relationships
- Users → Patients (1:1)
- Users → Doctors (1:1)
- Patients → Appointments (1:N)
- Doctors → Appointments (1:N)
- Appointments → Prescriptions (1:N)
- Patients → Medical Records (1:N)

## 🔒 Security Features

### Authentication
- Password hashing with Bcrypt
- Session management with secure cookies
- JWT token support for API
- Remember me functionality
- Password reset capabilities

### Protection
- SQL injection prevention
- XSS attack prevention
- CSRF token validation
- Input sanitization
- File upload security
- Rate limiting support

### Privacy
- Data encryption options
- GDPR compliance features
- Audit logging
- Access control
- Data retention policies

## 📱 Mobile Compatibility

The system is fully responsive and works on:
- **Desktop** (1024px+)
- **Tablet** (768px-1023px)
- **Mobile** (320px-767px)

### Mobile Features
- Touch-friendly interface
- Swipe gestures support
- Mobile-optimized forms
- Progressive Web App (PWA) ready

## 🔄 API Endpoints

### Authentication
- `POST /auth/login` - User login
- `POST /auth/register` - User registration
- `POST /auth/logout` - User logout

### Data Management
- `GET /api/patients` - List patients
- `POST /api/patients` - Create patient
- `PUT /api/patients/{id}` - Update patient
- `DELETE /api/patients/{id}` - Delete patient

### Notifications
- `GET /api/notifications` - Get notifications
- `POST /api/notifications` - Create notification
- `PUT /api/notifications/{id}` - Mark as read

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration and login
- [ ] Role-based access control
- [ ] Patient registration and management
- [ ] Appointment booking system
- [ ] Medical record management
- [ ] Lab test requests and results
- [ ] Pharmacy inventory management
- [ ] Billing and payment processing
- [ ] Notification system
- [ ] File upload functionality
- [ ] Search and filtering
- [ ] Export functionality
- [ ] Mobile responsiveness

### Automated Testing
- PHPUnit for backend testing
- JavaScript unit tests
- Cross-browser compatibility
- Performance testing

## 📈 Performance Optimization

### Database Optimization
- Indexed columns for fast queries
- Query optimization
- Connection pooling
- Caching strategies

### Frontend Optimization
- Minified CSS/JS
- Image optimization
- Lazy loading
- CDN integration

### Server Optimization
- Gzip compression
- Browser caching
- Load balancing
- CDN deployment

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Error
```
Error: Connection failed
```
**Solution:** Check database credentials in `config/database.php`

#### File Upload Not Working
```
Error: File upload failed
```
**Solution:** Check folder permissions and PHP upload settings

#### Login Issues
```
Error: Invalid credentials
```
**Solution:** Verify user exists and password is correct

#### Session Issues
```
Error: Session expired
```
**Solution:** Check session timeout settings

### Debug Mode
Enable debug mode in `config/database.php`:
```php
define('SECURE', false);  // Enable error display
```

## 📞 Support

### Documentation
- Online documentation: [docs.shms.com](https://docs.shms.com)
- API documentation: [api.shms.com](https://api.shms.com)

### Community
- GitHub Issues: Report bugs and feature requests
- Forum: [community.shms.com](https://community.shms.com)
- Discord: [SHMS Community](https://discord.gg/shms)

### Professional Support
- Email: support@shms.com
- Phone: +1-234-567-8900
- Business hours: 9 AM - 6 PM EST

## 🔄 Updates & Maintenance

### Version History
- **v1.0.0** - Initial release
- **v1.1.0** - Added mobile app support
- **v1.2.0** - Enhanced security features
- **v2.0.0** - Major UI/UX overhaul

### Update Process
1. Backup current database
2. Download new version
3. Update database schema
4. Test functionality
5. Deploy to production

### Maintenance Schedule
- **Daily**: Database backups
- **Weekly**: Security updates
- **Monthly**: Feature updates
- **Quarterly**: Major releases

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup
1. Fork the repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

### Code Standards
- PSR-12 coding standards
- Comprehensive documentation
- Unit test coverage
- Security review required

## 🏆 Acknowledgments

- Bootstrap Team - UI framework
- Chart.js - Data visualization
- Font Awesome - Icon library
- PHP Community - Language and ecosystem
- MySQL Team - Database system

---

**Smart Hospital Management System** - Transforming healthcare management through technology.

© 2024 Smart Hospital Management System. All rights reserved.
