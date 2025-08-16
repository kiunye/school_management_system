<?php
/**
 * Payment Gateway Selector
 *
 * Implements intelligent gateway selection logic and fallback mechanisms
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
 * Gateway Selector Class
 */
class SMS_Gateway_Selector {
    
    /**
     * Gateway manager instance
     *
     * @var SMS_Payment_Gateway_Manager
     */
    private $gateway_manager;
    
    /**
     * Selection criteria weights
     *
     * @var array
     */
    private $selection_weights = array(
        'availability' => 40,
        'success_rate' => 30,
        'response_time' => 20,
        'cost' => 10
    );
    
    /**
     * Constructor
     *
     * @param SMS_Payment_Gateway_Manager $gateway_manager
     */
    public function __construct($gateway_manager) {
        $this->gateway_manager = $gateway_manager;
    }
    
    /**
     * Select best gateway for payment
     *
     * @param array $criteria Selection criteria
     * @return string|WP_Error Selected gateway ID
     */
    public function select_best_gateway($criteria = array()) {
        $available_gateways = $this->gateway_manager->get_gateways(true);
        
        if (empty($available_gateways)) {
            return new WP_Error('no_gateways', 'No payment gateways available');
        }
        
        // If user has a preference and it's available, use it
        if (isset($criteria['preferred_gateway'])) {
            $preferred = $criteria['preferred_gateway'];
            if (isset($available_gateways[$preferred])) {
                return $preferred;
            }
        }
        
        // Score each gateway
        $gateway_scores = array();
        foreach ($available_gateways as $gateway_id => $gateway) {
            $score = $this->calculate_gateway_score($gateway_id, $criteria);
            if ($score > 0) {
                $gateway_scores[$gateway_id] = $score;
            }
        }
        
        if (empty($gateway_scores)) {
            return new WP_Error('no_suitable_gateway', 'No suitable payment gateway found');
        }
        
        // Return gateway with highest score
        arsort($gateway_scores);
        return array_key_first($gateway_scores);
    }
    
    /**
     * Calculate gateway score based on criteria
     *
     * @param string $gateway_id Gateway identifier
     * @param array $criteria Selection criteria
     * @return float Gateway score
     */
    private function calculate_gateway_score($gateway_id, $criteria) {
        $score = 0;
        
        // Availability score
        $availability_score = $this->get_availability_score($gateway_id, $criteria);
        $score += $availability_score * ($this->selection_weights['availability'] / 100);
        
        // Success rate score
        $success_rate_score = $this->get_success_rate_score($gateway_id);
        $score += $success_rate_score * ($this->selection_weights['success_rate'] / 100);
        
        // Response time score
        $response_time_score = $this->get_response_time_score($gateway_id);
        $score += $response_time_score * ($this->selection_weights['response_time'] / 100);
        
        // Cost score
        $cost_score = $this->get_cost_score($gateway_id, $criteria);
        $score += $cost_score * ($this->selection_weights['cost'] / 100);
        
        return $score;
    }
    
    /**
     * Get availability score for gateway
     *
     * @param string $gateway_id Gateway identifier
     * @param array $criteria Selection criteria
     * @return float Availability score (0-100)
     */
    private function get_availability_score($gateway_id, $criteria) {
        $gateway = $this->gateway_manager->get_gateway($gateway_id);
        
        if (!$gateway || !$gateway->is_enabled()) {
            return 0;
        }
        
        // Check phone number compatibility
        if (isset($criteria['phone_number'])) {
            $phone_compatibility = $this->check_phone_compatibility($gateway_id, $criteria['phone_number']);
            if (!$phone_compatibility) {
                return 0;
            }
        }
        
        // Check amount limits
        if (isset($criteria['amount'])) {
            $amount_compatibility = $this->check_amount_limits($gateway_id, $criteria['amount']);
            if (!$amount_compatibility) {
                return 0;
            }
        }
        
        // Check service status
        $service_status = $this->get_service_status($gateway_id);
        
        return $service_status;
    }
    
