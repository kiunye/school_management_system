<?php
/**
 * Receipt Generator
 *
 * Handles receipt generation for payment transactions
 *
 * @package SchoolManagementSystem
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Receipt Generator Class
 */
class SMS_Receipt_Generator {
    
    /**
     * Receipt templates
     *
     * @var array
     */
    private $templates = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the receipt generator
     */
    private function init() {
        // Load receipt templates
        $this->load_templates();
        
        // Add hooks for customization
        add_filter('sms_receipt_template_data', array($this, 'filter_template_data'), 10, 2);
        add_filter('sms_receipt_template_content', array($this, 'filter_template_content'), 10, 3);
    }
    
    /**
     * Generate receipt for transaction
     *
     * @param array $transaction_data Transaction data
     * @param string $template Template name
     * @return string Receipt HTML content
     */
    public function generate_receipt($transaction_data, $template = 'default') {
        if (!isset($this->templates[$template])) {
            $template = 'default';
        }
        
        // Prepare template data
        $template_data = $this->prepare_template_data($transaction_data);
        
        // Apply filters to template data
        $template_data = apply_filters('sms_receipt_template_data', $template_data, $transaction_data);
        
        // Render template
        $receipt_content = $this->render_template($template, $template_data);
        
        // Apply filters to final content
        $receipt_content = apply_filters('sms_receipt_template_content', $receipt_content, $template, $template_data);
        
        return $receipt_content;
    }
    
    /**
     * Generate PDF receipt
     *
     * @param array $transaction_data Transaction data
     * @param string $template Template name
     * @return string|WP_Error PDF file path or error
     */
    public function generate_pdf_receipt($transaction_data, $template = 'default') {
        // Generate HTML content
        $html_content = $this->generate_receipt($transaction_data, $template);
        
        // Check if PDF generation is available
        if (!$this->is_pdf_generation_available()) {
            return new WP_Error('pdf_not_available', 'PDF generation not available');
        }
        
        // Generate PDF
        return $this->convert_html_to_pdf($html_content, $transaction_data);
    }
    
    /**
     * Load receipt templates
     */
    private function load_templates() {
        $this->templates = array(
            'default' => array(
                'name' => 'Default Receipt',
                'description' => 'Standard school payment receipt',
                'template' => 'default-receipt.php'
            ),
            'detailed' => array(
                'name' => 'Detailed Receipt',
                'description' => 'Detailed receipt with breakdown',
                'template' => 'detailed-receipt.php'
            ),
            'simple' => array(
                'name' => 'Simple Receipt',
                'description' => 'Simple receipt format',
                'template' => 'simple-receipt.php'
            )
        );
        
        // Allow custom templates
        $this->templates = apply_filters('sms_receipt_templates', $this->templates);
    }
    
    /**
     * Prepare template data
     *
     * @param array $transaction_data Transaction data
     * @return array Template data
     */
    private function prepare_template_data($transaction_data) {
        $fields = $transaction_data['fields'];
        $student = $transaction_data['student'];
        $invoice = $transaction_data['invoice'];
        
        // School information
        $school_data = array(
            'name' => get_option('sms_school_name', get_bloginfo('name')),
            'address' => get_option('sms_school_address', ''),
            'phone' => get_option('sms_school_phone', ''),
            'email' => get_option('sms_school_email', get_option('admin_email')),
            'logo' => get_option('sms_school_logo', ''),
            'website' => get_option('sms_school_website', get_site_url())
        );
        
        // Transaction information
        $transaction_info = array(
            'id' => $transaction_data['id'],
            'number' => $fields['transaction_number'] ?? '',
            'receipt_number' => $fields['receipt_number'] ?? '',
            'date' => $fields['transaction_date'] ?? '',
            'amount' => $fields['amount'] ?? 0,
            'currency' => $fields['currency'] ?? 'KES',
            'payment_method' => $fields['payment_method'] ?? '',
            'status' => $fields['transaction_status'] ?? '',
            'gateway' => $fields['gateway_name'] ?? '',
            'gateway_reference' => $fields['gateway_reference'] ?? '',
            'type' => $fields['transaction_type'] ?? 'payment'
        );
        
        // Student information
        $student_info = array(
            'id' => $student['id'] ?? '',
            'name' => $student['name'] ?? '',
            'admission_number' => $student['admission_number'] ?? '',
            'class' => $student['class'] ?? '',
            'parent_email' => $student['parent_email'] ?? '',
            'parent_phone' => $student['parent_phone'] ?? ''
        );
        
        // Invoice information (if applicable)
        $invoice_info = array();
        if ($invoice) {
            $invoice_info = array(
                'id' => $invoice['id'],
                'number' => $invoice['number'] ?? '',
                'due_date' => $invoice['due_date'] ?? '',
                'total_amount' => $invoice['total_amount'] ?? 0
            );
        }
        
        // Additional data
        $additional_data = array(
            'generated_date' => current_time('mysql'),
            'generated_by' => wp_get_current_user()->display_name,
            'academic_year' => get_option('sms_current_academic_year', date('Y')),
            'term' => get_option('sms_current_term', 'Term 1')
        );
        
        return array(
            'school' => $school_data,
            'transaction' => $transaction_info,
            'student' => $student_info,
            'invoice' => $invoice_info,
            'additional' => $additional_data
        );
    }
    
