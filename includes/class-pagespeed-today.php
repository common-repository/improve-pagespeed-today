<?php
if (!defined('ABSPATH')) exit;

class PageSpeed_today {
    
    /**
     * The single instance of PageSpeed_today.
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $_instance = null;
    
    /**
     * Settings class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $settings = null;
    
    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;

    /**
     * Constructor function.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function __construct($file = '', $version = '1.1.1') {
        $this->_version = $version;
        $this->_token = 'pagespeed_today';
        $this->site_url = get_option('siteurl');

        // Load plugin environment variables
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        register_activation_hook($this->file, array(
            $this,
            'install'
        ));

        // Load admin CSS
        add_action('admin_enqueue_scripts', array(
            $this,
            'admin_enqueue_styles'
        ) , 10, 1);

        // Load API for generic admin functions
        if (is_admin()) {
            $this->admin = new PageSpeed_today_Admin_API();
        }

        // Handle localisation
        $this->load_plugin_textdomain();
        add_action('init', array(
            $this,
            'load_localisation'
        ) , 0);
    } // End __construct ()
    
    /**
     * Wrapper function to register a new post type
     * @param  string $post_type Post type name
     * @param  string $plural Post type item plural name
     * @param  string $single Post type item single name
     * @param  string $description Description of post type
     * @return object              Post type class object
     */
    public function register_post_type($post_type = '', $plural = '', $single = '', $description = '', $options = array()) {
        if (!$post_type || !$plural || !$single) return;
        $post_type = new PageSpeed_today_Post_Type($post_type, $plural, $single, $description, $options);
        return $post_type;
    }

    /**
     * Wrapper function to register a new taxonomy
     * @param  string $taxonomy Taxonomy name
     * @param  string $plural Taxonomy single name
     * @param  string $single Taxonomy plural name
     * @param  array $post_types Post types to which this taxonomy applies
     * @return object             Taxonomy class object
     */
    public function register_taxonomy($taxonomy = '', $plural = '', $single = '', $post_types = array() , $taxonomy_args = array()) {
        if (!$taxonomy || !$plural || !$single) return;
        $taxonomy = new PageSpeed_today_Taxonomy($taxonomy, $plural, $single, $post_types, $taxonomy_args);
        return $taxonomy;
    }

    /**
     * Load admin CSS.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function admin_enqueue_styles($hook = '') {
        wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/admin.css', array() , $this->_version);
        wp_enqueue_style($this->_token . '-admin');
    } // End admin_enqueue_styles ()
    
    /**
     * Load plugin localisation
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_localisation() {
        load_plugin_textdomain('pagespeed-today', false, dirname(plugin_basename($this->file)) . '/lang/');
    } // End load_localisation ()
    
    /**
     * Load plugin textdomain
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_plugin_textdomain() {
        $domain = 'pagespeed-today';
        $locale = apply_filters('plugin_locale', get_locale() , $domain);
        load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/lang/');
    } // End load_plugin_textdomain ()
    
    /**
     * Main PageSpeed_today Instance
     *
     * Ensures only one instance of PageSpeed_today is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see PageSpeed_today()
     * @return Main PageSpeed_today instance
     */
    public static function instance($file = '', $version = '1.0.0') {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }

        return self::$_instance;
    } // End instance ()
    
    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?') , $this->_version);
    } // End __clone ()
    
    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?') , $this->_version);
    } // End __wakeup ()
    
    /**
     * Installation. Runs on activation.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function install() {
        $this->_log_version_number();
    } // End install ()
    
    /**
     * Log the plugin version number.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    private function _log_version_number() {
        update_option($this->_token . '_version', $this->_version);
    } // End _log_version_number ()
}