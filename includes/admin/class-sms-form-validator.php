<?php
/**
 * Form Validation Class
 * Handles server-side form validation with user-friendly error messages
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * SMS Form Validator Class
 */
class SMS_Form_Validator extends SMS_Base {

    /**
     * Validation errors
     */
    private $errors = array();
    
    /**
     * Validation rules
     */
    private $rules = array();
    
    /**
     * Custom error messages
     */
    private $messages = array();
    
    /**
     * Form data
     */
    private $data = array();

    /**
     * Initialize the validator
     */
    public function __construct($data = array()) {
        parent::__construct();
        $this->data = $data;
        $this->setup_default_messages();
    }

    /**
     * Setup default error messages
     */
    private function setup_default_messages() {
        $this->messages = array(
            'required' => __('This field is required.', 'school-management-system'),
            'email' => __('Please enter a valid email address.', 'school-management-system'),
            'phone' => __('Please enter a valid phone number.', 'school-management-system'),
            'kenyan_phone' => __('Please enter a valid Kenyan phone number (e.g., 0712345678).', 'school-management-system'),
            'numeric' => __('This field must be a number.', 'school-management-system'),
            'integer' => __('This field must be a whole number.', 'school-management-system'),
            'min' => __('This field must be at least {min}.', 'school-management-system'),
            'max' => __('This field must be no more than {max}.', 'school-management-system'),
            'min_length' => __('This field must be at least {min} characters long.', 'school-management-system'),
            'max_length' => __('This field must be no more than {max} characters long.', 'school-management-system'),
            'unique' => __('This value already exists. Please choose a different one.', 'school-management-system'),
            'exists' => __('The selected value is invalid.', 'school-management-system'),
            'date' => __('Please enter a valid date.', 'school-management-system'),
            'date_format' => __('Please enter a date in the format: {format}.', 'school-management-system'),
            'before' => __('This date must be before {date}.', 'school-management-system'),
            'after' => __('This date must be after {date}.', 'school-management-system'),
            'url' => __('Please enter a valid URL.', 'school-management-system'),
            'alpha' => __('This field may only contain letters.', 'school-management-system'),
            'alpha_numeric' => __('This field may only contain letters and numbers.', 'school-management-system'),
            'alpha_dash' => __('This field may only contain letters, numbers, dashes, and underscores.', 'school-management-system'),
            'confirmed' => __('The confirmation does not match.', 'school-management-system'),
            'same' => __('This field must match {field}.', 'school-management-system'),
            'different' => __('This field must be different from {field}.', 'school-management-system'),
            'in' => __('The selected value is invalid.', 'school-management-system'),
            'not_in' => __('The selected value is not allowed.', 'school-management-system'),
            'file' => __('Please select a valid file.', 'school-management-system'),
            'image' => __('Please select a valid image file.', 'school-management-system'),
            'mimes' => __('Please select a file of type: {types}.', 'school-management-system'),
            'max_file_size' => __('The file size must be less than {size}.', 'school-management-system')
        );
    }