    /**
     * Render template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered content
     */
    private function render_template($template, $data) {
        // Check for custom template file
        $template_file = $this->get_template_file($template);
        
        if ($template_file && file_exists($template_file)) {
            ob_start();
            extract($data);
            include $template_file;
            return ob_get_clean();
        }
        
        // Use built-in template
        return $this->render_builtin_template($template, $data);
    }
    
    /**
     * Get template file path
     *
     * @param string $template Template name
     * @return string|null Template file path
     */
    private function get_template_file($template) {
        $template_dirs = array(
            get_stylesheet_directory() . '/sms-templates/receipts/',
            get_template_directory() . '/sms-templates/receipts/',
            plugin_dir_path(__FILE__) . '../templates/receipts/'
        );
        
        $template_filename = $this->templates[$template]['template'] ?? "{$template}-receipt.php";
        
        foreach ($template_dirs as $dir) {
            $file_path = $dir . $template_filename;
            if (file_exists($file_path)) {
                return $file_path;
            }
        }
        
        return null;
    }
    
    /**
     * Render built-in template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered content
     */
    private function render_builtin_template($template, $data) {
        switch ($template) {
            case 'detailed':
                return $this->render_detailed_template($data);
            case 'simple':
                return $this->render_simple_template($data);
            default:
                return $this->render_default_template($data);
        }
    }
    
