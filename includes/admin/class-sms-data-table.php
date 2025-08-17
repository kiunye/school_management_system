<?php
/**
 * Responsive Data Table Class
 * Handles data tables with sorting, filtering, and pagination
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * SMS Data Table Class
 */
class SMS_Data_Table extends SMS_Base {

    /**
     * Table configuration
     */
    private $config;
    
    /**
     * Table data
     */
    private $data;
    
    /**
     * Total records
     */
    private $total_records;
    
    /**
     * Filtered records
     */
    private $filtered_records;

    /**
     * Initialize the data table
     */
    public function __construct($config = array()) {
        parent::__construct();
        
        $this->config = wp_parse_args($config, array(
            'id' => 'sms-data-table',
            'class' => 'sms-responsive-table',
            'per_page' => 20,
            'show_pagination' => true,
            'show_search' => true,
            'show_filters' => true,
            'show_export' => true,
            'sortable' => true,
            'responsive' => true,
            'ajax' => false,
            'columns' => array(),
            'actions' => array(),
            'bulk_actions' => array()
        ));
        
        // Register AJAX handlers
        if ($this->config['ajax']) {
            add_action('wp_ajax_sms_datatable_data', array($this, 'ajax_get_data'));
            add_action('wp_ajax_sms_datatable_action', array($this, 'ajax_handle_action'));
        }
    }

    /**
     * Set table data
     */
    public function set_data($data, $total_records = null) {
        $this->data = $data;
        $this->total_records = $total_records !== null ? $total_records : count($data);
        $this->filtered_records = $this->total_records;
    }

