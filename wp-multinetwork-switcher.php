<?php
/**
 * Plugin Name: WP Multi-Network Switcher
 * Plugin URI: https://wpmultinetwork.com
 * Description: Display current network information in admin bar and provide network switching functionality for WP Multi Network environments.
 * Version: 1.0.3
 * Author: WPMultiNetwork.com
 * Author URI: https://wpmultinetwork.com
 * Text Domain: wp-multinetwork-switcher
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined("ABSPATH")) {
    exit();
}

define("WPMN_SWITCHER_VERSION", "1.0.3");
define("WPMN_SWITCHER_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("WPMN_SWITCHER_PLUGIN_URL", plugin_dir_url(__FILE__));

class WP_MultiNetwork_Switcher
{
    private static $instance = null;
    private $networks_cache = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action("plugins_loaded", [$this, "init"]);
    }

    public function init()
    {
        $this->loadTextDomain();

        if (!$this->isMultiNetwork()) {
            return;
        }

        add_action("admin_bar_menu", [$this, "addNetworkInfoToAdminBar"], 100);
        add_action("admin_enqueue_scripts", [$this, "enqueueAdminAssets"]);
        add_action("wp_enqueue_scripts", [$this, "enqueueAdminAssets"]);
        add_action("wp_ajax_switch_network", [$this, "handleNetworkSwitch"]);
        add_action("wp_ajax_get_network_info", [$this, "getNetworkInfo"]);
        add_action("admin_head", [$this, "addAdminHeadStyles"]);
        add_action("wp_head", [$this, "addAdminHeadStyles"]);
        add_action("wp_dashboard_setup", [$this, "addDashboardWidget"]);
        add_action("wp_network_dashboard_setup", [$this, "addDashboardWidget"]);
        add_action("admin_enqueue_scripts", [$this, "enqueueDashboardAssets"]);
        add_action("admin_print_styles", [$this, "printDashboardStyles"]);
    }

    private function loadTextDomain()
    {
        load_plugin_textdomain(
            "wp-multinetwork-switcher",
            false,
            dirname(plugin_basename(__FILE__)) . "/languages"
        );
    }

    private function isMultiNetwork()
    {
        return is_multisite() && function_exists("get_networks");
    }

    public function addNetworkInfoToAdminBar($wp_admin_bar)
    {
        if (!current_user_can("manage_network")) {
            return;
        }

        $current_network = $this->getCurrentNetworkInfo();
        $all_networks = $this->getAllNetworks();

        if (empty($current_network) || count($all_networks) <= 1) {
            return;
        }

        $wp_admin_bar->add_menu([
            "id" => "wp-multinetwork-switcher",
            "title" => $this->getNetworkDisplayTitle($current_network),
            "href" => "#",
            "meta" => [
                "class" => "wp-multinetwork-switcher",
                "title" => sprintf(
                    __("Current Network: %s", "wp-multinetwork-switcher"),
                    $current_network["domain"]
                ),
            ],
        ]);

        foreach ($all_networks as $network) {
            $is_current = $network->id == $current_network["id"];
            $site_count = $this->getNetworkSitesCount($network->id);

            $wp_admin_bar->add_menu([
                "parent" => "wp-multinetwork-switcher",
                "id" => "network-" . $network->id,
                "title" => $this->getNetworkMenuTitle(
                    $network,
                    $is_current,
                    $site_count
                ),
                "href" => $is_current
                    ? "#"
                    : $this->getNetworkSwitchUrl($network),
                "meta" => [
                    "class" => $is_current
                        ? "current-network"
                        : "switch-network",
                    "data-network-id" => $network->id,
                    "data-nonce" => wp_create_nonce("network_switch_nonce"),
                    "title" => sprintf(
                        __(
                            "Network: %s%s (%d sites)",
                            "wp-multinetwork-switcher"
                        ),
                        $network->domain,
                        $network->path,
                        $site_count
                    ),
                ],
            ]);
        }

        if (current_user_can("manage_network")) {
            $wp_admin_bar->add_menu([
                "parent" => "wp-multinetwork-switcher",
                "id" => "network-admin-all",
                "title" => $this->getNetworkAdminLinkTitle(),
                "href" => network_admin_url(),
                "meta" => [
                    "class" => "network-admin-link",
                ],
            ]);
        }
    }

    public function addDashboardWidget()
    {
        if (!current_user_can("manage_network")) {
            return;
        }

        wp_add_dashboard_widget(
            "wpmn_network_dashboard",
            __("Multi Network Overview", "wp-multinetwork-switcher"),
            [$this, "renderDashboardWidget"]
        );
    }

    public function renderDashboardWidget()
    {
        $all_networks = $this->getAllNetworks();
        $current_network = $this->getCurrentNetworkInfo();
        $total_sites = 0;

        foreach ($all_networks as $network) {
            $total_sites += $this->getNetworkSitesCount($network->id);
        }

        $super_admin_count = $this->getSuperAdminCount();

        echo '<div class="wpmn-dashboard-widget">';

        echo '<div class="wpmn-stats-row">';
        echo '<div class="wpmn-stat-item">';
        echo '<div class="wpmn-stat-number">' . count($all_networks) . "</div>";
        echo '<div class="wpmn-stat-label">' .
            __("Total Networks", "wp-multinetwork-switcher") .
            "</div>";
        echo "</div>";

        echo '<div class="wpmn-stat-item">';
        echo '<div class="wpmn-stat-number">' . $total_sites . "</div>";
        echo '<div class="wpmn-stat-label">' .
            __("Total Sites", "wp-multinetwork-switcher") .
            "</div>";
        echo "</div>";

        echo '<div class="wpmn-stat-item">';
        echo '<div class="wpmn-stat-number">' . $super_admin_count . "</div>";
        echo '<div class="wpmn-stat-label">' .
            __("Super Admins", "wp-multinetwork-switcher") .
            "</div>";
        echo "</div>";
        echo "</div>";
        echo "<h4>" .
            __("Current Network", "wp-multinetwork-switcher") .
            "</h4>";
        if ($current_network) {
            $favicon_url = $this->getNetworkFavicon($current_network["id"]);
            echo '<div class="wpmn-current-network">';
            echo '<div class="wpmn-current-network-info">';
            if ($favicon_url) {
                echo '<img src="' .
                    esc_url($favicon_url) .
                    '" alt="' .
                    esc_attr($current_network["site_name"]) .
                    '" class="wpmn-network-favicon" />';
            }
            echo '<div class="wpmn-current-network-details">';
            echo "<p>" . esc_html($current_network["site_name"]) . "</p>";
            echo "<p><small>" .
                esc_html(
                    $current_network["domain"] . $current_network["path"]
                ) .
                "</small></p>";
            echo "<p>" .
                sprintf(
                    __("%d sites", "wp-multinetwork-switcher"),
                    $this->getNetworkSitesCount($current_network["id"])
                ) .
                "</p>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        if (count($all_networks) > 1) {
            echo '<div class="wpmn-network-list">';
            echo "<h4>" .
                __("Quick Switch", "wp-multinetwork-switcher") .
                "</h4>";
            echo '<div class="wpmn-network-grid">';

            $display_networks = array_slice($all_networks, 0, 5);

            foreach ($display_networks as $network) {
                $is_current =
                    $current_network && $network->id == $current_network["id"];
                $site_count = $this->getNetworkSitesCount($network->id);
                $site_name = get_network_option(
                    $network->id,
                    "site_name",
                    $network->domain
                );

                echo '<div class="wpmn-network-item' .
                    ($is_current ? " current" : "") .
                    '">';
                if (!$is_current) {
                    echo '<a href="' .
                        esc_url($this->getNetworkSwitchUrl($network)) .
                        '" class="wpmn-switch-link" data-network-id="' .
                        $network->id .
                        '">';
                }
                echo '<div class="wpmn-network-name">' .
                    esc_html($site_name) .
                    "</div>";
                echo '<div class="wpmn-network-info">';
                echo '<span class="wpmn-network-domain">' .
                    esc_html($network->domain) .
                    "</span>";
                echo '<span class="wpmn-network-count">' .
                    sprintf(
                        __("%d sites", "wp-multinetwork-switcher"),
                        $site_count
                    ) .
                    "</span>";
                echo "</div>";
                if (!$is_current) {
                    echo "</a>";
                }
                echo "</div>";
            }

            if (count($all_networks) > 6) {
                echo '<div class="wpmn-network-more">';
                echo '<span class="wpmn-more-text">' .
                    sprintf(
                        __("+ %d more networks", "wp-multinetwork-switcher"),
                        count($all_networks) - 6
                    ) .
                    "</span>";
                echo "</div>";
            }

            echo "</div>";
            echo "</div>";
        }

        echo '<div class="wpmn-actions">';
        echo '<a href="' .
            esc_url(network_admin_url()) .
            '" class="button button-primary">' .
            __("Manage Networks", "wp-multinetwork-switcher") .
            "</a>";
        echo "</div>";

        echo "</div>";
    }

    public function printDashboardStyles()
    {
        $screen = get_current_screen();
        if (!$screen || !current_user_can("manage_network")) {
            return;
        }

        if (
            $screen->id === "dashboard" ||
            $screen->id === "dashboard-network"
        ) {
            echo '<style type="text/css">' .
                $this->getDashboardCSS() .
                "</style>";
        }
    }

    private function getSuperAdminCount()
    {
        $cache_key = "wpmn_super_admin_count";
        $count = wp_cache_get($cache_key);

        if (false === $count) {
            $super_admins = get_super_admins();
            $count = count($super_admins);
            wp_cache_set($cache_key, $count, "", 300);
        }

        return $count;
    }

    private function getNetworkFavicon($network_id)
    {
        $cache_key = "wpmn_network_favicon_" . $network_id;
        $favicon_url = wp_cache_get($cache_key);

        if (false === $favicon_url) {
            $main_site_id = get_main_site_id($network_id);

            if ($main_site_id) {
                switch_to_blog($main_site_id);
                $favicon_url = get_site_icon_url(32);

                if (!$favicon_url) {
                    $favicon_url =
                        get_template_directory_uri() . "/favicon.ico";
                    if (!@file_get_contents($favicon_url)) {
                        $favicon_url = admin_url("images/wordpress-logo.svg");
                    }
                }

                restore_current_blog();
            } else {
                $favicon_url = admin_url("images/wordpress-logo.svg");
            }

            wp_cache_set($cache_key, $favicon_url, "", 300);
        }

        return $favicon_url;
    }

    public function enqueueDashboardAssets()
    {
        $screen = get_current_screen();
        if (!$screen || !current_user_can("manage_network")) {
            return;
        }

        if (
            $screen->id === "dashboard" ||
            $screen->id === "dashboard-network"
        ) {
            wp_enqueue_script("jquery");
            wp_add_inline_script("jquery", $this->getDashboardJS());
        }
    }

    private function getDashboardCSS()
    {
        return "
        .wpmn-dashboard-widget {
            padding: 16px;
        }

        .wpmn-stats-row {
            display: flex;
            margin-bottom: 20px;
            gap: 15px;
        }

        .wpmn-stat-item {
            flex: 1;
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .wpmn-stat-number {
            font-size: 20px;
            font-weight: 600;
            line-height: 1.2;
        }

        .wpmn-stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .wpmn-current-network {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .wpmn-current-network h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }

        .wpmn-current-network-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .wpmn-network-favicon {
            width: 45px;
            height: 45px;
            border-radius: 4px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #c3c4c7;
            background: #ffffff;
            padding: 2%;
        }

        .wpmn-current-network-details p {
            margin: 5px 0;
        }

        .wpmn-network-list h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .wpmn-network-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .wpmn-network-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            background: #fff;
            transition: all 0.2s ease;
        }

        .wpmn-network-item.current {
            border-color: #0073aa;
            background: rgba(240, 245, 250, 0.1);
        }

        .wpmn-network-item a {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .wpmn-network-item:not(.current):hover {
            border-color: #72aee6;
            background: #f8f9fa;
        }

        .wpmn-network-name {
            font-weight: 400;
            margin-bottom: 5px;
            color: #333;
        }

        .wpmn-network-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #666;
        }

        .wpmn-network-domain {
            font-family: monospace;
        }

        .wpmn-network-count {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #e8f5e8;
            color: #2e7d32;
        }

        .wpmn-network-more {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #ddd;
            border-radius: 4px;
            padding: 12px;
            color: #666;
            font-size: 12px;
            font-style: italic;
            background: #fafafa;
        }

        .wpmn-actions {
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        @media (max-width: 600px) {
            .wpmn-stats-row {
                flex-direction: column;
                gap: 10px;
            }

            .wpmn-network-grid {
                grid-template-columns: 1fr;
            }

            .wpmn-current-network-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
        ";
    }

    private function getDashboardJS()
    {
        return "
        jQuery(document).ready(function($) {
            $('.wpmn-switch-link').on('click', function(e) {
                e.preventDefault();
                var link = $(this);
                var networkId = link.data('network-id');

                if (confirm('" .
            esc_js(__("Switch to this network?", "wp-multinetwork-switcher")) .
            "')) {
                    window.location.href = link.attr('href');
                }
            });
        });
        ";
    }

    private function getCurrentNetworkInfo()
    {
        $current_network = get_network();

        if (!$current_network) {
            return null;
        }

        return [
            "id" => $current_network->id,
            "domain" => $current_network->domain,
            "path" => $current_network->path,
            "site_name" => get_network_option(
                $current_network->id,
                "site_name",
                $current_network->domain
            ),
        ];
    }

    private function getAllNetworks()
    {
        if (!empty($this->networks_cache)) {
            return $this->networks_cache;
        }

        if (!function_exists("get_networks")) {
            return [];
        }

        $this->networks_cache = get_networks([
            "number" => 100,
            "orderby" => "domain",
        ]);

        return $this->networks_cache;
    }

    private function getNetworkSitesCount($network_id)
    {
        $cache_key = "wpmn_network_sites_count_" . $network_id;
        $count = wp_cache_get($cache_key);

        if (false === $count) {
            $sites = get_sites([
                "network_id" => $network_id,
                "count" => true,
                "number" => 1000,
            ]);
            $count = is_numeric($sites) ? intval($sites) : count($sites);

            wp_cache_set($cache_key, $count, "", 300);
        }

        return $count;
    }

    private function getNetworkDisplayTitle($network_info)
    {
        $site_name = !empty($network_info["site_name"])
            ? $network_info["site_name"]
            : $network_info["domain"];

        $current_site_count = $this->getNetworkSitesCount($network_info["id"]);

        return sprintf(
            "%s %s %s",
            $this->getDashicon("admin-site-alt3", "network-indicator"),
            sprintf(
                '<span class="network-name">%s</span>',
                esc_html($site_name)
            ),
            sprintf(
                '<span class="network-count">(%d)</span>',
                $current_site_count
            )
        );
    }

    private function getNetworkMenuTitle($network, $is_current, $site_count)
    {
        $site_name = get_network_option(
            $network->id,
            "site_name",
            $network->domain
        );

        $indicator = $is_current ? $this->getDashicon("yes") . " " : "";

        return sprintf(
            '%s %s <span class="site-count">(%s)</span>',
            $indicator,
            esc_html($site_name),
            sprintf(
                _n(
                    "%d site",
                    "%d sites",
                    $site_count,
                    "wp-multinetwork-switcher"
                ),
                $site_count
            )
        );
    }

    private function getNetworkAdminLinkTitle()
    {
        return sprintf(
            "%s %s",
            $this->getDashicon("admin-tools"),
            __("Manage All Networks", "wp-multinetwork-switcher")
        );
    }

    private function getDashicon($icon, $class = "")
    {
        $class_attr = $class ? sprintf(' class="%s"', esc_attr($class)) : "";
        return sprintf(
            '<span%s data-dashicon="%s"></span>',
            $class_attr,
            esc_attr($icon)
        );
    }

    private function getNetworkSwitchUrl($network)
    {
        $protocol = is_ssl() ? "https://" : "http://";
        $url = $protocol . $network->domain . $network->path;

        if (is_admin()) {
            $url .= "wp-admin/";
        }

        return $url;
    }

    public function handleNetworkSwitch()
    {
        if (!check_ajax_referer("network_switch_nonce", "nonce", false)) {
            wp_send_json_error(
                __("Invalid nonce.", "wp-multinetwork-switcher")
            );
        }

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions.", "wp-multinetwork-switcher")
            );
        }

        $network_id = intval($_POST["network_id"]);
        $network = get_network($network_id);

        if (!$network) {
            wp_send_json_error(
                __("Network does not exist.", "wp-multinetwork-switcher")
            );
        }

        if (!$this->userCanAccessNetwork(get_current_user_id(), $network_id)) {
            wp_send_json_error(
                __("Access denied to this network.", "wp-multinetwork-switcher")
            );
        }

        $switch_url = $this->getNetworkSwitchUrl($network);
        wp_send_json_success(["redirect_url" => $switch_url]);
    }

    public function getNetworkInfo()
    {
        if (!check_ajax_referer("network_switch_nonce", "nonce", false)) {
            wp_send_json_error(
                __("Invalid nonce.", "wp-multinetwork-switcher")
            );
        }

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions.", "wp-multinetwork-switcher")
            );
        }

        $network_id = intval($_POST["network_id"]);
        $network = get_network($network_id);

        if (!$network) {
            wp_send_json_error(
                __("Network does not exist.", "wp-multinetwork-switcher")
            );
        }

        $site_count = $this->getNetworkSitesCount($network_id);
        $site_name = get_network_option(
            $network_id,
            "site_name",
            $network->domain
        );

        wp_send_json_success([
            "id" => $network->id,
            "domain" => $network->domain,
            "path" => $network->path,
            "site_name" => $site_name,
            "site_count" => $site_count,
        ]);
    }

    private function userCanAccessNetwork($user_id, $network_id)
    {
        if (is_super_admin($user_id)) {
            return true;
        }

        $sites = get_sites([
            "network_id" => $network_id,
            "number" => 100,
        ]);

        foreach ($sites as $site) {
            if (is_user_member_of_blog($user_id, $site->blog_id)) {
                return true;
            }
        }

        return false;
    }

    public function enqueueAdminAssets()
    {
        if (!is_admin_bar_showing() || !current_user_can("manage_network")) {
            return;
        }

        wp_enqueue_script("jquery");
        wp_enqueue_style("dashicons");

        wp_add_inline_script("jquery", $this->getInlineJS());
    }

    public function addAdminHeadStyles()
    {
        if (!is_admin_bar_showing() || !current_user_can("manage_network")) {
            return;
        }

        echo '<style type="text/css">' . $this->getInlineCSS() . "</style>";
    }

    private function getInlineCSS()
    {
        return '
        #wpadminbar .wp-multinetwork-switcher .ab-item {
            padding: 0 10px !important;
        }

        #wpadminbar .network-indicator[data-dashicon]:before {
            content: "\f230";
            position: relative;
            float: left;
            font: normal 20px / 1 dashicons;
            speak: never;
            padding: 6px 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-image: none !important;
            margin-right: 6px;
            color: #9ca2a7;
        }

        #wpadminbar [data-dashicon="yes"]:before {
            content: "\\f147";
            font-family: dashicons;
            color: #72aee6;
            margin-right: 5px;
        }

        #wpadminbar [data-dashicon="admin-tools"]:before {
            content: "\\f348";
            font-family: dashicons;
            margin-right: 5px;
        }

        #wpadminbar .network-name {
            font-weight: 400;
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
            padding: 5px 12px !important;
            white-space: normal;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #wpadminbar .current-network .ab-item {
            background-color: rgba(240, 245, 250, 0.1) !important;
            color: #72aee6 !important;
        }

        #wpadminbar .switch-network:hover .ab-item {
            background-color: #1d2327 !important;
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
            font-weight: 400;
        }

        #wpadminbar .switching-indicator {
            font-size: 11px;
            opacity: 0.7;
            font-style: italic;
        }

        @media screen and (max-width: 782px) {
            #wpadminbar .wp-multinetwork-switcher .ab-submenu {
                min-width: 280px;
                left: -120px;
            }
        }
        ';
    }

    private function getInlineJS()
    {
        $ajax_url = admin_url("admin-ajax.php");
        $switching_text = __("Switching...", "wp-multinetwork-switcher");
        $error_text = __(
            "Error switching network. Please try again.",
            "wp-multinetwork-switcher"
        );

        return sprintf(
            "
        jQuery(document).ready(function($) {
            var switchingInProgress = false;

            $('.switch-network a').on('click', function(e) {
                e.preventDefault();

                if (switchingInProgress) {
                    return false;
                }

                var \$link = $(this);
                var \$item = \$link.closest('.switch-network');
                var networkId = \$item.data('network-id');
                var nonce = \$item.data('nonce');
                var originalHref = \$link.attr('href');

                if (!networkId || !nonce) {
                    window.location.href = originalHref;
                    return;
                }

                switchingInProgress = true;
                \$link.append(' <span class=\"switching-indicator\">(%s)</span>');

                $.ajax({
                    url: '%s',
                    type: 'POST',
                    data: {
                        action: 'switch_network',
                        network_id: networkId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert(response.data || '%s');
                            switchingInProgress = false;
                            \$link.find('.switching-indicator').remove();
                        }
                    },
                    error: function() {
                        alert('%s');
                        switchingInProgress = false;
                        \$link.find('.switching-indicator').remove();
                    }
                });
            });

            $(document).keydown(function(e) {
                if (e.ctrlKey && e.shiftKey && e.keyCode === 78) {
                    e.preventDefault();
                    var \$switcher = $('#wp-multinetwork-switcher');
                    if (\$switcher.length) {
                        \$switcher.trigger('click');
                    }
                }
            });

            $('#wp-multinetwork-switcher').attr({
                'role': 'menubar',
                'aria-label': '%s'
            });

            $('#wp-multinetwork-switcher .ab-submenu').attr('role', 'menu');
            $('#wp-multinetwork-switcher .ab-submenu li').attr('role', 'menuitem');
        });
        ",
            $switching_text,
            $ajax_url,
            $error_text,
            $error_text,
            esc_js(__("Network Switcher", "wp-multinetwork-switcher"))
        );
    }
}

WP_MultiNetwork_Switcher::getInstance();

register_activation_hook(__FILE__, function () {
    if (!is_multisite()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "This plugin requires WordPress Multisite to be enabled.",
                "wp-multinetwork-switcher"
            ),
            __("Plugin Activation Error", "wp-multinetwork-switcher"),
            ["back_link" => true]
        );
    }

    global $wp_version;
    if (version_compare($wp_version, "5.0", "<")) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "This plugin requires WordPress 5.0 or higher.",
                "wp-multinetwork-switcher"
            ),
            __("Plugin Activation Error", "wp-multinetwork-switcher"),
            ["back_link" => true]
        );
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_cache_flush();
});

function wpmn_get_network_sites_count($network_id)
{
    if (!is_multisite() || !function_exists("get_sites")) {
        return 0;
    }

    $sites = get_sites([
        "network_id" => $network_id,
        "count" => true,
        "number" => 1000,
    ]);

    return is_numeric($sites) ? intval($sites) : count($sites);
}

function wpmn_user_can_access_network($user_id, $network_id)
{
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    if (is_super_admin($user_id)) {
        return true;
    }

    $sites = get_sites([
        "network_id" => $network_id,
        "number" => 100,
    ]);

    foreach ($sites as $site) {
        if (is_user_member_of_blog($user_id, $site->blog_id)) {
            return true;
        }
    }

    return false;
}

function wpmn_get_network_display_name($network_id)
{
    $network = get_network($network_id);
    if (!$network) {
        return "";
    }

    $site_name = get_network_option($network_id, "site_name");
    return !empty($site_name) ? $site_name : $network->domain;
}