    /**
     * Check phone number compatibility with gateway
     *
     * @param string $gateway_id Gateway identifier
     * @param string $phone_number Phone number
     * @return bool
     */
    private function check_phone_compatibility($gateway_id, $phone_number) {
        // Normalize phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone_number);
        
        switch ($gateway_id) {
            case 'mpesa':
                // M-Pesa works with Safaricom numbers (07xx)
                return preg_match('/^(\+254|254|0)?7[0-9]{8}$/', $phone);
                
            case 'airtel_money':
                // Airtel Money works with Airtel numbers (01xx, 07xx for some)
                return preg_match('/^(\+254|254|0)?(1[0-9]{8}|73[0-9]{7}|78[0-9]{7})$/', $phone);
                
            case 'equity_bank':
                // Equity Bank supports all Kenyan numbers
                return preg_match('/^(\+254|254|0)?[17][0-9]{8}$/', $phone);
                
            default:
                return true; // Generic gateways accept all numbers
        }
    }
    
    /**
     * Check amount limits for gateway
     *
     * @param string $gateway_id Gateway identifier
     * @param float $amount Payment amount
     * @return bool
     */
    private function check_amount_limits($gateway_id, $amount) {
        $limits = $this->get_gateway_limits($gateway_id);
        
        return $amount >= $limits['min_amount'] && $amount <= $limits['max_amount'];
    }
    
    /**
     * Get gateway transaction limits
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    private function get_gateway_limits($gateway_id) {
        $default_limits = array(
            'min_amount' => 1,
            'max_amount' => 1000000
        );
        
        $gateway_limits = array(
            'mpesa' => array(
                'min_amount' => 1,
                'max_amount' => 300000 // M-Pesa daily limit
            ),
            'airtel_money' => array(
                'min_amount' => 1,
                'max_amount' => 500000 // Airtel Money daily limit
            ),
            'equity_bank' => array(
                'min_amount' => 1,
                'max_amount' => 1000000 // Bank transfer limit
            )
        );
        
        return isset($gateway_limits[$gateway_id]) ? $gateway_limits[$gateway_id] : $default_limits;
    }
    
    /**
     * Get service status score
     *
     * @param string $gateway_id Gateway identifier
     * @return float Status score (0-100)
     */
    private function get_service_status($gateway_id) {
        // Check recent service status
        $status_key = "sms_gateway_status_{$gateway_id}";
        $status = get_transient($status_key);
        
        if ($status === false) {
            // No cached status, assume available
            return 100;
        }
        
        return $status['available'] ? 100 : 0;
    }
    
    /**
     * Get success rate score
     *
     * @param string $gateway_id Gateway identifier
     * @return float Success rate score (0-100)
     */
    private function get_success_rate_score($gateway_id) {
        // Get success rate from last 30 days
        $stats = $this->get_gateway_stats($gateway_id, 30);
        
        if ($stats['total_transactions'] == 0) {
            return 50; // Neutral score for new gateways
        }
        
        return ($stats['successful_transactions'] / $stats['total_transactions']) * 100;
    }
    
    /**
     * Get response time score
     *
     * @param string $gateway_id Gateway identifier
     * @return float Response time score (0-100)
     */
    private function get_response_time_score($gateway_id) {
        $stats = $this->get_gateway_stats($gateway_id, 7);
        
        if ($stats['avg_response_time'] == 0) {
            return 50; // Neutral score
        }
        
        // Score based on response time (lower is better)
        // 0-5 seconds = 100, 5-10 seconds = 80, 10-30 seconds = 60, >30 seconds = 20
        $response_time = $stats['avg_response_time'];
        
        if ($response_time <= 5) {
            return 100;
        } elseif ($response_time <= 10) {
            return 80;
        } elseif ($response_time <= 30) {
            return 60;
        } else {
            return 20;
        }
    }
    
    /**
     * Get cost score
     *
     * @param string $gateway_id Gateway identifier
     * @param array $criteria Selection criteria
     * @return float Cost score (0-100)
     */
    private function get_cost_score($gateway_id, $criteria) {
        $amount = isset($criteria['amount']) ? $criteria['amount'] : 1000;
        $cost = $this->calculate_gateway_cost($gateway_id, $amount);
        
        // Get costs for all gateways to compare
        $all_costs = array();
        foreach ($this->gateway_manager->get_gateways(true) as $gw_id => $gateway) {
            $all_costs[$gw_id] = $this->calculate_gateway_cost($gw_id, $amount);
        }
        
        if (empty($all_costs)) {
            return 50;
        }
        
        $min_cost = min($all_costs);
        $max_cost = max($all_costs);
        
        if ($max_cost == $min_cost) {
            return 100; // All gateways have same cost
        }
        
        // Invert score (lower cost = higher score)
        return 100 - (($cost - $min_cost) / ($max_cost - $min_cost)) * 100;
    }
    