    /**
     * Render default receipt template
     *
     * @param array $data Template data
     * @return string HTML content
     */
    private function render_default_template($data) {
        $school = $data['school'];
        $transaction = $data['transaction'];
        $student = $data['student'];
        $invoice = $data['invoice'];
        $additional = $data['additional'];
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - ' . esc_html($transaction['receipt_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .receipt-container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .school-logo { max-height: 80px; margin-bottom: 10px; }
        .school-name { font-size: 24px; font-weight: bold; margin: 10px 0; }
        .school-details { font-size: 14px; color: #666; }
        .receipt-title { font-size: 20px; font-weight: bold; text-align: center; margin: 20px 0; background: #f5f5f5; padding: 10px; }
        .receipt-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .receipt-info div { flex: 1; }
        .info-section { margin-bottom: 20px; }
        .info-section h3 { font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .info-label { font-weight: bold; }
        .amount-section { background: #f9f9f9; padding: 15px; margin: 20px 0; text-align: center; }
        .amount-value { font-size: 24px; font-weight: bold; color: #2c5aa0; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        .status-badge { padding: 5px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">';
        
        if (!empty($school['logo'])) {
            $html .= '<img src="' . esc_url($school['logo']) . '" alt="School Logo" class="school-logo">';
        }
        
        $html .= '<div class="school-name">' . esc_html($school['name']) . '</div>';
        
        if (!empty($school['address'])) {
            $html .= '<div class="school-details">' . esc_html($school['address']) . '</div>';
        }
        
        if (!empty($school['phone']) || !empty($school['email'])) {
            $html .= '<div class="school-details">';
            if (!empty($school['phone'])) {
                $html .= 'Tel: ' . esc_html($school['phone']);
            }
            if (!empty($school['phone']) && !empty($school['email'])) {
                $html .= ' | ';
            }
            if (!empty($school['email'])) {
                $html .= 'Email: ' . esc_html($school['email']);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>
        
        <div class="receipt-title">PAYMENT RECEIPT</div>
        
        <div class="receipt-info">
            <div>
                <strong>Receipt No:</strong> ' . esc_html($transaction['receipt_number']) . '<br>
                <strong>Transaction No:</strong> ' . esc_html($transaction['number']) . '
            </div>
            <div style="text-align: right;">
                <strong>Date:</strong> ' . date('d/m/Y H:i', strtotime($transaction['date'])) . '<br>
                <strong>Status:</strong> <span class="status-badge status-' . esc_attr($transaction['status']) . '">' . esc_html(ucfirst($transaction['status'])) . '</span>
            </div>
        </div>
        
        <div class="info-section">
            <h3>Student Information</h3>
            <div class="info-row">
                <span class="info-label">Student Name:</span>
                <span>' . esc_html($student['name']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Admission Number:</span>
                <span>' . esc_html($student['admission_number']) . '</span>
            </div>';
            
        if (!empty($student['class'])) {
            $html .= '<div class="info-row">
                <span class="info-label">Class:</span>
                <span>' . esc_html($student['class']) . '</span>
            </div>';
        }
        
        $html .= '</div>
        
        <div class="info-section">
            <h3>Payment Details</h3>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span>' . esc_html(ucfirst(str_replace('_', ' ', $transaction['payment_method']))) . '</span>
            </div>';
            
        if (!empty($transaction['gateway'])) {
            $html .= '<div class="info-row">
                <span class="info-label">Payment Gateway:</span>
                <span>' . esc_html(ucfirst($transaction['gateway'])) . '</span>
            </div>';
        }
        
        if (!empty($transaction['gateway_reference'])) {
            $html .= '<div class="info-row">
                <span class="info-label">Reference:</span>
                <span>' . esc_html($transaction['gateway_reference']) . '</span>
            </div>';
        }
        
        if (!empty($invoice['number'])) {
            $html .= '<div class="info-row">
                <span class="info-label">Invoice Number:</span>
                <span>' . esc_html($invoice['number']) . '</span>
            </div>';
        }
        
        $html .= '</div>
        
        <div class="amount-section">
            <div>Amount Paid</div>
            <div class="amount-value">' . esc_html($transaction['currency']) . ' ' . number_format($transaction['amount'], 2) . '</div>
        </div>';
        
        if (!empty($additional['academic_year']) || !empty($additional['term'])) {
            $html .= '<div class="info-section">
                <h3>Academic Information</h3>';
                
            if (!empty($additional['academic_year'])) {
                $html .= '<div class="info-row">
                    <span class="info-label">Academic Year:</span>
                    <span>' . esc_html($additional['academic_year']) . '</span>
                </div>';
            }
            
            if (!empty($additional['term'])) {
                $html .= '<div class="info-row">
                    <span class="info-label">Term:</span>
                    <span>' . esc_html($additional['term']) . '</span>
                </div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '<div class="footer">
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>Generated on: ' . date('d/m/Y H:i', strtotime($additional['generated_date'])) . '</p>';
            
        if (!empty($additional['generated_by'])) {
            $html .= '<p>Generated by: ' . esc_html($additional['generated_by']) . '</p>';
        }
        
        if (!empty($school['website'])) {
            $html .= '<p>Visit us at: <a href="' . esc_url($school['website']) . '">' . esc_html($school['website']) . '</a></p>';
        }
        
        $html .= '</div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Render simple receipt template
     *
     * @param array $data Template data
     * @return string HTML content
     */
    private function render_simple_template($data) {
        $school = $data['school'];
        $transaction = $data['transaction'];
        $student = $data['student'];
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - ' . esc_html($transaction['receipt_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .receipt { max-width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #000; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .amount { font-size: 18px; font-weight: bold; margin: 10px 0; }
        hr { margin: 15px 0; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center bold">' . esc_html($school['name']) . '</div>
        <div class="center">PAYMENT RECEIPT</div>
        <hr>
        <div><strong>Receipt:</strong> ' . esc_html($transaction['receipt_number']) . '</div>
        <div><strong>Date:</strong> ' . date('d/m/Y', strtotime($transaction['date'])) . '</div>
        <div><strong>Student:</strong> ' . esc_html($student['name']) . '</div>
        <div><strong>Admission:</strong> ' . esc_html($student['admission_number']) . '</div>
        <hr>
        <div class="center amount">' . esc_html($transaction['currency']) . ' ' . number_format($transaction['amount'], 2) . '</div>
        <div class="center">(' . esc_html(ucfirst($transaction['payment_method'])) . ')</div>
        <hr>
        <div class="center">Thank you for your payment!</div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Render detailed receipt template
     *
     * @param array $data Template data
     * @return string HTML content
     */
    private function render_detailed_template($data) {
        // For now, use the default template
        // This can be expanded to include more detailed information
        return $this->render_default_template($data);
    }
    
    /**
     * Check if PDF generation is available
     *
     * @return bool
     */
    private function is_pdf_generation_available() {
        // Check if a PDF library is available (e.g., TCPDF, DOMPDF)
        return class_exists('TCPDF') || class_exists('Dompdf\Dompdf');
    }
    
    /**
     * Convert HTML to PDF
     *
     * @param string $html_content HTML content
     * @param array $transaction_data Transaction data
     * @return string|WP_Error PDF file path or error
     */
    private function convert_html_to_pdf($html_content, $transaction_data) {
        // This is a placeholder for PDF generation
        // Implementation would depend on the PDF library being used
        
        if (class_exists('TCPDF')) {
            return $this->generate_pdf_with_tcpdf($html_content, $transaction_data);
        } elseif (class_exists('Dompdf\Dompdf')) {
            return $this->generate_pdf_with_dompdf($html_content, $transaction_data);
        }
        
        return new WP_Error('pdf_library_missing', 'No PDF generation library available');
    }
    
    /**
     * Generate PDF using TCPDF
     *
     * @param string $html_content HTML content
     * @param array $transaction_data Transaction data
     * @return string PDF file path
     */
    private function generate_pdf_with_tcpdf($html_content, $transaction_data) {
        // TCPDF implementation would go here
        // This is a placeholder
        return new WP_Error('not_implemented', 'TCPDF implementation not available');
    }
    
    /**
     * Generate PDF using DOMPDF
     *
     * @param string $html_content HTML content
     * @param array $transaction_data Transaction data
     * @return string PDF file path
     */
    private function generate_pdf_with_dompdf($html_content, $transaction_data) {
        // DOMPDF implementation would go here
        // This is a placeholder
        return new WP_Error('not_implemented', 'DOMPDF implementation not available');
    }
    
    /**
     * Filter template data (hook for customization)
     *
     * @param array $data Template data
     * @param array $transaction_data Original transaction data
     * @return array Filtered data
     */
    public function filter_template_data($data, $transaction_data) {
        return $data;
    }
    
    /**
     * Filter template content (hook for customization)
     *
     * @param string $content Template content
     * @param string $template Template name
     * @param array $data Template data
     * @return string Filtered content
     */
    public function filter_template_content($content, $template, $data) {
        return $content;
    }
    
    /**
     * Get available templates
     *
     * @return array Available templates
     */
    public function get_available_templates() {
        return $this->templates;
    }
    
    /**
     * Register custom template
     *
     * @param string $template_id Template ID
     * @param array $template_config Template configuration
     * @return bool
     */
    public function register_template($template_id, $template_config) {
        if (isset($this->templates[$template_id])) {
            return false; // Template already exists
        }
        
        $this->templates[$template_id] = $template_config;
        return true;
    }
}