# School Management System - Technical Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Installation & Setup](#installation--setup)
4. [Configuration](#configuration)
5. [Database Schema](#database-schema)
6. [API Reference](#api-reference)
7. [Security](#security)
8. [Performance](#performance)
9. [Troubleshooting](#troubleshooting)
10. [Development Guidelines](#development-guidelines)

## System Overview

The School Management System is a comprehensive WordPress-based platform designed to streamline administrative tasks, enhance communication between stakeholders, and provide robust financial and academic management capabilities.

### Key Features

- **Student Management**: Complete student lifecycle from admission to graduation
- **Academic Management**: Classes, timetables, attendance tracking
- **Financial Management**: Fee structures, invoicing, multi-gateway payments
- **Communication System**: SMS integration, notices, automated notifications
- **Transport Management**: Route management and student assignments
- **Reporting & Analytics**: Comprehensive reports and data visualization
- **User Role Management**: Role-based access control for different user types

### Technology Stack

- **Platform**: WordPress 6.0+
- **PHP Version**: 8.0+
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Required Plugins**: Advanced Custom Fields Pro, User Role Editor
- **Third-party Integrations**: Africastalking SMS API, M-Pesa, Airtel Money

## Architecture

### Plugin Structure

```
school-management-system/
├── school-management-system.php    # Main plugin file
├── includes/                       # Core functionality
│   ├── core/                      # Plugin foundation classes
│   ├── admin/                     # Admin interface components
│   ├── public/                    # Frontend components
│   ├── post-types/                # Custom post type definitions
│   ├── taxonomies/                # Custom taxonomy definitions
│   ├── user-roles/                # User role management
│   ├── api/                       # REST API endpoints
│   ├── integrations/              # Third-party service integrations
│   └── financial/                 # Payment gateway implementations
├── admin/                         # Admin assets (CSS, JS, images)
├── public/                        # Public assets
├── languages/                     # Translation files
└── tests/                         # Unit and integration tests
```

### Core Components

#### 1. Custom Post Types (CPTs)

- **cpt_students**: Student records with admission numbers, personal details
- **cpt_classes**: Class management with teacher assignments, capacity limits
- **cpt_fees**: Fee structures with installment options and penalties
- **cpt_invoices**: Generated invoices with payment tracking
- **cpt_transactions**: Payment records with gateway-specific data
- **cpt_attendance**: Daily attendance records
- **cpt_timetables**: Class schedules with conflict detection
- **cpt_notices**: School announcements with targeting
- **cpt_transport_routes**: Transport routes with stops and capacity

#### 2. Custom Taxonomies

- **tax_subjects**: Academic subjects (Mathematics, English, Science, etc.)
- **tax_grades**: Grade levels (Grade 1, Grade 2, etc.)
- **tax_academic_years**: Academic years (2024-2025, 2025-2026)
- **tax_terms**: Academic terms (Term 1, Term 2, Term 3)

#### 3. User Roles

- **sms_admin**: School administrators with full system access
- **sms_teacher**: Teachers with academic management capabilities
- **sms_parent**: Parents with child-specific data access
- **sms_student**: Students with limited personal data access

## Installation & Setup

### Prerequisites

1. WordPress 6.0 or higher
2. PHP 8.0 or higher
3. MySQL 8.0+ or MariaDB 10.5+
4. Advanced Custom Fields Pro plugin
5. User Role Editor plugin

### Installation Steps

1. **Upload Plugin Files**
   ```bash
   # Upload the plugin directory to wp-content/plugins/
   wp plugin install school-management-system.zip
   ```

2. **Activate Required Plugins**
   ```bash
   wp plugin activate advanced-custom-fields-pro
   wp plugin activate user-role-editor
   wp plugin activate school-management-system
   ```

3. **Run Initial Setup**
   - Navigate to SMS Settings in WordPress admin
   - Complete the setup wizard
   - Configure school information and academic year

4. **Import ACF Field Groups**
   ```bash
   wp acf sync
   ```

5. **Set Up Permalinks**
   ```bash
   wp rewrite flush
   ```

### Database Setup

The plugin automatically creates necessary database tables and custom fields during activation. No manual database setup is required.

## Configuration

### General Settings

Access SMS Settings from the WordPress admin menu to configure:

#### School Information
- School name and address
- Contact information
- Academic year settings
- Term configuration

#### Payment Gateway Configuration

##### M-Pesa Setup
```php
// M-Pesa configuration options
$mpesa_config = [
    'environment' => 'sandbox', // or 'production'
    'consumer_key' => 'your_consumer_key',
    'consumer_secret' => 'your_consumer_secret',
    'shortcode' => 'your_shortcode',
    'passkey' => 'your_passkey',
    'callback_url' => site_url('/wp-json/sms/v1/mpesa/callback')
];
```

##### Airtel Money Setup
```php
// Airtel Money configuration
$airtel_config = [
    'environment' => 'sandbox',
    'client_id' => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'merchant_id' => 'your_merchant_id',
    'callback_url' => site_url('/wp-json/sms/v1/airtel/callback')
];
```

#### SMS Configuration (Africastalking)
```php
// Africastalking SMS configuration
$sms_config = [
    'username' => 'your_username',
    'api_key' => 'your_api_key',
    'sender_id' => 'your_sender_id',
    'environment' => 'sandbox' // or 'production'
];
```

### User Role Configuration

The system creates custom user roles with specific capabilities:

```php
// Administrator capabilities
$admin_capabilities = [
    'manage_students',
    'manage_classes',
    'manage_fees',
    'manage_financial_reports',
    'send_bulk_sms',
    'manage_system_settings',
    'manage_transport',
    'manage_notices'
];

// Teacher capabilities
$teacher_capabilities = [
    'edit_assigned_classes',
    'mark_attendance',
    'create_lessons',
    'view_student_records',
    'create_academic_notices',
    'manage_timetables'
];

// Parent capabilities
$parent_capabilities = [
    'view_child_records',
    'view_child_fees',
    'make_payments',
    'update_contact_info',
    'view_notices'
];
```

## Database Schema

### Custom Post Types Schema

#### Students Table (wp_posts + meta)
```sql
-- Core student data stored in wp_postmeta
meta_key                | meta_value
-----------------------|------------------
admission_number       | ADM2024001
full_name             | John Doe
date_of_birth         | 2010-01-15
grade_level           | grade-5
parent_email          | parent@email.com
parent_phone          | +254712345678
medical_info          | No known allergies
address               | 123 Main Street
current_class         | 45 (class post ID)
enrollment_status     | active
```

#### Classes Table (wp_posts + meta)
```sql
meta_key              | meta_value
---------------------|------------------
class_name           | Grade 5A
grade_level          | grade-5
capacity             | 30
teacher_id           | 12 (user ID)
academic_year        | 2024-2025
subject_assignments  | {"math": 12, "english": 15}
```

#### Transactions Table (wp_posts + meta)
```sql
meta_key                    | meta_value
---------------------------|------------------
invoice_id                 | 123 (invoice post ID)
student_id                 | 456 (student post ID)
amount                     | 5000.00
payment_method             | mpesa
gateway_transaction_id     | LHG31AA5TX
gateway_reference          | Receipt123
payment_status             | completed
gateway_response           | {"ResultCode": 0, "ResultDesc": "Success"}
payment_date               | 2024-01-15 10:30:00
```

### Custom Tables

#### Activity Log Table
```sql
CREATE TABLE wp_sms_activity_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    action varchar(50) NOT NULL,
    object_type varchar(50) NOT NULL,
    object_id bigint(20) NOT NULL,
    details text,
    ip_address varchar(45),
    timestamp datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY timestamp (timestamp)
);
```

## API Reference

### REST API Endpoints

The system provides REST API endpoints for external integrations:

#### Authentication
All API requests require authentication using WordPress REST API authentication methods.

#### Students Endpoint
```http
GET /wp-json/sms/v1/students
POST /wp-json/sms/v1/students
GET /wp-json/sms/v1/students/{id}
PUT /wp-json/sms/v1/students/{id}
DELETE /wp-json/sms/v1/students/{id}
```

#### Classes Endpoint
```http
GET /wp-json/sms/v1/classes
POST /wp-json/sms/v1/classes
GET /wp-json/sms/v1/classes/{id}
PUT /wp-json/sms/v1/classes/{id}
```

#### Attendance Endpoint
```http
GET /wp-json/sms/v1/attendance
POST /wp-json/sms/v1/attendance
GET /wp-json/sms/v1/attendance/{class_id}/{date}
```

#### Payment Endpoints
```http
POST /wp-json/sms/v1/payments/initiate
GET /wp-json/sms/v1/payments/{transaction_id}/status
POST /wp-json/sms/v1/mpesa/callback
POST /wp-json/sms/v1/airtel/callback
```

### Webhook Endpoints

#### M-Pesa Callback
```http
POST /wp-json/sms/v1/mpesa/callback
Content-Type: application/json

{
    "Body": {
        "stkCallback": {
            "MerchantRequestID": "29115-34620561-1",
            "CheckoutRequestID": "ws_CO_191220191020363925",
            "ResultCode": 0,
            "ResultDesc": "The service request is processed successfully.",
            "CallbackMetadata": {
                "Item": [
                    {
                        "Name": "Amount",
                        "Value": 1.00
                    },
                    {
                        "Name": "MpesaReceiptNumber",
                        "Value": "NLJ7RT61SV"
                    }
                ]
            }
        }
    }
}
```

## Security

### Data Protection

1. **Input Sanitization**: All user inputs are sanitized using WordPress functions
2. **SQL Injection Prevention**: Using WordPress $wpdb prepared statements
3. **XSS Prevention**: Output escaping with WordPress functions
4. **CSRF Protection**: WordPress nonces for form submissions
5. **File Upload Security**: Restricted file types and validation

### Authentication & Authorization

1. **Role-Based Access Control**: Custom capabilities for each user role
2. **Session Management**: WordPress native session handling
3. **Password Security**: WordPress password hashing and strength requirements
4. **API Authentication**: WordPress REST API authentication

### Payment Security

1. **Credential Encryption**: API keys encrypted using WordPress functions
2. **Callback Validation**: Gateway-specific signature verification
3. **Transaction Logging**: Comprehensive audit trail for all transactions
4. **PCI Compliance**: No sensitive payment data stored locally

### Security Best Practices

```php
// Input sanitization example
$student_name = sanitize_text_field($_POST['student_name']);
$parent_email = sanitize_email($_POST['parent_email']);

// Output escaping example
echo esc_html($student_name);
echo esc_url($callback_url);

// Nonce verification example
if (!wp_verify_nonce($_POST['nonce'], 'sms_create_student')) {
    wp_die('Security check failed');
}

// Capability checking example
if (!current_user_can('manage_students')) {
    wp_die('Insufficient permissions');
}
```

## Performance

### Optimization Strategies

1. **Database Indexing**: Proper indexes on frequently queried fields
2. **Query Optimization**: Efficient WordPress queries with proper caching
3. **Object Caching**: WordPress object cache for frequently accessed data
4. **Image Optimization**: Compressed images and lazy loading
5. **Minification**: CSS and JavaScript minification

### Caching Implementation

```php
// Object caching example
function get_student_data($student_id) {
    $cache_key = 'sms_student_' . $student_id;
    $student_data = wp_cache_get($cache_key);
    
    if (false === $student_data) {
        $student_data = get_post_meta($student_id);
        wp_cache_set($cache_key, $student_data, '', 3600); // Cache for 1 hour
    }
    
    return $student_data;
}
```

### Performance Monitoring

- Page load time targets: < 3 seconds
- Database query limits: < 50 queries per page
- Memory usage limits: < 256MB per request
- API response time: < 2 seconds

## Troubleshooting

### Common Issues

#### 1. Plugin Activation Errors
```
Error: The plugin does not have a valid header.
```
**Solution**: Ensure the main plugin file has proper WordPress headers.

#### 2. Database Connection Issues
```
Error establishing a database connection
```
**Solution**: Check database credentials in wp-config.php.

#### 3. Payment Gateway Errors
```
M-Pesa: Invalid credentials
```
**Solution**: Verify API credentials and environment settings.

#### 4. SMS Delivery Issues
```
Africastalking: Insufficient balance
```
**Solution**: Check SMS credit balance and top up if necessary.

### Debug Mode

Enable WordPress debug mode for troubleshooting:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Log Files

System logs are stored in:
- WordPress debug log: `/wp-content/debug.log`
- SMS activity log: Database table `wp_sms_activity_log`
- Payment logs: Database table `wp_sms_payment_log`

## Development Guidelines

### Coding Standards

Follow WordPress PHP Coding Standards:

```php
// Class naming convention
class SMS_Student_Manager {
    
    // Method naming convention
    public function create_student($student_data) {
        // Implementation
    }
    
    // Hook naming convention
    do_action('sms_student_created', $student_id);
    apply_filters('sms_student_data', $student_data);
}
```

### Testing

#### Unit Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Run specific test class
vendor/bin/phpunit tests/unit/test-sms-student-manager.php
```

#### Integration Testing
```bash
# Run integration tests
vendor/bin/phpunit tests/integration/
```

### Version Control

Use semantic versioning (MAJOR.MINOR.PATCH):
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes (backward compatible)

### Deployment

#### Staging Deployment
1. Deploy to staging environment
2. Run automated tests
3. Perform manual testing
4. Update documentation

#### Production Deployment
1. Create database backup
2. Deploy plugin files
3. Run database migrations
4. Clear caches
5. Monitor for issues

### Contributing

1. Fork the repository
2. Create feature branch
3. Follow coding standards
4. Write tests for new features
5. Submit pull request
6. Code review process
7. Merge to main branch

## Support

For technical support and bug reports:
- Email: support@schoolmanagementsystem.com
- Documentation: https://docs.schoolmanagementsystem.com
- GitHub Issues: https://github.com/school-management-system/issues

## License

This software is licensed under the GPL v2 or later.