# School Management System - Test Suite

This directory contains the comprehensive test suite for the School Management System WordPress plugin. The test suite includes unit tests, integration tests, and end-to-end workflow tests to ensure system reliability and functionality.

## Test Structure

```
tests/
├── bootstrap.php                           # Test bootstrap file
├── run-tests.sh                           # Test runner script
├── README.md                              # This file
├── utilities/                             # Test utilities and helpers
│   ├── class-sms-test-case.php           # Base test case class
│   ├── class-sms-test-factory.php        # Test data factory
│   └── class-sms-test-data.php           # Sample test data provider
├── unit/                                  # Unit tests
│   ├── test-sms-base.php                 # Base class tests
│   ├── test-sms-student-manager.php      # Student management tests
│   └── test-sms-payment-gateway-manager.php # Payment gateway tests
└── integration/                          # Integration tests
    ├── test-sms-custom-post-types.php    # WordPress CPT integration
    ├── test-sms-taxonomies.php           # WordPress taxonomy integration
    ├── test-sms-payment-gateways.php     # Payment gateway integration
    └── test-sms-end-to-end-workflows.php # Complete workflow tests
```

## Prerequisites

### System Requirements
- PHP 8.0 or higher
- MySQL 8.0+ or MariaDB 10.5+
- WordPress 6.0+
- Composer

### Required Tools
- PHPUnit 9.5+
- WordPress Test Suite
- MySQL/MariaDB server

## Installation

### 1. Install Dependencies

```bash
cd school_management_system
composer install --dev
```

### 2. Set up WordPress Test Environment

```bash
# Install WordPress test suite
bash bin/install-wp-tests.sh sms_test root '' localhost latest

# Or with custom database credentials
bash bin/install-wp-tests.sh sms_test db_user db_pass db_host wp_version
```

### 3. Configure Test Database

Ensure your MySQL/MariaDB server is running and accessible with the credentials provided in the installation step.

## Running Tests

### Quick Start

```bash
# Run all tests
./tests/run-tests.sh

# Run specific test suites
./tests/run-tests.sh unit           # Unit tests only
./tests/run-tests.sh integration    # Integration tests only
./tests/run-tests.sh payment        # Payment gateway tests
./tests/run-tests.sh workflow       # End-to-end workflow tests
```

### Advanced Usage

```bash
# Run tests with coverage report
./tests/run-tests.sh coverage

# Run tests with code quality checks
./tests/run-tests.sh all quality

# Run specific test file
vendor/bin/phpunit tests/unit/test-sms-student-manager.php

# Run tests with verbose output
vendor/bin/phpunit --verbose

# Run tests with specific filter
vendor/bin/phpunit --filter test_student_creation
```

## Test Categories

### Unit Tests

Unit tests focus on testing individual classes and methods in isolation. They use mocks and stubs to avoid dependencies on WordPress core, database, or external services.

**Coverage:**
- `SMS_Base` class functionality
- `SMS_Student_Manager` business logic
- `SMS_Payment_Gateway_Manager` core functionality
- Input validation and sanitization
- Error handling and logging
- Data formatting and utilities

**Example:**
```php
public function test_student_creation_with_valid_data() {
    $student_data = $this->test_data->get_sample_student_data();
    $result = $this->student_manager->create_student($student_data);
    
    $this->assertNotWPError($result);
    $this->assertIsInt($result);
}
```

### Integration Tests

Integration tests verify that different components work together correctly within the WordPress environment. They test actual database operations, WordPress hooks, and plugin interactions.

**Coverage:**
- Custom Post Types registration and functionality
- Custom Taxonomies and term management
- WordPress hooks and filters
- Database operations and queries
- User roles and capabilities
- Meta data handling

**Example:**
```php
public function test_student_post_creation() {
    $student_id = $this->factory->create_student();
    $this->assertPostExists($student_id, 'sms_students');
    
    $full_name = get_post_meta($student_id, 'full_name', true);
    $this->assertEquals('John Doe', $full_name);
}
```

### Payment Gateway Integration Tests

Specialized tests for payment gateway functionality, including sandbox testing and error handling.

**Coverage:**
- M-Pesa STK Push integration
- Airtel Money payment processing
- Payment verification and callbacks
- Gateway fallback mechanisms
- Transaction recording and receipt generation
- Error handling for various payment scenarios

**Example:**
```php
public function test_mpesa_stk_push_integration() {
    $result = $this->gateway_manager->process_payment(
        'mpesa', 1000, '+254712345678', 'TEST_REF_123'
    );
    
    $this->assertNotWPError($result);
    $this->assertEquals('pending', $result['status']);
}
```

### End-to-End Workflow Tests

Comprehensive tests that simulate complete user workflows from start to finish.

**Coverage:**
- Student admission to graduation workflow
- Financial processes from fee setup to payment collection
- Communication workflows (SMS, email notifications)
- Transport management workflows
- Academic reporting workflows
- Error handling across workflows

**Example:**
```php
public function test_complete_student_lifecycle_workflow() {
    // 1. Student application
    $student_id = $this->student_manager->create_student($student_data);
    
    // 2. Admission approval
    $this->student_manager->approve_admission($student_id);
    
    // 3. Class enrollment
    $this->student_manager->enroll_student($student_id, $class_id);
    
    // 4. Academic progress tracking
    // 5. Graduation
    // ... complete workflow verification
}
```

