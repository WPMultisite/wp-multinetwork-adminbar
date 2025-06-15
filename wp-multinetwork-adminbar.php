<?php
/**
 * Plugin Name: WP Multi Network Admin Bar
 * Plugin URI: https://wpmultinetwork.com
 * Description: Display current network information in admin bar and provide network switching functionality for WP Multi Network environments.
 * Version: 1.0.0
 * Author: WPMultiNetwork.com
 * Author URI: https://wpmultinetwork.com
 * Text Domain: wp-multinetwork-adminbar
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPMN_ADMINBAR_VERSION', '1.0.0');
define('WPMN_ADMINBAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPMN_ADMINBAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPMN_ADMINBAR_TEXT_DOMAIN', 'wp-multinetwork-adminbar');

class WP_MultiNetwork_AdminBar {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Load text domain for translations
        $this->loadTextDomain();
        
        // Check if this is a multi-network environment
        if (!$this->isMultiNetwork()) {
            return;
        }
        
        // Add admin bar items
        add_action('admin_bar_menu', [$this, 'addNetworkInfoToAdminBar'], 100);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Add AJAX handlers
        add_action('wp_ajax_switch_network', [$this, 'handleNetworkSwitch']);
    }
    
    /**
     * Load plugin text domain for translations
     */
    private function loadTextDomain() {
        load_plugin_textdomain(
            WPMN_ADMINBAR_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Check if this is a multi-network environment
     */
    private function isMultiNetwork() {
        return is_multisite() && function_exists('get_networks');
    }
    
    /**
     * Add network information to admin bar
     */
    public function addNetworkInfoToAdminBar($wp_admin_bar) {
        // Only show for users with network management capabilities
        if (!current_user_can('manage_network')) {
            return;
        }
        
        $current_network = $this->getCurrentNetworkInfo();
        $all_networks = $this->getAllNetworks();
        
        if (empty($current_network) || count($all_networks) <= 1) {
            return;
        }
        
        // Add parent menu item
        $wp_admin_bar->add_menu([
            'id' => 'wp-multinetwork-switcher',
            'title' => $this->getNetworkDisplayTitle($current_network),
            'href' => '#',
            'meta' => [
                'class' => 'wp-multinetwork-switcher',
                'title' => sprintf(
                    __('Current Network: %s', WPMN_ADMINBAR_TEXT_DOMAIN),
                    $current_network['domain']
                )
            ]
        ]);
        
        // Add network list submenu
        foreach ($all_networks as $network) {
            $is_current = ($network->id == $current_network['id']);
            $site_count = $this->getNetworkSitesCount($network->id);
            
            $wp_admin_bar->add_menu([
                'parent' => 'wp-multinetwork-switcher',
                'id' => 'network-' . $network->id,
                'title' => $this->getNetworkMenuTitle($network, $is_current, $site_count),
                'href' => $is_current ? '#' : $this->getNetworkSwitchUrl($network),
                'meta' => [
                    'class' => $is_current ? 'current-network' : 'switch-network',
                    'data-network-id' => $network->id,
                    'title' => sprintf(
                        __('Network: %s%s (%d sites)', WPMN_ADMINBAR_TEXT_DOMAIN),
                        $network->domain,
                        $network->path,
                        $site_count
                    )
                ]
            ]);
        }
        
        // Add network admin link
        if (current_user_can('manage_network')) {
            $wp_admin_bar->add_menu([
                'parent' => 'wp-multinetwork-switcher',
                'id' => 'network-admin-all',
                'title' => '🔧 ' . __('Manage All Networks', WPMN_ADMINBAR_TEXT_DOMAIN),
                'href' => network_admin_url(),
                'meta' => [
                    'class' => 'network-admin-link'
                ]
            ]);
        }
    }
    
    /**
     * Get current network information
     */
    private function getCurrentNetworkInfo() {
        $current_network = get_network();
        
        if (!$current_network) {
            return null;
        }
        
        return [
            'id' => $current_network->id,
            'domain' => $current_network->domain,
            'path' => $current_network->path,
            'site_name' => get_network_option($current_network->id, 'site_name', $current_network->domain)
        ];
    }
    
    /**
     * Get all networks
     */
    private function getAllNetworks() {
        if (!function_exists('get_networks')) {
            return [];
        }
        
        return get_networks([
            'number' => 100, // Limit to avoid performance issues
            'orderby' => 'domain'
        ]);
    }
    
    /**
     * Get network sites count with caching
     */
    private function getNetworkSitesCount($network_id) {
        $cache_key = 'wpmn_network_sites_count_' . $network_id;
        $count = wp_cache_get($cache_key);
        
        if (false === $count) {
            $sites = get_sites([
                'network_id' => $network_id,
                'count' => true,
                'number' => 1000 // Reasonable limit
            ]);
            $count = is_numeric($sites) ? intval($sites) : count($sites);
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $count, '', 300);
        }
        
        return $count;
    }
    
    /**
     * Generate network display title (main admin bar menu)
     */
    private function getNetworkDisplayTitle($network_info) {
        $site_name = !empty($network_info['site_name']) ? 
                    $network_info['site_name'] : 
                    $network_info['domain'];
        
        $current_site_count = $this->getNetworkSitesCount($network_info['id']);
        
        return sprintf(
            '<span class="network-indicator">🌐</span> <span class="network-name">%s</span> <span class="network-count">(%d)</span>',
            esc_html($site_name),
            $current_site_count
        );
    }
    
    /**
     * Generate network menu title
     */
    private function getNetworkMenuTitle($network, $is_current, $site_count) {
        $site_name = get_network_option($network->id, 'site_name', $network->domain);
        $indicator = $is_current ? '✓ ' : '';
        
        return sprintf(
            '%s<strong>%s</strong> <span class="site-count">(%s)</span><br><small style="opacity:0.7;">%s%s</small>',
            $indicator,
            esc_html($site_name),
            sprintf(_n('%d site', '%d sites', $site_count, WPMN_ADMINBAR_TEXT_DOMAIN), $site_count),
            esc_html($network->domain),
            $network->path !== '/' ? esc_html($network->path) : ''
        );
    }
    
    /**
     * Generate network switch URL
     */
    private function getNetworkSwitchUrl($network) {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $url = $protocol . $network->domain . $network->path;
        
        // If in admin, try to switch to corresponding admin
        if (is_admin()) {
            $url .= 'wp-admin/';
        }
        
        return $url;
    }
    
    /**
     * Handle network switch AJAX request
     */
    public function handleNetworkSwitch() {
        check_ajax_referer('network_switch_nonce', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_die(__('Insufficient permissions.', WPMN_ADMINBAR_TEXT_DOMAIN));
        }
        
        $network_id = intval($_POST['network_id']);
        $network = get_network($network_id);
        
        if (!$network) {
            wp_send_json_error(__('Network does not exist.', WPMN_ADMINBAR_TEXT_DOMAIN));
        }
        
        $switch_url = $this->getNetworkSwitchUrl($network);
        wp_send_json_success(['redirect_url' => $switch_url]);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets() {
        if (!is_admin_bar_showing() || !current_user_can('manage_network')) {
            return;
        }
        
        // Add inline styles
        $css = $this->getInlineCSS();
        wp_add_inline_style('admin-bar', $css);
        
        // Add inline scripts
        $js = $this->getInlineJS();
        wp_add_inline_script('jquery', $js);
    }
    
    /**
     * Get inline CSS
     */
    private function getInlineCSS() {
        return '
        #wpadminbar .wp-multinetwork-switcher .ab-item {
            padding: 0 10px !important;
        }
        
        #wpadminbar .network-indicator {
            font-size: 16px;
            margin-right: 5px;
        }
        
        #wpadminbar .network-name {
            font-weight: 600;
        }
        
        #wpadminbar .network-count {
            font-size: 12px;
            opacity: 0.8;
            margin-left: 5px;
        }
        
        #wpadminbar .wp-multinetwork-switcher .ab-submenu {
            min-width: 300px;
        }
        
        #wpadminbar .wp-multinetwork-switcher .ab-submenu .ab-item {
            line-height: 1.3;
            padding: 8px 12px !important;
            white-space: normal;
        }
        
        #wpadminbar .current-network .ab-item {
            background-color: rgba(240, 245, 250, 0.1) !important;
            color: #72aee6 !important;
        }
        
        #wpadminbar .switch-network:hover .ab-item {
            background-color: #0073aa !important;
            color: #fff !important;
        }
        
        #wpadminbar .site-count {
            font-size: 11px;
            opacity: 0.8;
            font-weight: normal;
        }
        
        #wpadminbar .network-admin-link {
            border-top: 1px solid rgba(255,255,255,0.2);
            margin-top: 5px;
            padding-top: 5px;
        }
        
        #wpadminbar .network-admin-link .ab-item {
            color: #72aee6 !important;
            font-weight: 600;
        }
        
        /* WordPress 6.8 compatibility */
        @media screen and (max-width: 782px) {
            #wpadminbar .wp-multinetwork-switcher .ab-submenu {
                min-width: 280px;
                left: -120px;
            }
        }
        ';
    }
    
    /**
     * Get inline JavaScript
     */
    private function getInlineJS() {
        $nonce = wp_create_nonce('network_switch_nonce');
        $switching_text = __('Switching...', WPMN_ADMINBAR_TEXT_DOMAIN);
        
        return "
        jQuery(document).ready(function($) {
            // Network switching functionality
            $('.switch-network a').on('click', function(e) {
                var href = $(this).attr('href');
                if (href && href !== '#') {
                    // Add loading indicator
                    var \$item = $(this).closest('.switch-network').find('.ab-item');
                    \$item.append(' <span class=\"switching-indicator\" style=\"opacity:0.7;\">({$switching_text})</span>');
                    
                    // Proceed with navigation
                    window.location.href = href;
                }
            });
            
            // Keyboard shortcut: Ctrl+Shift+N to open network switcher
            $(document).keydown(function(e) {
                if (e.ctrlKey && e.shiftKey && e.keyCode === 78) {
                    e.preventDefault();
                    $('#wp-multinetwork-switcher').trigger('click');
                }
            });
            
            // Add accessibility attributes
            $('#wp-multinetwork-switcher').attr({
                'role': 'menubar',
                'aria-label': '" . esc_js(__('Network Switcher', WPMN_ADMINBAR_TEXT_DOMAIN)) . "'
            });
            
            $('#wp-multinetwork-switcher .ab-submenu').attr('role', 'menu');
            $('#wp-multinetwork-switcher .ab-submenu li').attr('role', 'menuitem');
        });
        ";
    }
}

