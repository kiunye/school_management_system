<?php
/**
 * Invoice Template Manager
 *
 * Handles customizable invoice templates, PDF generation,
 * and template rendering for different invoice formats.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Invoice Template Manager Class
 */
class SMS_Invoice_Template_Manager extends SMS_Base {

    /**
     * Available templates
     */
    private $templates = [];

    /**
     * Template settings
     */
    private $template_settings;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Load template settings
        $this->load_template_settings();
        
        // Register default templates
        $this->register_default_templates();
        
        // Hook into template rendering
        add_action('sms_render_invoice_template', [$this, 'render_invoice_template'], 10, 3);
        add_action('sms_generate_invoice_pdf', [$this, 'generate_invoice_pdf'], 10, 2);
        
        // Template management hooks
        add_action('sms_register_invoice_template', [$this, 'register_template'], 10, 2);
        add_filter('sms_get_invoice_templates', [$this, 'get_available_templates']);
        
        // AJAX handlers for template preview
        add_action('wp_ajax_sms_preview_invoice_template', [$this, 'ajax_preview_template']);
    }

    /**
     * Register a new invoice template.
     *
     * @param string $template_id   Template ID
     * @param array  $template_data Template configuration
     */
    public function register_template($template_id, $template_data) {
        $this->templates[$template_id] = wp_parse_args($template_data, [
            'name' => '',
            'description' => '',
            'template_file' => '',
            'css_file' => '',
            'supports' => ['header', 'footer', 'items', 'totals'],
            'settings' => []
        ]);
    }

    /**
     * Get available invoice templates.
     *
     * @return array Available templates
     */
    public function get_available_templates() {
        return $this->templates;
    }

    /**
     * Render invoice template for display or PDF generation.
     *
     * @param int    $invoice_id   Invoice ID
     * @param string $template_id  Template ID to use
     * @param array  $options      Rendering options
     * @return string Rendered HTML
     */
    public function render_invoice_template($invoice_id, $template_id = null, $options = []) {
        // Use default template if none specified
        if (!$template_id) {
            $template_id = $this->template_settings['default_template'] ?? 'standard';
        }

        // Check if template exists
        if (!isset($this->templates[$template_id])) {
            $template_id = 'standard'; // Fallback to standard template
        }

        $template = $this->templates[$template_id];
        
        // Get invoice data
        $invoice_data = $this->get_invoice_data($invoice_id);
        if (!$invoice_data) {
            return '<p>Invoice not found.</p>';
        }

        // Get school information
        $school_data = $this->get_school_data();
        
        // Prepare template variables
        $template_vars = [
            'invoice' => $invoice_data,
            'school' => $school_data,
            'template_id' => $template_id,
            'options' => $options,
            'settings' => $this->template_settings
        ];

        // Start output buffering
        ob_start();
        
        // Include template CSS if not PDF generation
        if (empty($options['pdf_mode'])) {
            $this->include_template_css($template_id);
        }

        // Render template
        $this->render_template_file($template, $template_vars);
        
        return ob_get_clean();
    }

    /**
     * Generate PDF from invoice template.
     *
     * @param int    $invoice_id  Invoice ID
     * @param string $template_id Template ID
     * @return string|WP_Error PDF file path or error
     */
    public function generate_invoice_pdf($invoice_id, $template_id = null) {
        try {
            // Check if PDF library is available
            if (!class_exists('TCPDF')) {
                return new WP_Error('pdf_library_missing', __('PDF generation library not available.', 'school-management-system'));
            }

            // Render HTML for PDF
            $html_content = $this->render_invoice_template($invoice_id, $template_id, ['pdf_mode' => true]);
            
            // Get invoice data for filename
            $invoice_data = $this->get_invoice_data($invoice_id);
            $filename = 'invoice-' . $invoice_data['invoice_number'] . '.pdf';
            
            // Create PDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('School Management System');
            $pdf->SetAuthor($invoice_data['school']['name']);
            $pdf->SetTitle('Invoice ' . $invoice_data['invoice_number']);
            $pdf->SetSubject('Student Fee Invoice');
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Add a page
            $pdf->AddPage();
            
            // Write HTML content
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            // Create uploads directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $invoice_dir = $upload_dir['basedir'] . '/invoices/';
            if (!file_exists($invoice_dir)) {
                wp_mkdir_p($invoice_dir);
            }
            
            // Save PDF file
            $file_path = $invoice_dir . $filename;
            $pdf->Output($file_path, 'F');
            
            // Log PDF generation
            $this->log_activity(
                get_current_user_id(),
                'invoice_pdf_generated',
                'invoice',
                $invoice_id,
                ['filename' => $filename]
            );
            
            return $file_path;
            
        } catch (Exception $e) {
            return new WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }

    /**
     * Get invoice data for template rendering.
     *
     * @param int $invoice_id Invoice ID
     * @return array|false Invoice data or false if not found
     */
    private function get_invoice_data($invoice_id) {
        $invoice_post = get_post($invoice_id);
        if (!$invoice_post || $invoice_post->post_type !== 'sms_invoices') {
            return false;
        }

        // Get student data
        $student_id = get_field('student', $invoice_id);
        $student_data = [];
        if ($student_id) {
            $student_data = [
                'id' => $student_id,
                'name' => get_field('full_name', $student_id),
                'admission_number' => get_field('admission_number', $student_id),
                'grade' => get_field('grade_level', $student_id),
                'class' => get_field('current_class', $student_id),
                'parent_name' => get_field('parent_name', $student_id),
                'parent_email' => get_field('parent_email', $student_id),
                'parent_phone' => get_field('parent_phone', $student_id)
            ];
        }

        // Get invoice items
        $invoice_items = get_field('invoice_items', $invoice_id) ?? [];
        $formatted_items = [];
        foreach ($invoice_items as $item) {
            $formatted_items[] = [
                'fee_id' => $item['item_fee'],
                'fee_name' => get_the_title($item['item_fee']),
                'description' => $item['item_description'],
                'quantity' => $item['item_quantity'],
                'unit_price' => floatval($item['item_unit_price']),
                'discount' => floatval($item['item_discount']),
                'total' => floatval($item['item_total'])
            ];
        }

        return [
            'id' => $invoice_id,
            'invoice_number' => get_field('invoice_number', $invoice_id),
            'invoice_date' => get_field('invoice_date', $invoice_id),
            'due_date' => get_field('due_date', $invoice_id),
            'status' => get_field('invoice_status', $invoice_id),
            'payment_status' => get_field('payment_status', $invoice_id),
            'student' => $student_data,
            'items' => $formatted_items,
            'subtotal' => floatval(get_field('subtotal', $invoice_id)),
            'total_discount' => floatval(get_field('total_discount', $invoice_id)),
            'penalties' => floatval(get_field('penalties', $invoice_id)),
            'total_amount' => floatval(get_field('total_amount', $invoice_id)),
            'amount_paid' => floatval(get_field('amount_paid', $invoice_id)),
            'balance_due' => floatval(get_field('balance_due', $invoice_id)),
            'payment_methods' => get_field('payment_methods', $invoice_id),
            'payment_instructions' => get_field('payment_instructions', $invoice_id),
            'notes' => $invoice_post->post_content
        ];
    }

    /**
     * Get school data for template rendering.
     *
     * @return array School information
     */
    private function get_school_data() {
        return [
            'name' => get_option('sms_school_name', get_bloginfo('name')),
            'address' => get_option('sms_school_address', ''),
            'phone' => get_option('sms_school_phone', ''),
            'email' => get_option('sms_school_email', get_option('admin_email')),
            'website' => get_option('sms_school_website', home_url()),
            'logo' => get_option('sms_school_logo', ''),
            'registration_number' => get_option('sms_school_registration', ''),
            'bank_details' => get_option('sms_school_bank_details', [])
        ];
    }

    /**
     * Register default invoice templates.
     */
    private function register_default_templates() {
        // Standard template
        $this->register_template('standard', [
            'name' => __('Standard Invoice', 'school-management-system'),
            'description' => __('Clean, professional invoice template', 'school-management-system'),
            'template_file' => 'templates/invoice-standard.php',
            'css_file' => 'css/invoice-standard.css',
            'supports' => ['header', 'footer', 'items', 'totals', 'payment_info'],
            'settings' => [
                'show_logo' => true,
                'show_school_details' => true,
                'show_payment_methods' => true,
                'show_terms' => true
            ]
        ]);

        // Minimal template
        $this->register_template('minimal', [
            'name' => __('Minimal Invoice', 'school-management-system'),
            'description' => __('Simple, compact invoice template', 'school-management-system'),
            'template_file' => 'templates/invoice-minimal.php',
            'css_file' => 'css/invoice-minimal.css',
            'supports' => ['items', 'totals'],
            'settings' => [
                'show_logo' => false,
                'show_school_details' => true,
                'show_payment_methods' => false,
                'show_terms' => false
            ]
        ]);

        // Detailed template
        $this->register_template('detailed', [
            'name' => __('Detailed Invoice', 'school-management-system'),
            'description' => __('Comprehensive invoice with all details', 'school-management-system'),
            'template_file' => 'templates/invoice-detailed.php',
            'css_file' => 'css/invoice-detailed.css',
            'supports' => ['header', 'footer', 'items', 'totals', 'payment_info', 'terms', 'notes'],
            'settings' => [
                'show_logo' => true,
                'show_school_details' => true,
                'show_student_details' => true,
                'show_payment_methods' => true,
                'show_payment_history' => true,
                'show_terms' => true,
                'show_notes' => true
            ]
        ]);
    }

    /**
     * Include template CSS file.
     *
     * @param string $template_id Template ID
     */
    private function include_template_css($template_id) {
        if (!isset($this->templates[$template_id])) {
            return;
        }

        $template = $this->templates[$template_id];
        if (empty($template['css_file'])) {
            return;
        }

        $css_path = plugin_dir_path(__FILE__) . '../../admin/' . $template['css_file'];
        if (file_exists($css_path)) {
            echo '<style type="text/css">';
            include $css_path;
            echo '</style>';
        }
    }

    /**
     * Render template file with variables.
     *
     * @param array $template      Template configuration
     * @param array $template_vars Template variables
     */
    private function render_template_file($template, $template_vars) {
        $template_path = plugin_dir_path(__FILE__) . '../../admin/' . $template['template_file'];
        
        if (file_exists($template_path)) {
            // Extract variables for template use
            extract($template_vars);
            include $template_path;
        } else {
            // Fallback to built-in template
            $this->render_builtin_template($template_vars);
        }
    }

    /**
     * Render built-in template as fallback.
     *
     * @param array $template_vars Template variables
     */
    private function render_builtin_template($template_vars) {
        $invoice = $template_vars['invoice'];
        $school = $template_vars['school'];
        $settings = $template_vars['settings'];
        ?>
        <div class="invoice-template invoice-builtin">
            <!-- Header -->
            <div class="invoice-header">
                <?php if (!empty($settings['include_school_logo']) && !empty($school['logo'])): ?>
                    <div class="school-logo">
                        <img src="<?php echo esc_url($school['logo']); ?>" alt="<?php echo esc_attr($school['name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="school-info">
                    <h1><?php echo esc_html($school['name']); ?></h1>
                    <?php if (!empty($school['address'])): ?>
                        <p><?php echo esc_html($school['address']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($school['phone'])): ?>
                        <p>Tel: <?php echo esc_html($school['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($school['email'])): ?>
                        <p>Email: <?php echo esc_html($school['email']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="invoice-info">
                    <h2>INVOICE</h2>
                    <p><strong>Invoice #:</strong> <?php echo esc_html($invoice['invoice_number']); ?></p>
                    <p><strong>Date:</strong> <?php echo esc_html(date('d/m/Y', strtotime($invoice['invoice_date']))); ?></p>
                    <p><strong>Due Date:</strong> <?php echo esc_html(date('d/m/Y', strtotime($invoice['due_date']))); ?></p>
                </div>
            </div>

            <!-- Student Information -->
            <div class="student-info">
                <h3>Bill To:</h3>
                <p><strong><?php echo esc_html($invoice['student']['name']); ?></strong></p>
                <p>Admission #: <?php echo esc_html($invoice['student']['admission_number']); ?></p>
                <p>Grade: <?php echo esc_html($invoice['student']['grade']); ?></p>
                <?php if (!empty($invoice['student']['parent_name'])): ?>
                    <p>Parent: <?php echo esc_html($invoice['student']['parent_name']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Invoice Items -->
            <div class="invoice-items">
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Discount</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice['items'] as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($item['fee_name']); ?></strong>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><small><?php echo esc_html($item['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($item['quantity']); ?></td>
                                <td><?php echo $this->format_currency($item['unit_price']); ?></td>
                                <td><?php echo $this->format_currency($item['discount']); ?></td>
                                <td><?php echo $this->format_currency($item['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="invoice-totals">
                <table>
                    <tr>
                        <td>Subtotal:</td>
                        <td><?php echo $this->format_currency($invoice['subtotal']); ?></td>
                    </tr>
                    <?php if ($invoice['total_discount'] > 0): ?>
                        <tr>
                            <td>Total Discount:</td>
                            <td>-<?php echo $this->format_currency($invoice['total_discount']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($invoice['penalties'] > 0): ?>
                        <tr>
                            <td>Penalties:</td>
                            <td><?php echo $this->format_currency($invoice['penalties']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>Total Amount:</strong></td>
                        <td><strong><?php echo $this->format_currency($invoice['total_amount']); ?></strong></td>
                    </tr>
                    <?php if ($invoice['amount_paid'] > 0): ?>
                        <tr>
                            <td>Amount Paid:</td>
                            <td><?php echo $this->format_currency($invoice['amount_paid']); ?></td>
                        </tr>
                        <tr class="balance-row">
                            <td><strong>Balance Due:</strong></td>
                            <td><strong><?php echo $this->format_currency($invoice['balance_due']); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Payment Information -->
            <?php if (!empty($settings['include_payment_instructions'])): ?>
                <div class="payment-info">
                    <h3>Payment Information</h3>
                    <?php if (!empty($invoice['payment_methods'])): ?>
                        <p><strong>Accepted Payment Methods:</strong></p>
                        <ul>
                            <?php foreach ($invoice['payment_methods'] as $method): ?>
                                <li><?php echo esc_html(ucfirst(str_replace('_', ' ', $method))); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($invoice['payment_instructions'])): ?>
                        <p><?php echo esc_html($invoice['payment_instructions']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="invoice-footer">
                <?php if (!empty($settings['footer_text'])): ?>
                    <p><?php echo esc_html($settings['footer_text']); ?></p>
                <?php endif; ?>
                <p><small>Generated on <?php echo date('d/m/Y H:i'); ?></small></p>
            </div>
        </div>

        <style>
        .invoice-template {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .school-logo img {
            max-width: 100px;
            height: auto;
        }
        
        .school-info h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .invoice-info h2 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 24px;
        }
        
        .student-info {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #333;
        }
        
        .invoice-items table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .invoice-items th,
        .invoice-items td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .invoice-items th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .invoice-totals {
            float: right;
            width: 300px;
            margin-bottom: 30px;
        }
        
        .invoice-totals table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-totals td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .invoice-totals .total-row td,
        .invoice-totals .balance-row td {
            border-top: 2px solid #333;
            font-weight: bold;
        }
        
        .payment-info {
            clear: both;
            margin-top: 30px;
            padding: 15px;
            background-color: #f0f8ff;
            border: 1px solid #cce7ff;
        }
        
        .invoice-footer {
            margin-top: 40px;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            color: #666;
        }
        
        @media print {
            .invoice-template {
                max-width: none;
                margin: 0;
                padding: 0;
            }
        }
        </style>
        <?php
    }

    /**
     * Load template settings from options.
     */
    private function load_template_settings() {
        $this->template_settings = get_option('sms_invoice_template_settings', [
            'default_template' => 'standard',
            'include_school_logo' => true,
            'include_payment_instructions' => true,
            'footer_text' => 'Thank you for your payment.',
            'currency_symbol' => 'KES',
            'currency_position' => 'before'
        ]);
    }

    /**
     * AJAX handler for template preview.
     */
    public function ajax_preview_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_preview_template')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_invoices')) {
            wp_die(__('Insufficient permissions.', 'school-management-system'));
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $invoice_id = intval($_POST['invoice_id']);

        if (!$invoice_id) {
            wp_die(__('Invalid invoice ID.', 'school-management-system'));
        }

        // Render template
        $html = $this->render_invoice_template($invoice_id, $template_id, ['preview_mode' => true]);
        
        wp_send_json_success(['html' => $html]);
    }
}