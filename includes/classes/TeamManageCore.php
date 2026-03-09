<?php
declare(strict_types=1);
/**
 * TeamManageCore
 *
 * Core plugin class for EM Woo Team Manage.
 *
 * Responsibilities:
 * - Registers custom user roles for team leaders and subordinates
 * - Handles admin menu and submenu registration
 * - Manages user profile fields for team assignment
 * - Delegates AJAX and admin asset logic to TeamAjaxHandler
 * - Integrates with WooCommerce for automatic team leader creation after payment
 */
namespace emWooTeamManage\init_plugin\Classes;
require_once __DIR__ . '/TeamAjaxHandler.php';
require_once __DIR__ . '/TeamUserImporter.php';

class TeamManageCore
{
    /**
     * Constructor: Registers hooks for plugin initialization, admin, AJAX, and WooCommerce integration.
     */
    private $ajax;
    private $importer;
    public function __construct()
    {
        // Initialization hooks
        add_action('init', [$this, 'user_import_inits']);

        // Admin menu
        add_action('admin_menu', [$this, 'user_import_register_submenu_page']);
        add_action('admin_init', [$this, 'register_plugin_settings']);

        // WooCommerce hook
        add_action('woocommerce_thankyou', [$this, 'create_Team_Leader_After_Payment'], 10, 1);

        // AJAX handlers (delegated to TeamAjaxHandler)
        $this->ajax = new TeamAjaxHandler();

        add_action('wp_ajax_team_Leader_Form_Submission', [$this->ajax, 'team_Leader_Form_Submission']);
        add_action('admin_enqueue_scripts', [$this->ajax, 'load_Admin_Styles']);
        add_action('wp_ajax_edit_subordinate', [$this->ajax, 'handle_edit_subordinate']);
        add_action('admin_post_edit_subordinate', [$this->ajax, 'handle_edit_subordinate']);
        add_action('wp_ajax_get_subordinates', [$this->ajax, 'ajax_get_subordinates']);
        $this->importer = new TeamUserImporter();
        add_action('wp_ajax_user_import_submission', [$this->importer, 'user_import_submission']);
        add_action('wp_ajax_add_single_subordinate', [$this->ajax, 'add_single_subordinate']);
        add_action('wp_ajax_export_team_csv', [$this->ajax, 'export_team_csv']);

        // WooCommerce My Account tab
        add_action('init', [$this, 'register_myaccount_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_myaccount_menu_item']);
        add_action('woocommerce_account_team-manage_endpoint', [$this, 'myaccount_team_manage_content']);
        add_filter('the_title', [$this, 'myaccount_endpoint_title']);
        
    }

