<?php
/**
 * Inline Help System
 *
 * Provides contextual help, tooltips, and guided tours for complex features.
 *
 * @package School_Management_System
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Help System Class
 *
 * Manages inline help, tooltips, and user guidance throughout the system.
 */
class SMS_Help_System {

    /**
     * Help content storage
     */
    private $help_content = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_help_content();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_help_scripts']);
        add_action('wp_ajax_sms_get_help_content', [$this, 'ajax_get_help_content']);
        add_action('admin_footer', [$this, 'render_help_modal']);
        add_filter('screen_settings', [$this, 'add_help_tab'], 10, 2);
    }

    /**
     * Initialize help content
     */
    private function init_help_content() {
        $this->help_content = [
            'student_management' => [
                'title' => 'Student Management Help',
                'sections' => [
                    'adding_students' => [
                        'title' => 'Adding New Students',
                        'content' => 'Learn how to add new students to the system with all required information.',
                        'steps' => [
                            'Navigate to SMS > Students > Add New',
                            'Fill in personal details (name, date of birth, etc.)',
                            'Add parent/guardian information',
                            'Assign to appropriate grade level and class',
                            'Add medical information if applicable',
                            'Save the student record'
                        ],
                        'tips' => [
                            'Admission numbers are auto-generated if left blank',
                            'Parent accounts are created automatically',
                            'All required fields must be completed'
                        ]
                    ],
                    'bulk_import' => [
                        'title' => 'Bulk Student Import',
                        'content' => 'Import multiple students at once using CSV or Excel files.',
                        'steps' => [
                            'Prepare your data file with required columns',
                            'Go to SMS > Data Migration > Import',
                            'Upload your file and map fields',
                            'Review the preview and fix any errors',
                            'Complete the import process'
                        ],
                        'tips' => [
                            'Download the sample template for correct format',
                            'Ensure phone numbers are in correct format (+254...)',
                            'Check for duplicate admission numbers'
                        ]
                    ]
                ]
            ],
            'payment_gateways' => [
                'title' => 'Payment Gateway Configuration',
                'sections' => [
                    'mpesa_setup' => [
                        'title' => 'M-Pesa Configuration',
                        'content' => 'Set up M-Pesa STK Push for seamless mobile payments.',
                        'steps' => [
                            'Obtain M-Pesa API credentials from Safaricom',
                            'Go to SMS > Settings > Payment Gateways',
                            'Select M-Pesa and enter your credentials',
                            'Test the connection in sandbox mode',
                            'Switch to production when ready'
                        ],
                        'tips' => [
                            'Always test in sandbox mode first',
                            'Keep your credentials secure',
                            'Monitor transaction logs regularly'
                        ]
                    ],
                    'airtel_setup' => [
                        'title' => 'Airtel Money Configuration',
                        'content' => 'Configure Airtel Money for mobile payments.',
                        'steps' => [
                            'Register for Airtel Money merchant account',
                            'Obtain API credentials',
                            'Configure in SMS > Settings > Payment Gateways',
                            'Test with small amounts first',
                            'Go live after successful testing'
                        ]
                    ]
                ]
            ],
            'attendance_management' => [
                'title' => 'Attendance Management',
                'sections' => [
                    'marking_attendance' => [
                        'title' => 'Marking Daily Attendance',
                        'content' => 'Efficiently mark attendance for your classes.',
                        'steps' => [
                            'Navigate to SMS > Attendance > Mark Attendance',
                            'Select the class and date',
                            'Mark each student as Present, Absent, Late, or Excused',
                            'Add notes for absent students if needed',
                            'Save the attendance record'
                        ],
                        'tips' => [
                            'Mark attendance as soon as possible after class',
                            'Use bulk actions for faster marking',
                            'Parents are automatically notified of absences'
                        ]
                    ],
                    'attendance_reports' => [
                        'title' => 'Generating Attendance Reports',
                        'content' => 'Create comprehensive attendance reports for analysis.',
                        'steps' => [
                            'Go to SMS > Reports > Attendance Reports',
                            'Select date range and filters',
                            'Choose report type (summary, detailed, individual)',
                            'Generate and download the report',
                            'Share with relevant stakeholders'
                        ]
                    ]
                ]
            ],
            'fee_management' => [
                'title' => 'Fee Management System',
                'sections' => [
                    'creating_fees' => [
                        'title' => 'Creating Fee Structures',
                        'content' => 'Set up different types of fees for your school.',
                        'steps' => [
                            'Navigate to SMS > Fees > Add New',
                            'Enter fee details (name, amount, due date)',
                            'Select applicable grade levels',
                            'Configure payment options and penalties',
                            'Publish the fee structure'
                        ],
                        'tips' => [
                            'Set realistic due dates',
                            'Consider installment options for large fees',
                            'Configure automatic reminders'
                        ]
                    ],
                    'invoice_generation' => [
                        'title' => 'Generating Invoices',
                        'content' => 'Create and send invoices to parents.',
                        'steps' => [
                            'Go to SMS > Invoices > Bulk Generator',
                            'Select students and fee types',
                            'Review generated invoices',
                            'Send to parents via email/SMS',
                            'Monitor payment status'
                        ]
                    ]
                ]
            ],
            'communication_system' => [
                'title' => 'Communication Tools',
                'sections' => [
                    'sms_setup' => [
                        'title' => 'SMS Configuration',
                        'content' => 'Set up SMS communication using Africastalking.',
                        'steps' => [
                            'Create Africastalking account',
                            'Obtain API credentials',
                            'Configure in SMS > Settings > SMS',
                            'Test with a small group first',
                            'Set up automated notifications'
                        ],
                        'tips' => [
                            'Monitor SMS credit balance',
                            'Use templates for common messages',
                            'Keep messages under 160 characters'
                        ]
                    ],
                    'bulk_messaging' => [
                        'title' => 'Sending Bulk Messages',
                        'content' => 'Send messages to multiple recipients efficiently.',
                        'steps' => [
                            'Navigate to SMS > Communication > Send SMS',
                            'Compose your message',
                            'Select target audience',
                            'Preview and send the message',
                            'Monitor delivery status'
                        ]
                    ]
                ]
            ],
            'transport_management' => [
                'title' => 'Transport Management',
                'sections' => [
                    'route_creation' => [
                        'title' => 'Creating Transport Routes',
                        'content' => 'Set up school transport routes and schedules.',
                        'steps' => [
                            'Go to SMS > Transport > Routes > Add New',
                            'Enter route name and description',
                            'Add all stops with timing',
                            'Set capacity and fee information',
                            'Assign driver and vehicle details'
                        ],
                        'tips' => [
                            'Plan routes efficiently to minimize travel time',
                            'Consider traffic patterns and road conditions',
                            'Maintain buffer time for delays'
                        ]
                    ],
                    'student_assignment' => [
                        'title' => 'Assigning Students to Routes',
                        'content' => 'Assign students to appropriate transport routes.',
                        'steps' => [
                            'Navigate to SMS > Transport > Assignments',
                            'Select students to assign',
                            'Choose appropriate route',
                            'Verify capacity availability',
                            'Save assignments and notify parents'
                        ]
                    ]
                ]
            ],
            'reporting_system' => [
                'title' => 'Reports and Analytics',
                'sections' => [
                    'financial_reports' => [
                        'title' => 'Financial Reporting',
                        'content' => 'Generate comprehensive financial reports.',
                        'steps' => [
                            'Go to SMS > Reports > Financial Reports',
                            'Select report type and date range',
                            'Apply filters as needed',
                            'Generate the report',
                            'Export in desired format'
                        ],
                        'tips' => [
                            'Run reports regularly for better insights',
                            'Use filters to focus on specific data',
                            'Export to Excel for further analysis'
                        ]
                    ],
                    'academic_reports' => [
                        'title' => 'Academic Reporting',
                        'content' => 'Create reports on student performance and attendance.',
                        'steps' => [
                            'Navigate to SMS > Reports > Academic Reports',
                            'Choose report type (attendance, performance, etc.)',
                            'Set parameters and filters',
                            'Generate and review the report',
                            'Share with relevant stakeholders'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Enqueue help system scripts and styles
     */
    public function enqueue_help_scripts($hook) {
        // Only load on SMS admin pages
        if (strpos($hook, 'sms') === false) {
            return;
        }

        wp_enqueue_script(
            'sms-help-system',
            plugin_dir_url(__FILE__) . '../../admin/js/help-system.js',
            ['jquery', 'wp-util'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'sms-help-system',
            plugin_dir_url(__FILE__) . '../../admin/css/help-system.css',
            [],
            '1.0.0'
        );

        wp_localize_script('sms-help-system', 'smsHelp', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_help_nonce'),
            'strings' => [
                'loading' => __('Loading help content...', 'school-management-system'),
                'error' => __('Error loading help content.', 'school-management-system'),
                'close' => __('Close', 'school-management-system'),
                'next' => __('Next', 'school-management-system'),
                'previous' => __('Previous', 'school-management-system'),
                'finish' => __('Finish Tour', 'school-management-system')
            ]
        ]);
    }

    /**
     * AJAX handler for getting help content
     */
    public function ajax_get_help_content() {
        check_ajax_referer('sms_help_nonce', 'nonce');

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $section = sanitize_text_field($_POST['section'] ?? '');

        if (empty($topic)) {
            wp_send_json_error('Topic is required');
        }

        $content = $this->get_help_content($topic, $section);

        if ($content) {
            wp_send_json_success($content);
        } else {
            wp_send_json_error('Help content not found');
        }
    }

    /**
     * Get help content for a specific topic and section
     */
    public function get_help_content($topic, $section = null) {
        if (!isset($this->help_content[$topic])) {
            return null;
        }

        $topic_content = $this->help_content[$topic];

        if ($section && isset($topic_content['sections'][$section])) {
            return [
                'title' => $topic_content['sections'][$section]['title'],
                'content' => $topic_content['sections'][$section]['content'],
                'steps' => $topic_content['sections'][$section]['steps'] ?? [],
                'tips' => $topic_content['sections'][$section]['tips'] ?? []
            ];
        }

        return $topic_content;
    }

    /**
     * Render help modal in admin footer
     */
    public function render_help_modal() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'sms') === false) {
            return;
        }
        ?>
        <div id="sms-help-modal" class="sms-modal" style="display: none;">
            <div class="sms-modal-content">
                <div class="sms-modal-header">
                    <h2 id="sms-help-title"></h2>
                    <button type="button" class="sms-modal-close">&times;</button>
                </div>
                <div class="sms-modal-body">
                    <div id="sms-help-content"></div>
                    <div id="sms-help-steps" style="display: none;">
                        <h4><?php _e('Steps:', 'school-management-system'); ?></h4>
                        <ol id="sms-help-steps-list"></ol>
                    </div>
                    <div id="sms-help-tips" style="display: none;">
                        <h4><?php _e('Tips:', 'school-management-system'); ?></h4>
                        <ul id="sms-help-tips-list"></ul>
                    </div>
                </div>
                <div class="sms-modal-footer">
                    <button type="button" class="button button-secondary sms-modal-close">
                        <?php _e('Close', 'school-management-system'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tour Modal -->
        <div id="sms-tour-modal" class="sms-modal sms-tour-modal" style="display: none;">
            <div class="sms-modal-content">
                <div class="sms-modal-header">
                    <h2 id="sms-tour-title"></h2>
                    <button type="button" class="sms-modal-close">&times;</button>
                </div>
                <div class="sms-modal-body">
                    <div id="sms-tour-content"></div>
                    <div class="sms-tour-progress">
                        <span id="sms-tour-step-counter"></span>
                        <div class="sms-tour-progress-bar">
                            <div id="sms-tour-progress-fill"></div>
                        </div>
                    </div>
                </div>
                <div class="sms-modal-footer">
                    <button type="button" class="button button-secondary" id="sms-tour-prev">
                        <?php _e('Previous', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="sms-tour-next">
                        <?php _e('Next', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="sms-tour-finish" style="display: none;">
                        <?php _e('Finish Tour', 'school-management-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add help tab to screen options
     */
    public function add_help_tab($settings, $screen) {
        if (strpos($screen->id, 'sms') === false) {
            return $settings;
        }

        $help_button = '<button type="button" class="button button-secondary sms-help-trigger" data-topic="' . esc_attr($this->get_screen_help_topic($screen->id)) . '">';
        $help_button .= '<span class="dashicons dashicons-editor-help"></span> ';
        $help_button .= __('Help', 'school-management-system');
        $help_button .= '</button>';

        return $settings . $help_button;
    }

    /**
     * Get help topic for current screen
     */
    private function get_screen_help_topic($screen_id) {
        $topic_map = [
            'sms_students' => 'student_management',
            'sms_fees' => 'fee_management',
            'sms_attendance' => 'attendance_management',
            'sms_communication' => 'communication_system',
            'sms_transport' => 'transport_management',
            'sms_reports' => 'reporting_system',
            'sms_settings' => 'payment_gateways'
        ];

        foreach ($topic_map as $screen_pattern => $topic) {
            if (strpos($screen_id, $screen_pattern) !== false) {
                return $topic;
            }
        }

        return 'general';
    }

    /**
     * Add help tooltip to form fields
     */
    public static function tooltip($content, $position = 'top') {
        return sprintf(
            '<span class="sms-tooltip" data-tooltip="%s" data-position="%s">
                <span class="dashicons dashicons-editor-help"></span>
            </span>',
            esc_attr($content),
            esc_attr($position)
        );
    }

    /**
     * Add contextual help button
     */
    public static function help_button($topic, $section = null, $text = null) {
        $text = $text ?: __('Help', 'school-management-system');
        
        return sprintf(
            '<button type="button" class="button button-secondary sms-help-trigger" data-topic="%s" data-section="%s">
                <span class="dashicons dashicons-editor-help"></span> %s
            </button>',
            esc_attr($topic),
            esc_attr($section),
            esc_html($text)
        );
    }

    /**
     * Add guided tour trigger
     */
    public static function tour_button($tour_id, $text = null) {
        $text = $text ?: __('Take Tour', 'school-management-system');
        
        return sprintf(
            '<button type="button" class="button button-primary sms-tour-trigger" data-tour="%s">
                <span class="dashicons dashicons-welcome-learn-more"></span> %s
            </button>',
            esc_attr($tour_id),
            esc_html($text)
        );
    }

    /**
     * Get guided tour content
     */
    public function get_tour_content($tour_id) {
        $tours = [
            'student_management_tour' => [
                'title' => 'Student Management Tour',
                'steps' => [
                    [
                        'target' => '.sms-students-menu',
                        'title' => 'Students Menu',
                        'content' => 'Access all student-related functions from this menu.',
                        'position' => 'right'
                    ],
                    [
                        'target' => '.sms-add-student-btn',
                        'title' => 'Add New Student',
                        'content' => 'Click here to add a new student to the system.',
                        'position' => 'bottom'
                    ],
                    [
                        'target' => '.sms-student-search',
                        'title' => 'Search Students',
                        'content' => 'Use this search box to quickly find specific students.',
                        'position' => 'bottom'
                    ],
                    [
                        'target' => '.sms-bulk-actions',
                        'title' => 'Bulk Actions',
                        'content' => 'Select multiple students and perform bulk operations.',
                        'position' => 'top'
                    ]
                ]
            ],
            'payment_setup_tour' => [
                'title' => 'Payment Gateway Setup Tour',
                'steps' => [
                    [
                        'target' => '.sms-payment-settings',
                        'title' => 'Payment Settings',
                        'content' => 'Configure your payment gateways here.',
                        'position' => 'right'
                    ],
                    [
                        'target' => '.sms-mpesa-config',
                        'title' => 'M-Pesa Configuration',
                        'content' => 'Set up M-Pesa for mobile payments.',
                        'position' => 'bottom'
                    ],
                    [
                        'target' => '.sms-test-connection',
                        'title' => 'Test Connection',
                        'content' => 'Always test your payment gateway configuration.',
                        'position' => 'top'
                    ]
                ]
            ]
        ];

        return $tours[$tour_id] ?? null;
    }

    /**
     * Render contextual help for specific fields
     */
    public static function field_help($field_name) {
        $help_texts = [
            'admission_number' => 'Unique identifier for the student. Leave blank for auto-generation.',
            'parent_phone' => 'Enter phone number in format +254XXXXXXXXX for SMS notifications.',
            'fee_amount' => 'Enter amount in KES (Kenyan Shillings).',
            'due_date' => 'Select the date when payment is due.',
            'penalty_rate' => 'Percentage penalty applied for late payments (e.g., 5 for 5%).',
            'sms_sender_id' => 'Your registered sender ID from Africastalking.',
            'mpesa_shortcode' => 'Your M-Pesa business shortcode from Safaricom.',
            'route_capacity' => 'Maximum number of students that can use this route.',
            'class_capacity' => 'Maximum number of students that can be enrolled in this class.'
        ];

        if (isset($help_texts[$field_name])) {
            return self::tooltip($help_texts[$field_name]);
        }

        return '';
    }
}