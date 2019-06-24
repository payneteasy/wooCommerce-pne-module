<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\Transport\Response;

class WordPressPlugin
{
    public static function get_table()
    {
        global $wpdb;

        return $wpdb->prefix.'payneteasy_transactions';
    }

    private static function get_schema()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $table                      = self::get_table();

        return "CREATE TABLE $table (
`transaction_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL DEFAULT '0',
  `paynet_order_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Order id assigned to the order by PaynetEasy',
  `mode` enum('production','sandbox') NOT NULL DEFAULT 'production',
  `operation` varchar(255) NOT NULL DEFAULT '',
  `transaction_type` enum('sale','reversal','capture','preauth','return') NOT NULL DEFAULT 'sale' COMMENT 'Transaction type',
  `integration_method` enum('inline','form') NOT NULL DEFAULT 'inline' COMMENT 'The method to show card form',
  `payment_method` varchar(32) NOT NULL DEFAULT '',
  `state` enum('new','processing','done') NOT NULL DEFAULT 'new' COMMENT 'State of transaction',
  `status` enum('new','processing','approved','declined','filtered','error') NOT NULL DEFAULT 'new',
  `date_create` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_update` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `login` varchar(255) NOT NULL DEFAULT '',
  `end_point` varchar(255) NOT NULL DEFAULT '' COMMENT 'Merchant end point',
  `end_point_group` varchar(255) DEFAULT '',
  `gateway_url` varchar(255) NOT NULL DEFAULT '',
  `html` text COMMENT 'html data from paynet',
  `errors` text,
  PRIMARY KEY (`transaction_id`),
  KEY `order_id` (`order_id`),
  KEY `paynet_order_id` (`paynet_order_id`),
  KEY `state` (`state`)
) $collate";
    }

    /**
     * Full path to wordpress plugin
     * @var string
     */
    protected $plugin_file_name;
    /**
     * @var string
     */
    protected $plugin_basename;
    /**
     * @var string
     */
    protected $plugin_dir;
    /**
     * @var string
     */
    protected $plugin_url;
    /**
     * @var string
     */
    protected $version;

    /**
     * Plugin constructor.
     * @param string $plugin_file_name
     * @param string $version
     */
    public function __construct($plugin_file_name, $version)
    {
        $this->plugin_file_name     = $plugin_file_name;
        $this->plugin_dir           = dirname($this->plugin_file_name);
        $this->plugin_basename      = plugin_basename($plugin_file_name);
        $this->version              = $version;
        $this->plugin_url           = plugin_dir_url($this->plugin_file_name);

        $GLOBALS['PaynetEasyWoocommerceGateway'] = $this;
    }

    public function init()
    {
        $this->define_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_woocommerce_hooks();
    }

    public function plugin_basename()
    {
        return $this->plugin_basename;
    }

    public function plugin_dir()
    {
        return $this->plugin_dir;
    }

    public function plugin_url()
    {
        return $this->plugin_url;
    }

    public function plugin_file_name()
    {
        return $this->plugin_file_name;
    }

    public function define_woocommerce_hooks()
    {
        $this->wp_add_filter('woocommerce_payment_gateways', [$this, 'on_payment_gateways']);
    }

    public function on_payment_gateways($gateways)
    {
        $gateways[]                 = 'PaynetEasy\\WoocommerceGateway\\Gateway';

        return $gateways;
    }

    public function on_plugins_loaded()
    {

    }

    /**
     * @return $this
     */
    public function define_locale()
    {
        load_plugin_textdomain
        (
            'paynet-easy-gateway',
            false,
            dirname($this->plugin_basename).'/languages/'
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function define_admin_hooks()
    {
        register_activation_hook($this->plugin_file_name, [$this, 'on_activate']);
        register_deactivation_hook($this->plugin_file_name, [$this, 'on_deactivate']);
        register_uninstall_hook($this->plugin_file_name, [$this, 'on_uninstall']);

        $this->wp_add_action('plugins_loaded', [$this, 'on_plugins_loaded']);

        return $this;
    }

    public function define_public_hooks()
    {
        add_filter('template_include', [$this, 'on_template_include']);
    }

    public function on_activate()
    {
        if (!current_user_can('activate_plugins'))
        {
            return;
        }

        global $wpdb;

        $table_name                 = self::get_table();

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(self::get_schema());

        return;
    }

    public function on_deactivate()
    {
        if (!current_user_can('activate_plugins'))
        {
            return;
        }

    }

    public function on_uninstall()
    {
        if (!current_user_can('activate_plugins'))
        {
            return;
        }

        global $wpdb;

        $wpdb->query('DROP TABLE IF EXISTS '.self::get_table());
    }

    /**
     * @param $template
     * @return string
     * @throws \Exception
     */
    public function on_template_include($template)
    {
        if(empty($_REQUEST[PAYNET_EASY_PAGE]))
        {
            return $template;
        }

        $gateway                    = new Gateway();
        $transaction                = $gateway->handle_progress();
        $redirect                   = null;

        // If transaction is done
        if(false === $transaction->isProcessing() || $transaction->isRedirect())
        {
            $redirect               = $gateway->define_redirect_for_transaction($transaction);

            if($redirect !== null)
            {
                wp_redirect($redirect);
            }

            return $template;
        }

        set_query_var('payneteasy_transaction', $transaction);

        // Show HTML for 3D if needed
        if(!empty($transaction->getHtml()))
        {
            return $this->plugin_dir.'/templates/show_html.php';
        }

        // including jquery
        wp_enqueue_script('jquery');
        // return progress template
        return $this->plugin_dir.'/templates/progress.php';
    }

    /**
     * @return PaymentTransaction
     * @throws \Exception
     */
    public function on_progress()
    {
        if(empty($_REQUEST['transaction_id']))
        {
            throw new \Exception('The transaction id is undefined');
        }

        // Create gate
        $gateway                    = new Gateway();
        return $gateway->handle_progress($_REQUEST['transaction_id']);
    }

    public function run()
    {

    }

    /**
     * Alias for add_filter
     *
     * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
     * @param callable $function_to_add The callback to be run when the filter is applied.
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  associated with a particular action are executed. Default 10.
     *                                  Lower numbers correspond with earlier execution,
     *                                  and functions with the same priority are executed
     *                                  in the order in which they were added to the action.
     * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
     *
     * @return $this
     */
    public function wp_add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return $this->wp_add_filter($tag, $function_to_add, $priority, $accepted_args);
    }

    /**
     * Hook a function or method to a specific filter action.
     *
     * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
     * @param callable $function_to_add The callback to be run when the filter is applied.
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  associated with a particular action are executed. Default 10.
     *                                  Lower numbers correspond with earlier execution,
     *                                  and functions with the same priority are executed
     *                                  in the order in which they were added to the action.
     * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
     *
     * @return $this
     */
    public function wp_add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        add_filter($tag, $function_to_add, $priority, $accepted_args);

        return $this;
    }
}