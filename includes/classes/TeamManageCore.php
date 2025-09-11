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

        // WooCommerce hook
        add_action('woocommerce_thankyou', [$this, 'create_Team_Leader_After_Payment'], 10, 1);

        // AJAX handlers (delegated to TeamAjaxHandler)
        $this->ajax = new TeamAjaxHandler();

        add_action('wp_ajax_team_Leader_Form_Submission', [$this->ajax, 'team_Leader_Form_Submission']);
        add_action('wp_ajax_emulate_Team_Leader_Form_Submission', [$this->ajax, 'emulate_Team_Leader_Form_Submission']);
        add_action('wp_ajax_emulate_Team_subordinate_Form_Submission', [$this->ajax, 'emulate_Team_subordinate_Form_Submission']);
        add_action('admin_enqueue_scripts', [$this->ajax, 'load_Admin_Styles']);
        add_action('wp_ajax_edit_subordinate', [$this->ajax, 'handle_edit_subordinate']);
        add_action('wp_ajax_nopriv_edit_subordinate', [$this->ajax, 'handle_edit_subordinate']);
        add_action('admin_post_edit_subordinate', [$this->ajax, 'handle_edit_subordinate']);

        $this->importer = new TeamUserImporter();
        add_action('wp_ajax_user_import_submission', [$this->importer, 'user_import_submission']);
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
        ];

        // Add submenus with a generic callback
        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                function() use ($submenu) { $this->require_template($submenu['template']); },
                $submenu['position']
            );
        }
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