    /**
     * Set validation rules
     */
    public function rules($rules) {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Set custom messages
     */
    public function messages($messages) {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    /**
     * Validate the form data
     */
    public function validate() {
        $this->errors = array();
        
        foreach ($this->rules as $field => $rules) {
            $this->validate_field($field, $rules);
        }
        
        return empty($this->errors);
    }

    /**
     * Validate a single field
     */
    private function validate_field($field, $rules) {
        $value = isset($this->data[$field]) ? $this->data[$field] : '';
        $rules_array = is_string($rules) ? explode('|', $rules) : $rules;
        
        foreach ($rules_array as $rule) {
            $this->apply_rule($field, $value, $rule);
        }
    }

    /**
     * Apply a validation rule
     */
    private function apply_rule($field, $value, $rule) {
        $rule_parts = explode(':', $rule);
        $rule_name = $rule_parts[0];
        $parameters = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : array();
        
        $method = 'validate_' . $rule_name;
        
        if (method_exists($this, $method)) {
            $result = call_user_func_array(array($this, $method), array($field, $value, $parameters));
            
            if ($result !== true) {
                $this->add_error($field, $result);
            }
        }
    }

    /**
     * Add validation error
     */
    private function add_error($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = array();
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     */
    public function get_field_errors($field) {
        return isset($this->errors[$field]) ? $this->errors[$field] : array();
    }

    /**
     * Check if validation failed
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * Get formatted error message
     */
    private function get_message($rule, $field, $parameters = array()) {
        $message = isset($this->messages[$rule]) ? $this->messages[$rule] : 'Validation failed.';
        
        // Replace placeholders
        $message = str_replace('{field}', $this->get_field_name($field), $message);
        
        foreach ($parameters as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        
        return $message;
    }

    /**
     * Get human-readable field name
     */
    private function get_field_name($field) {
        return ucwords(str_replace('_', ' ', $field));
    }

    // Validation Rules

    /**
     * Required validation
     */
    protected function validate_required($field, $value, $parameters) {
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            return $this->get_message('required', $field);
        }
        
        return true;
    }

    /**
     * Email validation
     */
    protected function validate_email($field, $value, $parameters) {
        if ($value && !is_email($value)) {
            return $this->get_message('email', $field);
        }
        
        return true;
    }

    /**
     * Phone validation
     */
    protected function validate_phone($field, $value, $parameters) {
        if ($value && !preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $value)) {
            return $this->get_message('phone', $field);
        }
        
        return true;
    }

    /**
     * Kenyan phone validation
     */
    protected function validate_kenyan_phone($field, $value, $parameters) {
        if ($value) {
            $cleaned = preg_replace('/\s/', '', $value);
            if (!preg_match('/^(\+254|254|0)[17]\d{8}$/', $cleaned)) {
                return $this->get_message('kenyan_phone', $field);
            }
        }
        
        return true;
    }

    /**
     * Numeric validation
     */
    protected function validate_numeric($field, $value, $parameters) {
        if ($value && !is_numeric($value)) {
            return $this->get_message('numeric', $field);
        }
        
        return true;
    }

    /**
     * Integer validation
     */
    protected function validate_integer($field, $value, $parameters) {
        if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
            return $this->get_message('integer', $field);
        }
        
        return true;
    }

    /**
     * Minimum value validation
     */
    protected function validate_min($field, $value, $parameters) {
        if ($value && is_numeric($value) && floatval($value) < floatval($parameters[0])) {
            return $this->get_message('min', $field, array('min' => $parameters[0]));
        }
        
        return true;
    }

    /**
     * Maximum value validation
     */
    protected function validate_max($field, $value, $parameters) {
        if ($value && is_numeric($value) && floatval($value) > floatval($parameters[0])) {
            return $this->get_message('max', $field, array('max' => $parameters[0]));
        }
        
        return true;
    }

    /**
     * Minimum length validation
     */
    protected function validate_min_length($field, $value, $parameters) {
        if ($value && strlen($value) < intval($parameters[0])) {
            return $this->get_message('min_length', $field, array('min' => $parameters[0]));
        }
        
        return true;
    }

    /**
     * Maximum length validation
     */
    protected function validate_max_length($field, $value, $parameters) {
        if ($value && strlen($value) > intval($parameters[0])) {
            return $this->get_message('max_length', $field, array('max' => $parameters[0]));
        }
        
        return true;
    }

    /**
     * Unique validation (checks database)
     */
    protected function validate_unique($field, $value, $parameters) {
        if (!$value) return true;
        
        global $wpdb;
        
        $table = $parameters[0];
        $column = isset($parameters[1]) ? $parameters[1] : $field;
        $except_id = isset($parameters[2]) ? $parameters[2] : null;
        
        $query = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = %s", $value);
        
        if ($except_id) {
            $query .= $wpdb->prepare(" AND id != %d", $except_id);
        }
        
        $count = $wpdb->get_var($query);
        
        if ($count > 0) {
            return $this->get_message('unique', $field);
        }
        
        return true;
    }

    /**
     * Exists validation (checks if value exists in database)
     */
    protected function validate_exists($field, $value, $parameters) {
        if (!$value) return true;
        
        global $wpdb;
        
        $table = $parameters[0];
        $column = isset($parameters[1]) ? $parameters[1] : $field;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        ));
        
        if ($count == 0) {
            return $this->get_message('exists', $field);
        }
        
