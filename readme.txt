=== WP Multi Network Switcher ===
Contributors: wpmultinetwork
Tags: multisite, multi-network, admin bar, network switching, network management
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true

Display current network information in admin bar and provide seamless network switching functionality for WP Multi Network environments.

== Description ==

**WP Multi Network Switcher** enhances the WordPress admin experience for Multi Network environments by adding intelligent network identification and switching capabilities directly to the admin bar.

= Key Features =

* **Network Identification** - Clearly displays the current network name and site count in the admin bar
* **Quick Network Switching** - One-click switching between different networks with dropdown menu
* **Network Statistics** - Shows the number of sites in each network for better overview
* **Smart Navigation** - Maintains admin context when switching between networks
* **Performance Optimized** - Uses caching to avoid database overhead
* **Fully Translatable** - Complete internationalization support with translation files
* **WordPress 6.8 Compatible** - Tested with the latest WordPress version
* **Mobile Responsive** - Works seamlessly on all device sizes
* **Accessibility Ready** - Full ARIA support and keyboard navigation

= Perfect For =

* **SaaS Platform Developers** - Managing multiple client networks
* **Enterprise Administrators** - Overseeing multi-brand websites
* **Agency Owners** - Handling multiple client sites efficiently
* **Educational Institutions** - Managing multi-campus networks
* **Media Companies** - Operating regional or topical site networks

= How It Works =

Once activated, the plugin automatically detects your Multi Network setup and adds a network indicator to the admin bar. The indicator shows:

1. **Current Network Icon** (🌐) for easy visual identification
2. **Network Name** - The display name of your current network
3. **Site Count** - Number of sites in parentheses (e.g., "Main Network (15)")
4. **Dropdown Menu** - Quick access to all available networks

= Network Statistics =

Each network entry displays:
* Network display name
* Domain and path information
* Total number of sites
* Visual indicator for the current network (✓)

= Keyboard Shortcuts =

* **Ctrl+Shift+N** - Open network switcher dropdown

= Developer Friendly =

The plugin provides utility functions for developers:

* `wpmn_get_network_sites_count($network_id)` - Get site count for a network
* `wpmn_user_can_access_network($user_id, $network_id)` - Check user permissions
* `wpmn_get_network_display_name($network_id)` - Get formatted network name

= Translations =

The plugin is fully translatable and includes:
* English (default)
* Chinese Simplified (zh_CN)
* More languages coming soon!

Want to contribute a translation? Visit our [GitHub repository](https://github.com/wpmultisite/wp-multinetwork-adminbar).

== Installation ==

= Minimum Requirements =

* WordPress 5.0 or greater
* WordPress Multisite enabled
* WP Multi Network plugin installed and activated
* PHP version 7.4 or greater
* MySQL version 5.6 or greater

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "WP Multi Network Switcher"
4. Click "Install Now" and then "Activate"
5. The plugin will automatically detect your Multi Network setup

= Manual Installation =

1. Download the plugin zip file
2. Extract the files to your `/wp-content/plugins/wp-multinetwork-switcher/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure you have WP Multi Network plugin installed and configured

= Network Activation =

For Multi Network setups, you should activate this plugin at the network level:

1. Go to Network Admin → Plugins
2. Find "WP Multi Network Switcher"
3. Click "Network Activate"

== Frequently Asked Questions ==

= Does this plugin work with regular WordPress Multisite? =

This plugin is specifically designed for WP Multi Network environments. While it can detect regular multisite installations, the network switching functionality requires the WP Multi Network plugin to be installed and configured.

= Can I customize the network display names? =

Yes! The plugin uses the "Site Name" setting from each network's configuration. You can change this in Network Admin → Settings for each individual network.

= How do I hide the network switcher for certain users? =

The network switcher only appears for users with `manage_network` capability. You can customize this by using WordPress capability management plugins or custom code.

= Does this plugin affect site performance? =

No, the plugin is designed with performance in mind. It uses WordPress caching to store network statistics and only loads when the admin bar is displayed.

= Can I translate this plugin to my language? =

Absolutely! The plugin is fully internationalized. You can find translation files in the `/languages/` directory. We welcome community translations on our GitHub repository.

= Is this plugin compatible with other admin bar plugins? =

Yes, the plugin is designed to work alongside other admin bar modifications. It adds its own menu item without interfering with existing functionality.

= What happens if I deactivate WP Multi Network? =

The plugin will gracefully detect the absence of WP Multi Network and won't display the network switcher. No errors or conflicts will occur.

== Screenshots ==

1. **Admin Bar Integration** - The network indicator seamlessly integrated into the WordPress admin bar
2. **Network Dropdown Menu** - Complete network listing with site counts and quick switching
3. **Current Network Highlighting** - Clear visual indication of the active network with checkmark
4. **Mobile Responsive Design** - Optimized display for mobile devices and tablets
5. **Network Statistics Display** - Detailed information showing domain, path, and site counts
6. **Settings Integration** - Network management links for easy administration access

== Changelog ==

= 1.0.0 - 2025-06-15 =
* Initial release
* Network identification in admin bar
* Quick network switching functionality
* Network statistics display
* Performance optimization with caching
* Full internationalization support
* WordPress 6.8 compatibility
* Mobile responsive design
* Accessibility features
* Keyboard shortcuts support

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Multi Network Admin Bar. This plugin enhances your Multi Network management experience with intuitive network switching and identification features.

== Support ==

For support, feature requests, and bug reports, please visit:

* **Documentation**: https://wpmultinetwork.com/document/wp-multinetwork-switcher
* **Support Forum**: https://wpmultinetwork.com/support
* **GitHub Issues**: https://github.com/wpmultisite/wp-multinetwork-switcher/issues

== Contributing ==

We welcome contributions! Please see our [contributing guidelines](https://github.com/wpmultisite/wp-multinetwork-switcher/blob/main/CONTRIBUTING.md) on GitHub.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. It only accesses WordPress network configuration data that is already available to users with appropriate permissions.

== Credits ==

Developed by the WPMultiNetwork.com team with ❤️ for the WordPress Multi Network community.

Special thanks to contributors and translators who help make this plugin better for everyone.