## Test Data and Fixtures

### Test Factory

The `SMS_Test_Factory` class provides methods to create test data:

```php
// Create test students
$students = $this->factory->create_students(5);

// Create test class
$class_id = $this->factory->create_class([
    'meta_input' => ['capacity' => 30]
]);

// Create test invoice
$invoice_id = $this->factory->create_invoice([
    'meta_input' => ['total_amount' => 5000]
]);
```

### Sample Data Provider

The `SMS_Test_Data` class provides realistic sample data:

```php
// Get sample student data
$student_data = $this->test_data->get_sample_student_data();

// Get sample payment gateway responses
$responses = $this->test_data->get_sample_gateway_responses();

// Get sample SMS templates
$sms_data = $this->test_data->get_sample_sms_data();
```

## Test Configuration

### Environment Variables

Set these environment variables for test configuration:

```bash
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
export WP_CORE_DIR="/tmp/wordpress"
export DB_NAME="sms_test"
export DB_USER="root"
export DB_PASSWORD=""
export DB_HOST="localhost"
```

### PHPUnit Configuration

The `phpunit.xml` file contains test suite configuration:

```xml
<testsuites>
    <testsuite name="unit">
        <directory>tests/unit</directory>
    </testsuite>
    <testsuite name="integration">
        <directory>tests/integration</directory>
    </testsuite>
</testsuites>
```

## Mocking and Test Doubles

### WordPress Functions

WordPress functions are mocked in unit tests:

```php
// Mock current_time function
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date($type === 'mysql' ? 'Y-m-d H:i:s' : $type);
    }
}
```

### External Services

External services like payment gateways and SMS APIs are mocked:

```php
class Mock_MPESA_Gateway extends SMS_Payment_Gateway_Base {
    public function initialize_payment($amount, $phone_number, $reference) {
        return [
            'status' => 'pending',
            'transaction_id' => 'MOCK_' . wp_rand(100000, 999999)
        ];
    }
}
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: sms_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        extensions: mysqli, zip
    
    - name: Install dependencies
      run: composer install --dev
    
    - name: Install WordPress test suite
      run: bash bin/install-wp-tests.sh sms_test root root 127.0.0.1 latest
    
    - name: Run tests
      run: ./tests/run-tests.sh
```

## Test Coverage

### Generating Coverage Reports

```bash
# Generate HTML coverage report
./tests/run-tests.sh coverage

# View coverage report
open tests/coverage/index.html
```

### Coverage Targets

- **Unit Tests**: 90%+ code coverage
- **Integration Tests**: 80%+ feature coverage
- **End-to-End Tests**: 100% critical workflow coverage

## Debugging Tests

### Verbose Output

```bash
vendor/bin/phpunit --verbose --debug
```

### Specific Test Debugging

```php
public function test_debug_example() {
    $result = $this->some_method();
    
    // Debug output
    error_log('Debug: ' . print_r($result, true));
    
    // Assertions
    $this->assertTrue($result);
}
```

### Database Inspection

```php
public function test_with_database_inspection() {
    global $wpdb;
    
    // Your test code
    $student_id = $this->factory->create_student();
    
    // Inspect database
    $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = 'sms_students'");
    error_log('Posts: ' . print_r($posts, true));
}
```

## Best Practices

### Test Organization

1. **One test class per source class**
2. **Descriptive test method names**
3. **Arrange-Act-Assert pattern**
4. **Independent tests** (no dependencies between tests)
5. **Clean up after tests**

### Test Data

1. **Use factories for consistent test data**
2. **Avoid hardcoded values**
3. **Test edge cases and error conditions**
4. **Use realistic sample data**

### Assertions

1. **Use specific assertions** (`assertEquals` vs `assertTrue`)
2. **Test both positive and negative cases**
3. **Verify error conditions**
4. **Check side effects**

### Performance

1. **Keep tests fast** (< 1 second per test)
2. **Use database transactions** when possible
3. **Mock external services**
4. **Minimize WordPress setup overhead**

## Troubleshooting

### Common Issues

**Database Connection Errors**
```bash
# Check MySQL service
sudo service mysql status

# Verify database credentials
mysql -u root -p -e "SHOW DATABASES;"
```

**WordPress Test Suite Issues**
```bash
# Reinstall WordPress test suite
rm -rf /tmp/wordpress-tests-lib
bash bin/install-wp-tests.sh sms_test root '' localhost latest
```

**Memory Limit Issues**
```bash
# Increase PHP memory limit
php -d memory_limit=512M vendor/bin/phpunit
```

**Permission Issues**
```bash
# Fix file permissions
chmod +x bin/install-wp-tests.sh
chmod +x tests/run-tests.sh
```

### Getting Help

1. Check the test output for specific error messages
2. Review the test logs in `tests/logs/`
3. Verify all dependencies are installed correctly
4. Ensure database credentials are correct
5. Check WordPress and PHP version compatibility

## Contributing

When adding new tests:

1. Follow the existing test structure and naming conventions
2. Add appropriate documentation and comments
3. Ensure tests are independent and can run in any order
4. Include both positive and negative test cases
5. Update this README if adding new test categories or utilities

## License

This test suite is part of the School Management System plugin and follows the same GPL v2 or later license.