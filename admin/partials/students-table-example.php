<?php
/**
 * Example Students Table using Responsive Data Table
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Include the data table class
if (!class_exists('SMS_Data_Table')) {
    require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-data-table.php';
}

// Sample student data (in real implementation, this would come from database)
$students_data = array(
    array(
        'id' => 1,
        'admission_number' => 'STU001',
        'full_name' => 'John Doe',
        'class' => 'Grade 5A',
        'parent_name' => 'Jane Doe',
        'phone' => '0712345678',
        'status' => 'active',
        'enrollment_date' => '2024-01-15',
        'outstanding_fees' => 5000.00
    ),
    array(
        'id' => 2,
        'admission_number' => 'STU002',
        'full_name' => 'Mary Smith',
        'class' => 'Grade 4B',
        'parent_name' => 'Robert Smith',
        'phone' => '0723456789',
        'status' => 'active',
        'enrollment_date' => '2024-01-20',
        'outstanding_fees' => 0.00
    ),
    array(
        'id' => 3,
        'admission_number' => 'STU003',
        'full_name' => 'Peter Johnson',
        'class' => 'Grade 6A',
        'parent_name' => 'Sarah Johnson',
        'phone' => '0734567890',
        'status' => 'inactive',
        'enrollment_date' => '2023-09-10',
        'outstanding_fees' => 12500.00
    )
);

// Configure the data table
$table_config = array(
    'id' => 'students-table',
    'class' => 'sms-responsive-table students-table',
    'per_page' => 20,
    'show_pagination' => true,
    'show_search' => true,
    'show_filters' => true,
    'show_export' => true,
    'sortable' => true,
    'responsive' => true,
    'ajax' => false,
    'columns' => array(
        'admission_number' => array(
            'title' => __('Admission No.', 'school-management-system'),
            'sortable' => true,
            'type' => 'text'
        ),
        'full_name' => array(
            'title' => __('Student Name', 'school-management-system'),
            'sortable' => true,
            'type' => 'text'
        ),
        'class' => array(
            'title' => __('Class', 'school-management-system'),
            'sortable' => true,
            'type' => 'text'
        ),
        'parent_name' => array(
            'title' => __('Parent Name', 'school-management-system'),
            'sortable' => true,
            'type' => 'text'
        ),
        'phone' => array(
            'title' => __('Phone', 'school-management-system'),
            'sortable' => false,
            'type' => 'text'
        ),
        'status' => array(
            'title' => __('Status', 'school-management-system'),
            'sortable' => true,
            'type' => 'status'
        ),
        'enrollment_date' => array(
            'title' => __('Enrollment Date', 'school-management-system'),
            'sortable' => true,
            'type' => 'date'
        ),
        'outstanding_fees' => array(
            'title' => __('Outstanding Fees', 'school-management-system'),
            'sortable' => true,
            'type' => 'currency'
        )
    ),
    'actions' => array(
        'view' => array(
            'label' => __('View', 'school-management-system'),
            'icon' => 'visibility',
            'class' => 'view',
            'url' => function($row) {
                return admin_url('post.php?post=' . $row['id'] . '&action=edit');
            }
        ),
        'edit' => array(
            'label' => __('Edit', 'school-management-system'),
            'icon' => 'edit',
            'class' => 'edit',
            'url' => function($row) {
                return admin_url('post.php?post=' . $row['id'] . '&action=edit');
            }
        ),
        'delete' => array(
            'label' => __('Delete', 'school-management-system'),
            'icon' => 'trash',
            'class' => 'delete',
            'url' => '#'
        )
    ),
    'bulk_actions' => array(
        'activate' => __('Activate', 'school-management-system'),
        'deactivate' => __('Deactivate', 'school-management-system'),
        'delete' => __('Delete', 'school-management-system'),
        'export' => __('Export Selected', 'school-management-system')
    )
);

// Create and configure the table
$data_table = new SMS_Data_Table($table_config);
$data_table->set_data($students_data, count($students_data));
?>

<div class="wrap">
    <h1><?php _e('Students Management', 'school-management-system'); ?></h1>
    
    <!-- Add New Student Button -->
    <div class="page-title-action">
        <a href="<?php echo admin_url('post-new.php?post_type=sms_students'); ?>" class="button button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Add New Student', 'school-management-system'); ?>
        </a>
    </div>
    
    <!-- Students Table -->
    <?php echo $data_table->render(); ?>
    
    <!-- Additional Actions -->
    <div class="sms-page-actions">
        <div class="actions-left">
            <button type="button" class="button" id="import-students">
                <span class="dashicons dashicons-upload"></span>
                <?php _e('Import Students', 'school-management-system'); ?>
            </button>
            <button type="button" class="button" id="bulk-sms">
                <span class="dashicons dashicons-email-alt"></span>
                <?php _e('Send Bulk SMS', 'school-management-system'); ?>
            </button>
        </div>
        
        <div class="actions-right">
            <button type="button" class="button" id="generate-reports">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('Generate Reports', 'school-management-system'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.page-title-action {
    margin: 20px 0;
}

.page-title-action .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.sms-page-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.actions-left,
.actions-right {
    display: flex;
    gap: 10px;
}

.sms-page-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

@media (max-width: 768px) {
    .sms-page-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .actions-left,
    .actions-right {
        width: 100%;
        justify-content: center;
    }
    
    .sms-page-actions .button {
        flex: 1;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Import students functionality
    $('#import-students').on('click', function() {
        // Open import modal or redirect to import page
        console.log('Import students clicked');
    });
    
    // Bulk SMS functionality
    $('#bulk-sms').on('click', function() {
        var selectedStudents = $('#students-table-wrapper .row-selector:checked');
        
        if (selectedStudents.length === 0) {
            alert('Please select students to send SMS to.');
            return;
        }
        
        // Open SMS modal or redirect to SMS page
        console.log('Bulk SMS clicked for', selectedStudents.length, 'students');
    });
    
    // Generate reports functionality
    $('#generate-reports').on('click', function() {
        // Open reports modal or redirect to reports page
        console.log('Generate reports clicked');
    });
});
</script>