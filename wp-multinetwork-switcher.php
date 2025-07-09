<?php
/**
 * Plugin Name: WP Multi-Network Switcher
 * Plugin URI: https://wpmultinetwork.com
 * Description: Display current network information in admin bar and provide network switching functionality for WP Multi Network environments.
 * Version: 1.0.2
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

define("WPMN_ADMINBAR_VERSION", "1.0.2");
define("WPMN_ADMINBAR_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("WPMN_ADMINBAR_PLUGIN_URL", plugin_dir_url(__FILE__));
define("WPMN_ADMINBAR_TEXT_DOMAIN", "wp-multinetwork-switcher");

class WP_MultiNetwork_AdminBar
{
    private static $instance = null;
    private $networks_cache = [];
    private $admin_color_scheme = "fresh";

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

        $this->detectAdminColorScheme();

        add_action("admin_bar_menu", [$this, "addNetworkInfoToAdminBar"], 100);
        add_action("admin_enqueue_scripts", [$this, "enqueueAdminAssets"]);
        add_action("wp_enqueue_scripts", [$this, "enqueueAdminAssets"]);
        add_action("wp_ajax_switch_network", [$this, "handleNetworkSwitch"]);
        add_action("wp_ajax_get_network_info", [$this, "getNetworkInfo"]);
        add_action("admin_head", [$this, "addAdminHeadStyles"]);
        add_action("wp_head", [$this, "addAdminHeadStyles"]);
    }

    private function loadTextDomain()
    {
        load_plugin_textdomain(
            WPMN_ADMINBAR_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . "/languages"
        );
    }

    private function isMultiNetwork()
    {
        return is_multisite() && function_exists("get_networks");
    }

    private function detectAdminColorScheme()
    {
        $current_user = wp_get_current_user();
        if ($current_user) {
            $user_color_scheme = get_user_meta(
                $current_user->ID,
                "admin_color",
                true
            );
            $this->admin_color_scheme = $user_color_scheme
                ? $user_color_scheme
                : "fresh";
        }
    }

    private function getColorSchemeColors()
    {
        $schemes = [
            "fresh" => [
                "primary" => "#0073aa",
                "secondary" => "#72aee6",
                "hover" => "#005a87",
                "background" => "rgba(240, 245, 250, 0.1)",
            ],
            "light" => [
                "primary" => "#0073aa",
                "secondary" => "#72aee6",
                "hover" => "#005a87",
                "background" => "rgba(240, 245, 250, 0.1)",
            ],
            "blue" => [
                "primary" => "#096484",
                "secondary" => "#4796b3",
                "hover" => "#07526c",
                "background" => "rgba(70, 150, 179, 0.1)",
            ],
            "midnight" => [
                "primary" => "#e14d43",
                "secondary" => "#77a6b9",
                "hover" => "#dd382d",
                "background" => "rgba(119, 166, 185, 0.1)",
            ],
            "sunrise" => [
                "primary" => "#d1864a",
                "secondary" => "#c8b03c",
                "hover" => "#b77729",
                "background" => "rgba(200, 176, 60, 0.1)",
            ],
            "ectoplasm" => [
                "primary" => "#a3b745",
                "secondary" => "#c8d035",
                "hover" => "#8b9a3e",
                "background" => "rgba(200, 208, 53, 0.1)",
            ],
            "ocean" => [
                "primary" => "#627c83",
                "secondary" => "#9ebaa0",
                "hover" => "#576e74",
                "background" => "rgba(158, 186, 160, 0.1)",
            ],
            "coffee" => [
                "primary" => "#c7a589",
                "secondary" => "#9ea476",
                "hover" => "#b79570",
                "background" => "rgba(158, 164, 118, 0.1)",
            ],
        ];

        return isset($schemes[$this->admin_color_scheme])
            ? $schemes[$this->admin_color_scheme]
            : $schemes["fresh"];
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
                    __("Current Network: %s", WPMN_ADMINBAR_TEXT_DOMAIN),
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
                            WPMN_ADMINBAR_TEXT_DOMAIN
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
            '%s<strong>%s</strong> <span class="site-count">(%s)</span>',
            $indicator,
            esc_html($site_name),
            sprintf(
                _n(
                    "%d site",
                    "%d sites",
                    $site_count,
                    WPMN_ADMINBAR_TEXT_DOMAIN
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
            __("Manage All Networks", WPMN_ADMINBAR_TEXT_DOMAIN)
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
            wp_send_json_error(__("Invalid nonce.", WPMN_ADMINBAR_TEXT_DOMAIN));
        }

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions.", WPMN_ADMINBAR_TEXT_DOMAIN)
            );
        }

        $network_id = intval($_POST["network_id"]);
        $network = get_network($network_id);

        if (!$network) {
            wp_send_json_error(
                __("Network does not exist.", WPMN_ADMINBAR_TEXT_DOMAIN)
            );
        }

        if (!$this->userCanAccessNetwork(get_current_user_id(), $network_id)) {
            wp_send_json_error(
                __("Access denied to this network.", WPMN_ADMINBAR_TEXT_DOMAIN)
            );
        }

        $switch_url = $this->getNetworkSwitchUrl($network);
        wp_send_json_success(["redirect_url" => $switch_url]);
    }

    public function getNetworkInfo()
    {
        if (!check_ajax_referer("network_switch_nonce", "nonce", false)) {
            wp_send_json_error(__("Invalid nonce.", WPMN_ADMINBAR_TEXT_DOMAIN));
        }

        if (!current_user_can("manage_network")) {
            wp_send_json_error(
                __("Insufficient permissions.", WPMN_ADMINBAR_TEXT_DOMAIN)
            );
        }

        $network_id = intval($_POST["network_id"]);
        $network = get_network($network_id);

        if (!$network) {
            wp_send_json_error(
                __("Network does not exist.", WPMN_ADMINBAR_TEXT_DOMAIN)
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
        $colors = $this->getColorSchemeColors();

        return sprintf(
            '
        #wpadminbar .wp-multinetwork-switcher .ab-item {
            padding: 0 10px !important;
        }

        #wpadminbar .network-indicator[data-dashicon]:before {
            content: "\\f319";
            font-family: dashicons;
            font-size: 16px;
            margin-right: 5px;
            vertical-align: middle;
        }

        #wpadminbar [data-dashicon="yes"]:before {
            content: "\\f147";
            font-family: dashicons;
            color: %s;
            margin-right: 5px;
        }

        #wpadminbar [data-dashicon="admin-tools"]:before {
            content: "\\f348";
            font-family: dashicons;
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
            background-color: %s !important;
            color: %s !important;
        }

        #wpadminbar .switch-network:hover .ab-item {
            background-color: %s !important;
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
            color: %s !important;
            font-weight: 600;
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
        ',
            $colors["secondary"],
            $colors["background"],
            $colors["secondary"],
            $colors["hover"],
            $colors["secondary"]
        );
    }

    private function getInlineJS()
    {
        $ajax_url = admin_url("admin-ajax.php");
        $switching_text = __("Switching...", WPMN_ADMINBAR_TEXT_DOMAIN);
        $error_text = __(
            "Error switching network. Please try again.",
            WPMN_ADMINBAR_TEXT_DOMAIN
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
            esc_js(__("Network Switcher", WPMN_ADMINBAR_TEXT_DOMAIN))
        );
    }
}

WP_MultiNetwork_AdminBar::getInstance();

register_activation_hook(__FILE__, function () {
    if (!is_multisite()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "This plugin requires WordPress Multisite to be enabled.",
                WPMN_ADMINBAR_TEXT_DOMAIN
            ),
            __("Plugin Activation Error", WPMN_ADMINBAR_TEXT_DOMAIN),
            ["back_link" => true]
        );
    }

    global $wp_version;
    if (version_compare($wp_version, "5.0", "<")) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "This plugin requires WordPress 5.0 or higher.",
                WPMN_ADMINBAR_TEXT_DOMAIN
            ),
            __("Plugin Activation Error", WPMN_ADMINBAR_TEXT_DOMAIN),
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