        return true;
    }

    /**
     * Date validation
     */
    protected function validate_date($field, $value, $parameters) {
        if ($value && !strtotime($value)) {
            return $this->get_message('date', $field);
        }
        
        return true;
    }

    /**
     * Date format validation
     */
    protected function validate_date_format($field, $value, $parameters) {
        if ($value) {
            $format = $parameters[0];
            $date = DateTime::createFromFormat($format, $value);
            
            if (!$date || $date->format($format) !== $value) {
                return $this->get_message('date_format', $field, array('format' => $format));
            }
        }
        
        return true;
    }

    /**
     * Before date validation
     */
    protected function validate_before($field, $value, $parameters) {
        if ($value) {
            $before_date = $parameters[0];
            
            if (strtotime($value) >= strtotime($before_date)) {
                return $this->get_message('before', $field, array('date' => $before_date));
            }
        }
        
        return true;
    }

    /**
     * After date validation
     */
    protected function validate_after($field, $value, $parameters) {
        if ($value) {
            $after_date = $parameters[0];
            
            if (strtotime($value) <= strtotime($after_date)) {
                return $this->get_message('after', $field, array('date' => $after_date));
            }
        }
        
        return true;
    }

    /**
     * URL validation
     */
    protected function validate_url($field, $value, $parameters) {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->get_message('url', $field);
        }
        
        return true;
    }

    /**
     * Alpha validation (letters only)
     */
    protected function validate_alpha($field, $value, $parameters) {
        if ($value && !preg_match('/^[a-zA-Z]+$/', $value)) {
            return $this->get_message('alpha', $field);
        }
        
        return true;
    }

    /**
     * Alpha numeric validation
     */
    protected function validate_alpha_numeric($field, $value, $parameters) {
        if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            return $this->get_message('alpha_numeric', $field);
        }
        
        return true;
    }

    /**
     * Alpha dash validation (letters, numbers, dashes, underscores)
     */
    protected function validate_alpha_dash($field, $value, $parameters) {
        if ($value && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            return $this->get_message('alpha_dash', $field);
        }
        
        return true;
    }

    /**
     * Confirmed validation (password confirmation)
     */
    protected function validate_confirmed($field, $value, $parameters) {
        $confirmation_field = $field . '_confirmation';
        $confirmation_value = isset($this->data[$confirmation_field]) ? $this->data[$confirmation_field] : '';
        
        if ($value !== $confirmation_value) {
            return $this->get_message('confirmed', $field);
        }
        
        return true;
    }

    /**
     * Same validation (field must match another field)
     */
    protected function validate_same($field, $value, $parameters) {
        $other_field = $parameters[0];
        $other_value = isset($this->data[$other_field]) ? $this->data[$other_field] : '';
        
        if ($value !== $other_value) {
            return $this->get_message('same', $field, array('field' => $this->get_field_name($other_field)));
        }
        
        return true;
    }

    /**
     * Different validation (field must be different from another field)
     */
    protected function validate_different($field, $value, $parameters) {
        $other_field = $parameters[0];
        $other_value = isset($this->data[$other_field]) ? $this->data[$other_field] : '';
        
        if ($value === $other_value) {
            return $this->get_message('different', $field, array('field' => $this->get_field_name($other_field)));
        }
        
        return true;
    }

    /**
     * In validation (value must be in list)
     */
    protected function validate_in($field, $value, $parameters) {
        if ($value && !in_array($value, $parameters)) {
            return $this->get_message('in', $field);
        }
        
        return true;
    }

    /**
     * Not in validation (value must not be in list)
     */
    protected function validate_not_in($field, $value, $parameters) {
        if ($value && in_array($value, $parameters)) {
            return $this->get_message('not_in', $field);
        }
        
        return true;
    }

    /**
     * File validation
     */
    protected function validate_file($field, $value, $parameters) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return $this->get_message('file', $field);
        }
        
        return true;
    }

    /**
     * Image validation
     */
    protected function validate_image($field, $value, $parameters) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            
            if (!in_array($_FILES[$field]['type'], $allowed_types)) {
                return $this->get_message('image', $field);
            }
        }
        
        return true;
    }

    /**
     * MIME types validation
     */
    protected function validate_mimes($field, $value, $parameters) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file_type = $_FILES[$field]['type'];
            $allowed_types = $parameters;
            
            if (!in_array($file_type, $allowed_types)) {
                return $this->get_message('mimes', $field, array('types' => implode(', ', $allowed_types)));
            }
        }
        
        return true;
    }

    /**
     * Maximum file size validation
     */
    protected function validate_max_file_size($field, $value, $parameters) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $max_size = $this->parse_size($parameters[0]);
            
            if ($_FILES[$field]['size'] > $max_size) {
                return $this->get_message('max_file_size', $field, array('size' => $parameters[0]));
            }
        }
        
        return true;
    }

    /**
     * Parse size string (e.g., "2MB" to bytes)
     */
    private function parse_size($size) {
        $units = array('B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824);
        
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT]?B)$/i', $size, $matches)) {
            return floatval($matches[1]) * $units[strtoupper($matches[2])];
        }
        
        return intval($size);
    }

    /**
     * Render validation errors for display
     */
    public function render_errors($field = null) {
        if ($field) {
            $errors = $this->get_field_errors($field);
            if (!empty($errors)) {
                echo '<div class="validation-errors">';
                foreach ($errors as $error) {
                    echo '<span class="validation-error">' . esc_html($error) . '</span>';
                }
                echo '</div>';
            }
        } else {
            if (!empty($this->errors)) {
                echo '<div class="error-summary">';
                echo '<h4>' . __('Please correct the following errors:', 'school-management-system') . '</h4>';
                echo '<ul>';
                foreach ($this->errors as $field => $field_errors) {
                    foreach ($field_errors as $error) {
                        echo '<li>' . esc_html($error) . '</li>';
                    }
                }
                echo '</ul>';
                echo '</div>';
            }
        }
    }
}