    /**
     * Render the complete data table
     */
    public function render() {
        $table_id = $this->config['id'];
        $table_class = $this->config['class'];
        
        ob_start();
        ?>
        <div class="sms-datatable-wrapper" id="<?php echo esc_attr($table_id); ?>-wrapper">
            
            <!-- Table Controls -->
            <div class="sms-datatable-controls">
                <div class="controls-left">
                    <?php if ($this->config['show_search']): ?>
                        <div class="search-box">
                            <input type="text" 
                                   id="<?php echo esc_attr($table_id); ?>-search" 
                                   class="sms-table-search" 
                                   placeholder="<?php _e('Search...', 'school-management-system'); ?>">
                            <span class="search-icon dashicons dashicons-search"></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($this->config['show_filters']): ?>
                        <div class="filter-controls">
                            <?php $this->render_filters(); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="controls-right">
                    <?php if ($this->config['show_export']): ?>
                        <div class="export-controls">
                            <button type="button" class="button sms-export-btn" data-format="csv">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Export CSV', 'school-management-system'); ?>
                            </button>
                            <button type="button" class="button sms-export-btn" data-format="excel">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php _e('Export Excel', 'school-management-system'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="view-controls">
                        <button type="button" class="button view-toggle active" data-view="table">
                            <span class="dashicons dashicons-list-view"></span>
                        </button>
                        <button type="button" class="button view-toggle" data-view="cards">
                            <span class="dashicons dashicons-grid-view"></span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <?php if (!empty($this->config['bulk_actions'])): ?>
                <div class="sms-bulk-actions">
                    <select id="<?php echo esc_attr($table_id); ?>-bulk-action">
                        <option value=""><?php _e('Bulk Actions', 'school-management-system'); ?></option>
                        <?php foreach ($this->config['bulk_actions'] as $action => $label): ?>
                            <option value="<?php echo esc_attr($action); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button sms-bulk-apply">
                        <?php _e('Apply', 'school-management-system'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Loading Indicator -->
            <div class="sms-table-loading" style="display: none;">
                <div class="loading-spinner">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php _e('Loading...', 'school-management-system'); ?>
                </div>
            </div>
            
            <!-- Table View -->
            <div class="table-view active">
                <div class="table-responsive">
                    <table class="<?php echo esc_attr($table_class); ?>" id="<?php echo esc_attr($table_id); ?>">
                        <?php $this->render_table_header(); ?>
                        <tbody>
                            <?php $this->render_table_body(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Cards View -->
            <div class="cards-view">
                <div class="sms-cards-grid">
                    <?php $this->render_cards_view(); ?>
                </div>
            </div>
            
            <!-- Table Info and Pagination -->
            <div class="sms-datatable-footer">
                <div class="table-info">
                    <span class="showing-entries">
                        <?php printf(
                            __('Showing %d to %d of %d entries', 'school-management-system'),
                            '<span class="start-entry">1</span>',
                            '<span class="end-entry">' . min($this->config['per_page'], count($this->data)) . '</span>',
                            '<span class="total-entries">' . $this->total_records . '</span>'
                        ); ?>
                    </span>
                    <?php if ($this->filtered_records !== $this->total_records): ?>
                        <span class="filtered-info">
                            <?php printf(__('(filtered from %d total entries)', 'school-management-system'), $this->total_records); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($this->config['show_pagination']): ?>
                    <div class="table-pagination">
                        <?php $this->render_pagination(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }

    /**
     * Render table header
     */
    private function render_table_header() {
        ?>
        <thead>
            <tr>
                <?php if (!empty($this->config['bulk_actions'])): ?>
                    <th class="check-column">
                        <input type="checkbox" class="select-all">
                    </th>
                <?php endif; ?>
                
                <?php foreach ($this->config['columns'] as $column_key => $column): ?>
                    <th class="column-<?php echo esc_attr($column_key); ?> <?php echo isset($column['sortable']) && $column['sortable'] ? 'sortable' : ''; ?>"
                        data-column="<?php echo esc_attr($column_key); ?>">
                        <?php echo esc_html($column['title']); ?>
                        <?php if (isset($column['sortable']) && $column['sortable']): ?>
                            <span class="sort-indicator">
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </span>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                
                <?php if (!empty($this->config['actions'])): ?>
                    <th class="actions-column"><?php _e('Actions', 'school-management-system'); ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <?php
    }

    /**
     * Render table body
     */
    private function render_table_body() {
        if (empty($this->data)) {
            $colspan = count($this->config['columns']);
            if (!empty($this->config['bulk_actions'])) $colspan++;
            if (!empty($this->config['actions'])) $colspan++;
            
            ?>
            <tr class="no-items">
                <td colspan="<?php echo $colspan; ?>" class="no-items-message">
                    <?php _e('No items found.', 'school-management-system'); ?>
                </td>
            </tr>
            <?php
            return;
        }

        foreach ($this->data as $row_index => $row_data) {
            $this->render_table_row($row_data, $row_index);
        }
    }

    /**
     * Render single table row
     */
    private function render_table_row($row_data, $row_index) {
        $row_id = isset($row_data['id']) ? $row_data['id'] : $row_index;
        ?>
        <tr data-row-id="<?php echo esc_attr($row_id); ?>">
            <?php if (!empty($this->config['bulk_actions'])): ?>
                <td class="check-column">
                    <input type="checkbox" class="row-selector" value="<?php echo esc_attr($row_id); ?>">
                </td>
            <?php endif; ?>
            
            <?php foreach ($this->config['columns'] as $column_key => $column): ?>
                <td class="column-<?php echo esc_attr($column_key); ?>" data-label="<?php echo esc_attr($column['title']); ?>">
                    <?php echo $this->render_cell_content($row_data, $column_key, $column); ?>
                </td>
            <?php endforeach; ?>
            
            <?php if (!empty($this->config['actions'])): ?>
                <td class="actions-column">
                    <div class="row-actions">
                        <?php $this->render_row_actions($row_data, $row_id); ?>
                    </div>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    }

    /**
     * Render cell content
     */
    private function render_cell_content($row_data, $column_key, $column) {
        $value = isset($row_data[$column_key]) ? $row_data[$column_key] : '';
        
        // Apply column formatter if available
        if (isset($column['formatter']) && is_callable($column['formatter'])) {
            return call_user_func($column['formatter'], $value, $row_data, $column_key);
        }
        
        // Apply built-in formatters
        if (isset($column['type'])) {
            switch ($column['type']) {
                case 'date':
                    return $value ? date_i18n(get_option('date_format'), strtotime($value)) : '';
                    
                case 'datetime':
                    return $value ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($value)) : '';
                    
                case 'currency':
                    return 'KES ' . number_format(floatval($value), 2);
                    
                case 'status':
                    return '<span class="status-badge status-' . esc_attr(strtolower($value)) . '">' . esc_html(ucfirst($value)) . '</span>';
                    
                case 'image':
                    return $value ? '<img src="' . esc_url($value) . '" alt="" class="table-image">' : '';
                    
                case 'link':
                    $url = isset($column['url']) ? $column['url'] : '#';
                    if (is_callable($column['url'])) {
                        $url = call_user_func($column['url'], $row_data);
                    }
                    return '<a href="' . esc_url($url) . '">' . esc_html($value) . '</a>';
                    
                default:
                    return esc_html($value);
            }
        }
        
        return esc_html($value);
    }

    /**
     * Render row actions
     */
    private function render_row_actions($row_data, $row_id) {
        foreach ($this->config['actions'] as $action_key => $action) {
            $url = isset($action['url']) ? $action['url'] : '#';
            if (is_callable($action['url'])) {
                $url = call_user_func($action['url'], $row_data);
            }
            
            $class = isset($action['class']) ? $action['class'] : '';
            $title = isset($action['title']) ? $action['title'] : $action['label'];
            
            ?>
            <a href="<?php echo esc_url($url); ?>" 
               class="row-action <?php echo esc_attr($class); ?>" 
               data-action="<?php echo esc_attr($action_key); ?>"
               data-row-id="<?php echo esc_attr($row_id); ?>"
               title="<?php echo esc_attr($title); ?>">
                <?php if (isset($action['icon'])): ?>
                    <span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"></span>
                <?php endif; ?>
                <span class="action-text"><?php echo esc_html($action['label']); ?></span>
            </a>
            <?php
        }
    }

    /**
     * Render cards view
     */
    private function render_cards_view() {
        if (empty($this->data)) {
            ?>
            <div class="no-items-card">
                <div class="empty-state">
                    <span class="dashicons dashicons-admin-post"></span>
                    <h3><?php _e('No items found', 'school-management-system'); ?></h3>
                    <p><?php _e('Try adjusting your search or filter criteria.', 'school-management-system'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        foreach ($this->data as $row_index => $row_data) {
            $this->render_card_item($row_data, $row_index);
        }
    }

    /**
     * Render single card item
     */
    private function render_card_item($row_data, $row_index) {
        $row_id = isset($row_data['id']) ? $row_data['id'] : $row_index;
        ?>
        <div class="sms-card-item" data-row-id="<?php echo esc_attr($row_id); ?>">
            <?php if (!empty($this->config['bulk_actions'])): ?>
                <div class="card-selector">
                    <input type="checkbox" class="row-selector" value="<?php echo esc_attr($row_id); ?>">
                </div>
            <?php endif; ?>
            
            <div class="card-content">
                <?php
                // Render primary columns in card format
                $primary_columns = array_slice($this->config['columns'], 0, 3, true);
                foreach ($primary_columns as $column_key => $column):
                ?>
                    <div class="card-field">
                        <label><?php echo esc_html($column['title']); ?>:</label>
                        <span><?php echo $this->render_cell_content($row_data, $column_key, $column); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($this->config['actions'])): ?>
                <div class="card-actions">
                    <?php $this->render_row_actions($row_data, $row_id); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render filters
     */
    private function render_filters() {
        // This would be implemented based on specific filter requirements
        ?>
        <select class="sms-table-filter" data-filter="status">
            <option value=""><?php _e('All Status', 'school-management-system'); ?></option>
            <option value="active"><?php _e('Active', 'school-management-system'); ?></option>
            <option value="inactive"><?php _e('Inactive', 'school-management-system'); ?></option>
        </select>
        <?php
    }

    /**
     * Render pagination
     */
    private function render_pagination() {
        $current_page = 1;
        $total_pages = ceil($this->total_records / $this->config['per_page']);
        
        if ($total_pages <= 1) {
            return;
        }
        
        ?>
        <div class="pagination-wrapper">
            <button type="button" class="pagination-btn first" data-page="1" <?php disabled($current_page, 1); ?>>
                <span class="dashicons dashicons-controls-skipback"></span>
            </button>
            <button type="button" class="pagination-btn prev" data-page="<?php echo max(1, $current_page - 1); ?>" <?php disabled($current_page, 1); ?>>
                <span class="dashicons dashicons-controls-back"></span>
            </button>
            
            <div class="pagination-info">
                <input type="number" class="current-page" value="<?php echo $current_page; ?>" min="1" max="<?php echo $total_pages; ?>">
                <span class="pagination-separator"><?php _e('of', 'school-management-system'); ?></span>
                <span class="total-pages"><?php echo $total_pages; ?></span>
            </div>
            
            <button type="button" class="pagination-btn next" data-page="<?php echo min($total_pages, $current_page + 1); ?>" <?php disabled($current_page, $total_pages); ?>>
                <span class="dashicons dashicons-controls-forward"></span>
            </button>
            <button type="button" class="pagination-btn last" data-page="<?php echo $total_pages; ?>" <?php disabled($current_page, $total_pages); ?>>
                <span class="dashicons dashicons-controls-skipforward"></span>
            </button>
        </div>
        <?php
    }

    /**
     * AJAX handler for getting table data
     */
    public function ajax_get_data() {
        check_ajax_referer('sms_admin_nonce', 'nonce');
        
        $page = intval($_POST['page']);
        $per_page = intval($_POST['per_page']);
        $search = sanitize_text_field($_POST['search']);
        $sort_column = sanitize_text_field($_POST['sort_column']);
        $sort_direction = sanitize_text_field($_POST['sort_direction']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // This would be implemented to fetch data based on parameters
        $data = array();
        $total_records = 0;
        
        wp_send_json_success(array(
            'data' => $data,
            'total_records' => $total_records,
            'filtered_records' => count($data)
        ));
    }

    /**
     * AJAX handler for table actions
     */
    public function ajax_handle_action() {
        check_ajax_referer('sms_admin_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type']);
        $row_ids = array_map('intval', $_POST['row_ids']);
        
        // Handle the action
        $result = $this->handle_table_action($action, $row_ids);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Handle table actions
     */
    private function handle_table_action($action, $row_ids) {
        // This would be implemented based on specific action requirements
        return array('message' => 'Action completed successfully');
    }
}