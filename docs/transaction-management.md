# Transaction Management System

The School Management System includes a comprehensive transaction management system that handles payment processing, status tracking, and receipt generation.

## Components

### 1. SMS_Transaction_Manager
The core transaction management class that handles:
- Creating transaction records
- Updating transaction statuses
- Recording gateway-specific data
- Generating and sending receipts
- Verifying transactions with payment gateways

### 2. SMS_Transaction_Status_Tracker
Handles automatic status updates and monitoring:
- Scheduled status updates for pending transactions
- Gateway verification
- Error handling and retry logic
- Admin notifications for failed transactions

### 3. SMS_Receipt_Generator
Manages receipt generation:
- Multiple receipt templates (default, detailed, simple)
- HTML receipt generation
- PDF generation support (when libraries are available)
- Customizable receipt templates

### 4. SMS_Transaction_Admin
Admin interface for transaction management:
- Transaction list with custom columns
- Individual transaction management
- Bulk operations (verify, send receipts, mark completed)
- Status update interface
- Receipt preview and download

### 5. SMS_Transaction_Integration
Integrates transaction management with other system components:
- Payment gateway integration
- Invoice status updates
- Notification handling
- Bulk operation processing

## Transaction Statuses

- **pending**: Transaction initiated but not yet processed
- **processing**: Transaction being processed by payment gateway
- **completed**: Transaction successfully completed
- **failed**: Transaction failed
- **cancelled**: Transaction cancelled by user or system
- **refunded**: Transaction refunded
- **disputed**: Transaction disputed

## Verification Statuses

- **unverified**: Not yet verified with payment gateway
- **verified**: Successfully verified with payment gateway
- **failed_verification**: Verification failed
- **manual_verification**: Requires manual verification

## Usage Examples

### Creating a Transaction

```php
$transaction_manager = SMS_Transaction_Manager::get_instance();

$transaction_data = array(
    'student_id' => 123,
    'invoice_id' => 456,
    'amount' => 5000.00,
    'currency' => 'KES',
    'payment_method' => 'mpesa',
    'gateway_name' => 'mpesa',
    'phone_number' => '254700000000'
);

$transaction_id = $transaction_manager->create_transaction($transaction_data);
```

### Updating Transaction Status

```php
$transaction_manager->update_transaction_status(
    $transaction_id,
    SMS_Transaction_Manager::STATUS_COMPLETED,
    'Payment verified with M-Pesa'
);
```

### Generating a Receipt

```php
$receipt_content = $transaction_manager->generate_receipt($transaction_id);
```

### Sending a Receipt

```php
$transaction_manager->send_receipt($transaction_id, array('email', 'sms'));
```

## Payment Gateway Integration

The transaction system integrates with payment gateways through events:

```php
// When payment is initiated
do_action('sms_payment_initiated', $payment_data, $gateway_id, $gateway_response);

// When payment is completed
do_action('sms_payment_completed', $payment_data, $gateway_id, $gateway_response);

// When payment fails
do_action('sms_payment_failed', $payment_data, $gateway_id, $gateway_response);
```

## Automatic Processing

The system includes automatic processing features:

1. **Scheduled Status Updates**: Hourly checks of pending transactions
2. **Auto-Receipt Generation**: Automatic receipt generation for completed payments
3. **Notification Sending**: SMS and email notifications for status changes
4. **Invoice Status Updates**: Automatic invoice status updates based on payments

## Admin Interface

The admin interface provides:

1. **Transaction Management Page**: Overview and bulk operations
2. **Individual Transaction Pages**: Detailed transaction management
3. **Receipt Management**: Template and delivery settings
4. **Status Monitoring**: Real-time status tracking and notifications

## Testing

Use the test script to verify system functionality:

```bash
# Access the test script
http://yoursite.com/wp-content/plugins/school-management-system/test-transaction-system.php
```

## Configuration Options

Available WordPress options:

- `sms_auto_generate_receipts`: Enable/disable automatic receipt generation
- `sms_default_receipt_methods`: Default receipt delivery methods
- `sms_receipt_number_prefix`: Prefix for receipt numbers
- `sms_transaction_number_prefix`: Prefix for transaction numbers

## Hooks and Filters

### Actions

- `sms_transaction_created`: Fired when a transaction is created
- `sms_transaction_status_changed`: Fired when transaction status changes
- `sms_transaction_completed`: Fired when transaction is completed
- `sms_transaction_failed`: Fired when transaction fails
- `sms_receipt_generated`: Fired when receipt is generated

### Filters

- `sms_receipt_template_data`: Filter receipt template data
- `sms_receipt_template_content`: Filter receipt content
- `sms_receipt_templates`: Filter available receipt templates

## Error Handling

The system includes comprehensive error handling:

1. **WP_Error Objects**: All methods return WP_Error on failure
2. **Logging**: Automatic logging of all transaction activities
3. **Admin Notifications**: Notifications for failed transactions
4. **Retry Logic**: Automatic retry for failed gateway communications

## Security

Security measures implemented:

1. **Nonce Verification**: All AJAX requests use nonces
2. **Capability Checks**: User capability verification
3. **Input Sanitization**: All inputs are sanitized
4. **SQL Injection Prevention**: Uses WordPress database methods

## Performance

Performance optimizations:

1. **Singleton Pattern**: Prevents multiple instances
2. **Caching**: Uses WordPress object caching where available
3. **Batch Processing**: Bulk operations for efficiency
4. **Scheduled Tasks**: Background processing for heavy operations