    /**
     * Calculate gateway cost for transaction
     *
     * @param string $gateway_id Gateway identifier
     * @param float $amount Transaction amount
     * @return float Transaction cost
     */
    private function calculate_gateway_cost($gateway_id, $amount) {
        $cost_structures = array(
            'mpesa' => array(
                'type' => 'tiered',
                'tiers' => array(
                    array('min' => 1, 'max' => 100, 'cost' => 0),
                    array('min' => 101, 'max' => 500, 'cost' => 7),
                    array('min' => 501, 'max' => 1000, 'cost' => 13),
                    array('min' => 1001, 'max' => 1500, 'cost' => 23),
                    array('min' => 1501, 'max' => 2500, 'cost' => 33),
                    array('min' => 2501, 'max' => 3500, 'cost' => 53),
                    array('min' => 3501, 'max' => 5000, 'cost' => 57),
                    array('min' => 5001, 'max' => 7500, 'cost' => 78),
                    array('min' => 7501, 'max' => 10000, 'cost' => 90)
                )
            ),
            'airtel_money' => array(
                'type' => 'percentage',
                'rate' => 0.015, // 1.5%
                'min_fee' => 5,
                'max_fee' => 100
            ),
            'equity_bank' => array(
                'type' => 'flat',
                'cost' => 25
            ),
            'cash' => array(
                'type' => 'flat',
                'cost' => 0
            )
        );
        
        if (!isset($cost_structures[$gateway_id])) {
            return 0;
        }
        
        $structure = $cost_structures[$gateway_id];
        
        switch ($structure['type']) {
            case 'tiered':
                foreach ($structure['tiers'] as $tier) {
                    if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                        return $tier['cost'];
                    }
                }
                return 0;
                
            case 'percentage':
                $fee = $amount * $structure['rate'];
                return max($structure['min_fee'], min($fee, $structure['max_fee']));
                
            case 'flat':
            default:
                return $structure['cost'];
        }
    }
    
    /**
     * Get gateway statistics
     *
     * @param string $gateway_id Gateway identifier
     * @param int $days Number of days to look back
     * @return array
     */
    private function get_gateway_stats($gateway_id, $days = 30) {
        // This would typically query a statistics table
        // For now, return mock data
        return array(
            'total_transactions' => 100,
            'successful_transactions' => 95,
            'failed_transactions' => 5,
            'avg_response_time' => 3.5
        );
    }
    
    /**
     * Get fallback gateway order
     *
     * @param string $primary_gateway Primary gateway that failed
     * @param array $criteria Selection criteria
     * @return array Ordered list of fallback gateways
     */
    public function get_fallback_order($primary_gateway, $criteria = array()) {
        $available_gateways = $this->gateway_manager->get_gateways(true);
        unset($available_gateways[$primary_gateway]); // Remove failed gateway
        
        if (empty($available_gateways)) {
            return array();
        }
        
        // Score remaining gateways
        $gateway_scores = array();
        foreach ($available_gateways as $gateway_id => $gateway) {
            $score = $this->calculate_gateway_score($gateway_id, $criteria);
            if ($score > 0) {
                $gateway_scores[$gateway_id] = $score;
            }
        }
        
        // Sort by score (highest first)
        arsort($gateway_scores);
        
        return array_keys($gateway_scores);
    }
    
    /**
     * Update gateway status
     *
     * @param string $gateway_id Gateway identifier
     * @param bool $available Whether gateway is available
     * @param string $message Status message
     */
    public function update_gateway_status($gateway_id, $available, $message = '') {
        $status_key = "sms_gateway_status_{$gateway_id}";
        $status = array(
            'available' => $available,
            'message' => $message,
            'updated' => current_time('timestamp')
        );
        
        set_transient($status_key, $status, HOUR_IN_SECONDS);
        
        do_action('sms_gateway_status_updated', $gateway_id, $status);
    }
}