    /**
    * Registers custom user roles and WooCommerce admin access for team leaders.
    */
    public function user_import_inits() {
        // Common capabilities for custom roles
        $role_caps = array(
            'read' => true,
            'create_posts' => false,
            'edit_posts' => false,
            'edit_others_posts' => false,
            'publish_posts' => false,
            'manage_categories' => false,
        );

        // Add custom roles if not already present
        if (!get_role('team_leader')) {
            add_role('team_leader', 'Team Leader', $role_caps);
        }
        if (!get_role('team_subordinate')) {
            add_role('team_subordinate', 'Team Subordinate', $role_caps);
        }

        // Check if WooCommerce is active and user is a team leader
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $user = wp_get_current_user();
            if (in_array('team_leader', (array) $user->roles)) {
                add_filter('woocommerce_prevent_admin_access', '__return_false');
                add_filter('woocommerce_disable_admin_bar', '__return_false');
            }
        }
    }

    /**
    * Registers admin menu and submenu pages for team management.
    */
    public function user_import_register_submenu_page() {

        // Main menu page
        add_menu_page(
            'Add Subordinates',
            'Team Manage',
            'read',
            'user-import-controls',
            '',
            '',
            2
        );

        // Submenu pages configuration
        $submenus = [
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'Add Subordinates',
                'menu_title'  => 'Add Subordinates',
                'capability'  => 'read',
                'menu_slug'   => 'user-import-controls',
                'template'    => 'team-leader-user-import-page.php',
                'position'    => 3
            ],
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'View Subordinates',
                'menu_title'  => 'View Subordinates',
                'capability'  => 'read',
                'menu_slug'   => 'team-leader-admin',
                'template'    => 'team-leader-admin-page.php',
                'position'    => 1
            ],
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'Site Admin View',
                'menu_title'  => 'Site Admin View',
                'capability'  => 'manage_options',
                'menu_slug'   => 'site-admin-team-leader-admin',
                'template'    => 'site-admin-team-leader-page.php',
                'position'    => 2
            ],
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'Team Settings',
                'menu_title'  => 'Settings',
                'capability'  => 'manage_options',
                'menu_slug'   => 'emwtm-settings',
                'template'    => null,
                'callback'    => [$this, 'render_settings_page'],
                'position'    => 4
            ],
        ];

        // Add submenus with a generic callback
        foreach ($submenus as $submenu) {
            $callback = isset($submenu['callback'])
                ? $submenu['callback']
                : function() use ($submenu) { $this->require_template($submenu['template']); };
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $callback,
                $submenu['position']
            );
        }
    }

    /**
    * Registers the custom WooCommerce My Account endpoint.
    */
    public function register_myaccount_endpoint() {
        add_rewrite_endpoint('team-manage', EP_ROOT | EP_PAGES);
    }

    /**
    * Adds the Team Manage link to the WooCommerce My Account navigation.
    */
    public function add_myaccount_menu_item(array $items): array {
        $user = wp_get_current_user();
        if (!in_array('team_leader', (array) $user->roles, true)) {
            return $items;
        }
        // Insert before logout
        $logout = $items['customer-logout'] ?? [];
        unset($items['customer-logout']);
        $items['team-manage']    = __('My Team', 'emWooTeamManage');
        $items['customer-logout'] = $logout;
        return $items;
    }

    /**
    * Renders content for the team-manage WooCommerce endpoint.
    */
    public function myaccount_team_manage_content() {
        $this->require_template('team-leader-admin-page.php');
    }

    /**
    * Sets the page title for the team-manage endpoint.
    */
    public function myaccount_endpoint_title(string $title): string {
        global $wp_query;
        if (!is_null($wp_query) && isset($wp_query->query_vars['team-manage']) && in_the_loop()) {
            $title = __('My Team', 'emWooTeamManage');
        }
        return $title;
    }

    /**
    * Registers plugin settings with the WordPress Settings API.
    */
    public function register_plugin_settings() {
        register_setting('emwtm_settings_group', 'emwtm_max_subordinates', [
            'type'              => 'integer',
            'sanitize_callback' => function($val) { $v = (int) $val; return $v > 0 ? $v : 200; },
            'default'           => 200,
        ]);
        add_settings_section('emwtm_main', 'Team Manage Settings', null, 'emwtm-settings');
        add_settings_field(
            'emwtm_max_subordinates',
            'Max Subordinates per Team Leader',
            function() {
                $val = (int) get_option('emwtm_max_subordinates', 200);
                echo '<input type="number" name="emwtm_max_subordinates" value="' . esc_attr($val) . '" min="1" max="5000" class="small-text" />';
                echo '<p class="description">Maximum number of subordinates a single team leader can have (default: 200).</p>';
            },
            'emwtm-settings',
            'emwtm_main'
        );
    }

    /**
    * Renders the Settings admin page.
    */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>Team Manage Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('emwtm_settings_group');
                do_settings_sections('emwtm-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
    * Returns the configured max subordinates limit.
    */
    public static function get_max_subordinates(): int {
        return (int) get_option('emwtm_max_subordinates', 200);
    }

    /**
    * Creates a team leader user after WooCommerce payment if not already registered.
    */
    public function create_Team_Leader_After_Payment( $order_id ) {
        // If user is logged in, do nothing because they already have an account
        if ( is_user_logged_in() ) return;

        // Get the newly created order
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Get the billing email address
        $order_email = $order->get_billing_email();

        // Check if there are any users with the billing email as user or email
        $email_exists = email_exists( $order_email );
        $user_exists = username_exists( $order_email );

        // Get the order status (see if the customer has paid)
        $order_status = $order->get_status();

        // Only create user if not exists and order is paid
        if (
            ! $user_exists &&
            ! $email_exists &&
            ( $order->has_status( 'processing' ) || $order->has_status( 'completed' ) )
        ) {
            // Generate random password
            $random_password = wp_generate_password( 12 );

            // Get billing and shipping data
            $first_name = $order->get_billing_first_name();
            $last_name  = $order->get_billing_last_name();
            $role       = 'team_leader';

            // Create new user with email as username, password, and role
            $user_id = wp_insert_user( array(
                'user_email' => $order_email,
                'user_login' => $order_email,
                'user_pass'  => $random_password,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => $role,
            ) );

            if ( ! is_wp_error( $user_id ) ) {
                wp_new_user_notification( $user_id, null, "both" );
                update_user_meta( $user_id, 'guest', 'yes' );

                // User's billing data
                $billing_fields = [
                    'billing_address_1', 'billing_address_2', 'billing_city', 'billing_company',
                    'billing_country', 'billing_state', 'billing_email', 'billing_first_name',
                    'billing_last_name', 'billing_phone', 'billing_postcode'
                ];
                foreach ( $billing_fields as $field ) {
                    update_user_meta( $user_id, $field, $order->{"get_$field"}() );
                }

                // User's shipping data
                $shipping_fields = [
                    'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_company',
                    'shipping_state', 'shipping_country', 'shipping_first_name', 'shipping_last_name',
                    'shipping_method', 'shipping_postcode'
                ];
                foreach ( $shipping_fields as $field ) {
                    // Some shipping fields may not have a getter, fallback to meta if needed
                    $value = method_exists( $order, "get_$field" ) ? $order->{"get_$field"}() : $order->$field;
                    update_user_meta( $user_id, $field, $value );
                }

                // Link past orders to this newly created customer
                wc_update_new_customer_past_orders( $user_id );
            }
        }
    }

    /**
    * Helper to require template files from the templates directory.
    */
    private function require_template($template) {
        require_once(dirname(__DIR__, 2) . "/templates/{$template}");
    }
}

new TeamManageCore;