// Initialize plugin
WP_MultiNetwork_AdminBar::getInstance();

/**
 * Activation hook - Check requirements
 */
register_activation_hook(__FILE__, function() {
    if (!is_multisite()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('This plugin requires WordPress Multisite to be enabled.', WPMN_ADMINBAR_TEXT_DOMAIN),
            __('Plugin Activation Error', WPMN_ADMINBAR_TEXT_DOMAIN),
            ['back_link' => true]
        );
    }
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('This plugin requires WordPress 5.0 or higher.', WPMN_ADMINBAR_TEXT_DOMAIN),
            __('Plugin Activation Error', WPMN_ADMINBAR_TEXT_DOMAIN),
            ['back_link' => true]
        );
    }
});

/**
 * Deactivation hook - Clean up
 */
register_deactivation_hook(__FILE__, function() {
    // Clear any cached data
    wp_cache_flush();
});

/**
 * Utility function: Get network sites count (public API)
 */
function wpmn_get_network_sites_count($network_id) {
    if (!is_multisite() || !function_exists('get_sites')) {
        return 0;
    }
    
    $sites = get_sites([
        'network_id' => $network_id,
        'count' => true,
        'number' => 1000
    ]);
    
    return is_numeric($sites) ? intval($sites) : count($sites);
}

/**
 * Utility function: Check if user can access network
 */
function wpmn_user_can_access_network($user_id, $network_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Check if user is super admin
    if (is_super_admin($user_id)) {
        return true;
    }
    
    // Check if user has access to any sites in this network
    $sites = get_sites([
        'network_id' => $network_id, 
        'number' => 100
    ]);
    
    foreach ($sites as $site) {
        if (is_user_member_of_blog($user_id, $site->blog_id)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Utility function: Get network display name
 */
function wpmn_get_network_display_name($network_id) {
    $network = get_network($network_id);
    if (!$network) {
        return '';
    }
    
    $site_name = get_network_option($network_id, 'site_name');
    return !empty($site_name) ? $site_name : $network->domain;
}