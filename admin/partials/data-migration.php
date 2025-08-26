<?php
/**
 * Data Migration Admin Interface
 *
 * Provides admin interface for data import/export, validation, and backup/restore operations.
 *
 * @package School_Management_System
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize migration tools
$data_migrator = new SMS_Data_Migrator();
$data_validator = new SMS_Data_Validator();
$backup_manager = new SMS_Backup_Manager();

// Handle form submissions
$message = '';
$message_type = '';

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'import_students':
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $upload_result = wp_handle_upload($_FILES['import_file'], ['test_form' => false]);
                if (!isset($upload_result['error'])) {
                    $mapping = $_POST['field_mapping'] ?? [];
                    $import_result = $data_migrator->import_students($upload_result['file'], $mapping);
                    
                    if (is_wp_error($import_result)) {
                        $message = 'Import failed: ' . $import_result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = "Import completed. Success: {$import_result['success']}, Errors: {$import_result['errors']}";
                        $message_type = 'success';
                    }
                } else {
                    $message = 'File upload failed: ' . $upload_result['error'];
                    $message_type = 'error';
                }
            }
            break;

        case 'validate_data':
            $validation_report = $data_validator->run_full_system_validation();
            $message = "Validation completed. Total issues found: {$validation_report['total_issues']}";
            $message_type = $validation_report['total_issues'] > 0 ? 'warning' : 'success';
            break;

        case 'create_backup':
            $backup_options = [
                'include_database' => isset($_POST['include_database']),
                'include_files' => isset($_POST['include_files']),
                'include_uploads' => isset($_POST['include_uploads']),
                'compress' => isset($_POST['compress']),
                'description' => sanitize_text_field($_POST['backup_description'] ?? 'Manual backup')
            ];
            
            $backup_result = $backup_manager->create_full_backup($backup_options);
            
            if (is_wp_error($backup_result)) {
                $message = 'Backup failed: ' . $backup_result->get_error_message();
                $message_type = 'error';
            } else {
                $message = "Backup created successfully. ID: {$backup_result['id']}";
                $message_type = 'success';
            }
            break;
    }
}

// Get existing backups
$backups = $backup_manager->list_backups();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sms-migration-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#import" class="nav-tab nav-tab-active">Data Import</a>
            <a href="#validation" class="nav-tab">Data Validation</a>
            <a href="#backup" class="nav-tab">Backup & Restore</a>
        </nav>

        <!-- Data Import Tab -->
        <div id="import" class="tab-content active">
            <div class="card">
                <h2>Import Students</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('sms_import_students'); ?>
                    <input type="hidden" name="action" value="import_students">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_file">Import File</label>
                            </th>
                            <td>
                                <input type="file" id="import_file" name="import_file" accept=".csv,.json" required>
                                <p class="description">Upload CSV or JSON file with student data. Maximum file size: <?php echo wp_max_upload_size(); ?> bytes.</p>
                            </td>
                        </tr>
                    </table>

                    <h3>Field Mapping</h3>
                    <p>Map the columns in your import file to system fields:</p>
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>System Field</th>
                                <th>Import Column Name</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Full Name</td>
                                <td><input type="text" name="field_mapping[full_name]" value="full_name" class="regular-text"></td>
                                <td><span class="required">Yes</span></td>
                            </tr>
                            <tr>
                                <td>Admission Number</td>
                                <td><input type="text" name="field_mapping[admission_number]" value="admission_number" class="regular-text"></td>
                                <td>No (auto-generated if empty)</td>
                            </tr>
                            <tr>
                                <td>Date of Birth</td>
                                <td><input type="text" name="field_mapping[date_of_birth]" value="date_of_birth" class="regular-text"></td>
                                <td><span class="required">Yes</span></td>
                            </tr>
                            <tr>
                                <td>Grade Level</td>
                                <td><input type="text" name="field_mapping[grade_level]" value="grade_level" class="regular-text"></td>
                                <td><span class="required">Yes</span></td>
                            </tr>
                            <tr>
                                <td>Parent Name</td>
                                <td><input type="text" name="field_mapping[parent_name]" value="parent_name" class="regular-text"></td>
                                <td>No</td>
                            </tr>
                            <tr>
                                <td>Parent Email</td>
                                <td><input type="text" name="field_mapping[parent_email]" value="parent_email" class="regular-text"></td>
                                <td><span class="required">Yes</span></td>
                            </tr>
                            <tr>
                                <td>Parent Phone</td>
                                <td><input type="text" name="field_mapping[parent_phone]" value="parent_phone" class="regular-text"></td>
                                <td><span class="required">Yes</span></td>
                            </tr>
                            <tr>
                                <td>Address</td>
                                <td><input type="text" name="field_mapping[address]" value="address" class="regular-text"></td>
                                <td>No</td>
                            </tr>
                            <tr>
                                <td>Medical Information</td>
                                <td><input type="text" name="field_mapping[medical_info]" value="medical_info" class="regular-text"></td>
                                <td>No</td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button('Import Students', 'primary', 'submit', false); ?>
                </form>
            </div>

            <div class="card">
                <h2>Import Academic Data</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('sms_import_academic'); ?>
                    <input type="hidden" name="action" value="import_academic">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="academic_type">Data Type</label>
                            </th>
                            <td>
                                <select id="academic_type" name="academic_type" required>
                                    <option value="">Select data type...</option>
                                    <option value="classes">Classes</option>
                                    <option value="subjects">Subjects</option>
                                    <option value="grades">Grade Levels</option>
                                    <option value="academic_years">Academic Years</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="academic_file">Import File</label>
                            </th>
                            <td>
                                <input type="file" id="academic_file" name="import_file" accept=".csv,.json" required>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Import Academic Data', 'primary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <!-- Data Validation Tab -->
        <div id="validation" class="tab-content">
            <div class="card">
                <h2>System Data Validation</h2>
                <p>Run comprehensive validation checks on all system data to identify and fix issues.</p>
                
                <form method="post">
                    <?php wp_nonce_field('sms_validate_data'); ?>
                    <input type="hidden" name="action" value="validate_data">
                    <?php submit_button('Run Full Validation', 'primary', 'submit', false); ?>
                </form>

                <?php if (isset($validation_report)): ?>
                    <div class="validation-results">
                        <h3>Validation Results</h3>
                        <p><strong>Total Issues Found:</strong> <?php echo esc_html($validation_report['total_issues']); ?></p>
                        
                        <?php foreach ($validation_report['categories'] as $category => $results): ?>
                            <div class="validation-category">
                                <h4><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></h4>
                                <p>Checked: <?php echo esc_html($results['total_checked']); ?> | Issues: <?php echo esc_html(count($results['issues'])); ?></p>
                                
                                <?php if (!empty($results['issues'])): ?>
                                    <details>
                                        <summary>View Issues</summary>
                                        <ul>
                                            <?php foreach (array_slice($results['issues'], 0, 10) as $issue): ?>
                                                <li>
                                                    <strong><?php echo esc_html($issue['title'] ?? $issue['type'] ?? 'Issue'); ?>:</strong>
                                                    <?php echo esc_html(is_array($issue['errors']) ? implode(', ', $issue['errors']) : $issue['error']); ?>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php if (count($results['issues']) > 10): ?>
                                                <li><em>... and <?php echo count($results['issues']) - 10; ?> more issues</em></li>
                                            <?php endif; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Data Cleanup</h2>
                <p>Clean up orphaned data and fix broken relationships.</p>
                
                <form method="post">
                    <?php wp_nonce_field('sms_cleanup_data'); ?>
                    <input type="hidden" name="action" value="cleanup_data">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Cleanup Options</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="remove_orphaned_transactions" value="1">
                                        Remove orphaned transactions
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="remove_orphaned_attendance" value="1">
                                        Remove orphaned attendance records
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="fix_relationships" value="1" checked>
                                        Fix broken relationships
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="backup_before_cleanup" value="1" checked>
                                        Create backup before cleanup
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Run Cleanup', 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <!-- Backup & Restore Tab -->
        <div id="backup" class="tab-content">
            <div class="card">
                <h2>Create Backup</h2>
                <form method="post">
                    <?php wp_nonce_field('sms_create_backup'); ?>
                    <input type="hidden" name="action" value="create_backup">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="backup_description">Description</label>
                            </th>
                            <td>
                                <input type="text" id="backup_description" name="backup_description" value="Manual backup - <?php echo date('Y-m-d H:i'); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Options</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="include_database" value="1" checked>
                                        Include database
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="include_files" value="1" checked>
                                        Include plugin files
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="include_uploads" value="1">
                                        Include uploads directory
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="compress" value="1" checked>
                                        Compress backup
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Create Backup', 'primary', 'submit', false); ?>
                </form>
            </div>

            <div class="card">
                <h2>Available Backups</h2>
                <?php if (empty($backups)): ?>
                    <p>No backups found.</p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Backup ID</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><code><?php echo esc_html($backup['id']); ?></code></td>
                                    <td><?php echo esc_html($backup['description']); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($backup['timestamp']))); ?></td>
                                    <td><?php echo esc_html(size_format($backup['size'])); ?></td>
                                    <td>
                                        <span class="status-<?php echo esc_attr($backup['status']); ?>">
                                            <?php echo esc_html(ucfirst($backup['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small restore-backup" data-backup-id="<?php echo esc_attr($backup['id']); ?>">
                                            Restore
                                        </button>
                                        <button type="button" class="button button-small button-link-delete delete-backup" data-backup-id="<?php echo esc_attr($backup['id']); ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.sms-migration-tabs .nav-tab-wrapper {
    margin-bottom: 20px;
}

.sms-migration-tabs .tab-content {
    display: none;
}

.sms-migration-tabs .tab-content.active {
    display: block;
}

.validation-category {
    margin: 15px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.validation-category h4 {
    margin: 0 0 10px 0;
}

.validation-results details {
    margin-top: 10px;
}

.validation-results summary {
    cursor: pointer;
    font-weight: bold;
}

.validation-results ul {
    margin: 10px 0;
    padding-left: 20px;
}

.required {
    color: #d63638;
    font-weight: bold;
}

.status-completed {
    color: #00a32a;
}

.status-in_progress {
    color: #dba617;
}

.status-failed {
    color: #d63638;
}

.card {
    margin-bottom: 20px;
}

.card h2 {
    margin-top: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // Restore backup
    $('.restore-backup').on('click', function() {
        var backupId = $(this).data('backup-id');
        
        if (confirm('Are you sure you want to restore from this backup? This will overwrite current data.')) {
            // Show loading state
            $(this).prop('disabled', true).text('Restoring...');
            
            // Submit restore request
            $.post(ajaxurl, {
                action: 'sms_restore_backup',
                backup_id: backupId,
                nonce: '<?php echo wp_create_nonce("sms_restore_backup"); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Backup restored successfully!');
                    location.reload();
                } else {
                    alert('Restore failed: ' + response.data);
                }
            }).always(function() {
                $('.restore-backup').prop('disabled', false).text('Restore');
            });
        }
    });

    // Delete backup
    $('.delete-backup').on('click', function() {
        var backupId = $(this).data('backup-id');
        
        if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
            $(this).prop('disabled', true).text('Deleting...');
            
            $.post(ajaxurl, {
                action: 'sms_delete_backup',
                backup_id: backupId,
                nonce: '<?php echo wp_create_nonce("sms_delete_backup"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Delete failed: ' + response.data);
                }
            }).always(function() {
                $('.delete-backup').prop('disabled', false).text('Delete');
            });
        }
    });
});
</script>