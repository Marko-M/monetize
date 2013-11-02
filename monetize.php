<?php
/*
Plugin Name: Monetize
Plugin URL: http://www.techytalk.info/wordpress/monetize/
Description: Monetize units and impressions.
Version: 1.03
Author: Marko Martinović
Author URL: http://www.techytalk.info
License: GPLv2 or later
*/

class Monetize {
    const version = '1.03';
    const default_db_version = 9;
    const jquery_ui_timepicker_version = '1.4';
    const jquery_swfobject_version = '1.1.1';

    const admin_cap = 'manage_options';

    const client_cap = 'monetize_own_units';
    const client_role = 'monetize_client';

    const lc_monetary = 'en_US.UTF-8';

    const link = 'http://www.techytalk.info/wordpress/monetize/';
    const donate_link = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CZQW2VZNHMGGN';
    const support_link = 'http://www.techytalk.info/wordpress/monetize/';
    const faq_link = 'http://www.techytalk.info/wordpress/monetize/';
    const changelog_link = 'http://www.techytalk.info/wordpress/monetize/';

    protected $url;
    protected $path;
    protected $basename;
    protected $db_version;
    protected $options;
    protected $log_file;

    protected $ip_data = array();

    public function __construct() {
        $this->url = WP_PLUGIN_URL . '/monetize';
        $this->path =  WP_PLUGIN_DIR . '/monetize';
        $this->basename = plugin_basename(__FILE__);
        $this->log_file = $this->path . '/' . 'debug.log';
        $this->db_version = get_option('monetize_db_version');
        $this->options = get_option('monetize_options');

        setlocale(LC_MONETARY, self::lc_monetary);

        add_action('plugins_loaded', array($this, 'update_db_check'));
        add_action('init', array($this, 'text_domain'));

        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_menu', array($this, 'add_options_page'));

        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Backend style
        add_action('admin_print_styles', array($this, 'admin_styles'));

        // Frontend style
        add_action('wp_print_styles', array($this, 'style'));

        // Backend Javascript
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Frontend Javascript
        add_action('wp_enqueue_scripts', array($this, 'scripts'));

        // Plugin row links
        add_filter('plugin_row_meta', array($this, 'plugin_meta'), 10, 2);

        // Activation Hooks
        register_activation_hook(__FILE__, array($this, 'add_role'));

        // Deactivation Hooks
        register_deactivation_hook(__FILE__, array($this, 'remove_role'));

        // Media strings (remove gallery and featured image)
        add_filter('media_view_strings', array($this, 'custom_media_uploader'));

        // Icons
        add_action('admin_head', array($this, 'custom_post_type_icons'));

        // Add swf to media uploader allowed file types
        add_filter('upload_mimes', array($this, 'allowed_mime_types'));

        // Ajax handlers
        add_action( 'wp_ajax_nopriv_monetize-ajax-fetch', array($this, 'ajax_fetch_handler'));
        add_action( 'wp_ajax_monetize-ajax-fetch', array($this, 'ajax_fetch_handler'));
        add_action( 'wp_ajax_nopriv_monetize-ajax-click', array($this, 'ajax_click_handler'));
        add_action( 'wp_ajax_monetize-ajax-click', array($this, 'ajax_click_handler'));
    }

    public function style() {
        $suffix = (isset($this->options['debug_mode']) ||
        (defined('WP_DEBUG') && WP_DEBUG)) ? '.dev' : '';

        $style_url = $this->url . '/css/monetize'.$suffix.'.css';
        $my_style_file = $this->path . '/css/monetize'.$suffix.'.css';;

        if (file_exists($my_style_file)) {
            wp_enqueue_style('monetize_style_sheet', $style_url);
        }
    }

    public function allowed_mime_types($mime_types) {
        return array_merge($mime_types, array('swf' => 'application/x-shockwave-flash'));
    }

    function custom_post_type_icons() {
    ?>
    <style type="text/css" media="screen">
        #toplevel_page_monetize-clicks .wp-menu-image {
            background: url(<?php echo $this->url ?>/img/blue-document-attribute-c.png) no-repeat 6px -17px !important;
        }
	#toplevel_page_monetize-clicks:hover .wp-menu-image, #toplevel_page_monetize-clicks.wp-has-current-submenu .wp-menu-image {
            background-position:6px 7px!important;
        }
        #toplevel_page_monetize-impressions .wp-menu-image {
            background: url(<?php echo $this->url ?>/img/blue-document-attribute-i.png) no-repeat 6px -17px !important;
        }
	#toplevel_page_monetize-impressions:hover .wp-menu-image, #toplevel_page_monetize-impressions.wp-has-current-submenu .wp-menu-image {
            background-position:6px 7px!important;
        }
        #toplevel_page_monetize-units .wp-menu-image {
            background: url(<?php echo $this->url ?>/img/blue-document-attribute-u.png) no-repeat 6px -17px !important;
        }
	#toplevel_page_monetize-units:hover .wp-menu-image, #toplevel_page_monetize-units.wp-has-current-submenu .wp-menu-image {
            background-position:6px 7px!important;
        }
        #toplevel_page_monetize-zones .wp-menu-image {
            background: url(<?php echo $this->url ?>/img/blue-document-attribute-z.png) no-repeat 6px -17px !important;
        }
	#toplevel_page_monetize-zones:hover .wp-menu-image, #toplevel_page_monetize-zones.wp-has-current-submenu .wp-menu-image {
            background-position:6px 7px!important;
        }
    </style>
    <?php
    }

    public function custom_media_uploader( $strings ) {
        global $current_screen;

        if ($current_screen->base == 'units_page_monetize-units-new' ||
            ($current_screen->base == 'toplevel_page_monetize-units' &&
                isset($_GET['monetize-unit-id']))) {
            unset( $strings['createGalleryTitle'] ); //Create Gallery
            unset( $strings['setFeaturedImageTitle'] ); //Set Featured Image

            $strings['uploadedToThisPost'] = __('Unattached', 'monetize'); // Rename dropdown filter option
            $strings['insertIntoPost'] = __('Insert', 'monetize');
        }

        return $strings;
    }

    public function add_role() {
        // Deep copy of an object
        $subscriber = get_role('subscriber');
        $administrator = get_role('administrator');

        // Create role out of subscriber role
        if(($monetize_subscriber = get_role(self::client_role)) === null) {
            $monetize_subscriber = add_role(
                self::client_role,
                __('Advertiser', 'monetize'),
                $subscriber->capabilities
            );
        }

        // Add our capability to it
        $monetize_subscriber->add_cap(self::client_cap);

        // Add client capability to administrators
        $administrator->add_cap(self::client_cap);
    }

    public function remove_role() {
        remove_role(self::client_role);

        $administrator = get_role('administrator');
        $administrator->remove_cap(self::client_cap);
    }

    public function scripts() {
        $suffix = (isset($this->options['debug_mode']) ||
                (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) ? '.dev' : '';

        wp_enqueue_script('jquery');

        if (file_exists($this->path . '/js/jquery/jquery.swfobject'.$suffix.'.js')) {
            wp_enqueue_script(
                'monetize-jquery-swfobject',
                $this->url.'/js/jquery/jquery.swfobject'.$suffix.'.js',
                false,
                self::jquery_swfobject_version,
                false
            );
        }

        if (file_exists($this->path . '/js/monetize-flash'.$suffix.'.js')) {
            wp_enqueue_script(
                'monetize-flash',
                $this->url.'/js/monetize-flash'.$suffix.'.js',
                array('jquery', 'monetize-jquery-swfobject'),
                self::version,
                true
            );

            wp_localize_script(
                'monetize-flash',
                'monetize_flash',
                array()
            );
        }

        if (file_exists($this->path . '/js/monetize-click'.$suffix.'.js')) {
            wp_enqueue_script(
                'monetize-click',
                $this->url.'/js/monetize-click'.$suffix.'.js',
                array('jquery'),
                self::version,
                true
            );

            wp_localize_script(
                'monetize-click',
                'monetize_click',
                array(
                    'ajaxurl' => admin_url(
                        'admin-ajax.php',
                        (is_ssl() ? 'https' : 'http')
                    )
                )
            );
        }

        if (file_exists($this->path . '/js/monetize'.$suffix.'.js')) {
            wp_enqueue_script(
                'monetize',
                $this->url.'/js/monetize'.$suffix.'.js',
                array('jquery', 'monetize-flash', 'monetize-click'),
                self::version,
                true
            );

            wp_localize_script(
                'monetize',
                'monetize',
                array(
                    'ajaxurl' => admin_url(
                        'admin-ajax.php',
                        (is_ssl() ? 'https' : 'http')
                    ),
                    'wp_cache' => (defined('WP_CACHE') && WP_CACHE && !is_user_logged_in()) ? 1:0,
                    'script_suffix' => $suffix,
                    'version' => self::version,
                    'url' => $this->url
                )
            );
        }
    }

    public function ajax_click_handler() {
        $response = -1;

        if(isset($_POST['data']) && is_array($_POST['data']) &&
                isset($_POST['data']['impression_id'])) {
            $impression_id = stripslashes($_POST['data']['impression_id']);
            if(($click_id = $this->new_click($impression_id)) !== false) {
                $response = $click_id;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    public function ajax_fetch_handler() {
        $response = -1;

        if(isset($_POST['data']) && is_array($_POST['data']) &&
                isset($_POST['data']['zones'])) {
            $response = array();

            $zones = (array)$_POST['data']['zones'];
            $response['zones'] = array();

            if(!empty($_POST['data']['url'])) {
                $url = trim($_POST['data']['url']);
            } else {
                $url = null;
            }

            if(!empty($_POST['data']['referer'])) {
                $referer = trim($_POST['data']['referer']);
            } else {
                $referer = null;
            }

            foreach ($zones as $zone_id) {
                $unit = $this->get_random_unit($zone_id);

                if(isset($unit) && is_array($unit) && isset($unit['unit_html'])) {
                    if(($impression_id = $this->new_impression(
                            $unit['unit_id'], $url, $referer)) !== false) {
                        $response[$zone_id] = array(
                            'impression_id' => stripslashes($impression_id),
                            'unit_html' => stripslashes($unit['unit_html']),
                            'zone_width' => stripslashes($unit['zone_width']),
                            'zone_height' => stripslashes($unit['zone_height']),
                            'zone_css' => stripslashes($unit['zone_css'])
                        );
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Add admin menu separator before or after given menu item as identified by slug
     *
     * @param string $slug Admin menu item slug
     * @param string $mode Can be 'before' or 'after', by default it is 'before'
     */
    public function add_admin_menu_separator($slug, $mode = 'before') {
        global $menu;

        $count = 0;
        foreach($menu as $section) {
            if($section[2] == $slug) {
                if($mode == 'after')
                    $count++;

                // Part of the menu before target
                $new_menu = array_slice($menu, 0, $count, true);
                $new_menu[] = array('', 'read', 'separator'.$count, '', 'wp-menu-separator');

                // Part of the menu after target
                $after = array_slice($menu, $count, null, true);
                foreach ($after as $aoffset => $asection) {
                    $new_menu[$aoffset+1] = $asection;
                }
                // Overwrite old menu
                $menu = $new_menu;
                break;
            }
            $count++;
        }
    }

    public function admin_styles() {
        // Only authors and clients need the CSS
        if(!current_user_can(self::client_cap))
            return;

        global $current_screen;
        if (
                $current_screen->base != 'toplevel_page_monetize-clicks' &&
                $current_screen->base != 'toplevel_page_monetize-impressions' &&
                $current_screen->base != 'toplevel_page_monetize-zones' &&
                $current_screen->base != 'toplevel_page_monetize-units' &&
                $current_screen->base != 'units_page_monetize-units-new' &&
                $current_screen->base != 'impressions_page_monetize-impressions-trends' &&
                $current_screen->base != 'clicks_page_monetize-clicks-trends') {
            return;
        }

        $suffix = (isset($this->options['debug_mode']) ||
                (defined('WP_DEBUG') && WP_DEBUG)) ? '.dev' : '';

        if( $current_screen->base == 'toplevel_page_monetize-clicks' ||
            $current_screen->base == 'toplevel_page_monetize-impressions' ||
            ($current_screen->base == 'toplevel_page_monetize-zones' &&
                !isset($_GET['monetize-zone-id'])) ||
            ($current_screen->base == 'toplevel_page_monetize-units' &&
                !isset($_GET['monetize-unit-id'])) ||
            $current_screen->base == 'impressions_page_monetize-impressions-trends' ||
            $current_screen->base == 'clicks_page_monetize-clicks-trends') {
            global $wp_scripts;

            // jQuery UI style
            $ui = $wp_scripts->query('jquery-ui-core');
            wp_enqueue_style('monetize-jquery-ui', 'https://ajax.aspnetcdn.com/ajax/jquery.ui/'.$ui->ver.'/themes/redmond/jquery-ui.css' , false, $ui->ver);

            // jQuery UI timepicker style
            if (file_exists($this->path . '/css/jquery-ui/jquery-ui-timepicker'.$suffix.'.css')) {
                wp_enqueue_style('monetize-jquery-ui-timepicker', $this->url .'/css/jquery-ui/jquery-ui-timepicker'.$suffix.'.css', false, self::jquery_ui_timepicker_version);
            }
        }

        // Monetize style
        if (file_exists($this->path . '/css/monetize-admin'.$suffix.'.css')) {
            wp_enqueue_style('monetize-admin', $this->url.'/css/monetize-admin'.$suffix.'.css', array(), self::version);
        }
    }

    public function admin_scripts($hook) {
        // Only authors and clients need the Javascript
        if(!current_user_can(self::client_cap))
            return;

        if( $hook != 'toplevel_page_monetize-clicks'  &&
            $hook != 'toplevel_page_monetize-impressions'  &&
            $hook != 'toplevel_page_monetize-zones' &&
            $hook != 'toplevel_page_monetize-units' &&
            $hook != 'units_page_monetize-units-new' &&
            $hook != 'impressions_page_monetize-impressions-trends' &&
            $hook != 'clicks_page_monetize-clicks-trends')
        return;

        $suffix = (isset($this->options['debug_mode']) ||
                (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) ? '.dev' : '';

        // jQuery
        wp_enqueue_script('jquery');

        if( $hook == 'toplevel_page_monetize-clicks' ||
            $hook == 'toplevel_page_monetize-impressions' ||
            ($hook == 'toplevel_page_monetize-zones' &&
                !isset($_GET['monetize-zone-id'])) ||
            ($hook == 'toplevel_page_monetize-units' &&
                        !isset($_GET['monetize-unit-id'])) ||
            $hook == 'impressions_page_monetize-impressions-trends'||
            $hook == 'clicks_page_monetize-clicks-trends') {

            // jQuery UI scripts
            wp_enqueue_script('jquery-ui-slider');
            wp_enqueue_script('jquery-ui-datepicker');

            // jQuery UI timepicker script
            if (file_exists($this->path . '/js/jquery-ui/jquery-ui-timepicker'.$suffix.'.js')) {
                wp_enqueue_script('monetize-jquery-ui-timepicker', $this->url. '/js/jquery-ui/jquery-ui-timepicker'.$suffix.'.js', array('jquery', 'jquery-ui-slider', 'jquery-ui-datepicker'), self::jquery_ui_timepicker_version, true);
            }

            // Monetize script
            if (file_exists($this->path . '/js/monetize-timepicker'.$suffix.'.js')) {
                wp_enqueue_script('monetize-timepicker', $this->url. '/js/monetize-timepicker'.$suffix.'.js', array('jquery', 'jquery-ui-slider', 'jquery-ui-datepicker', 'monetize-jquery-ui-timepicker'), self::version, true);
                wp_localize_script(
                    'monetize-timepicker',
                        'monetize', array(
                        'i18n' => array(
                            // Datepicker
                            'closeText' => __('Done', 'monetize'),
                            'prevText' => __('Prev', 'monetize'),
                            'nextText' => __('Next', 'monetize'),
                            'currentText' => __('Today', 'monetize'),
                            'monthNames' =>
                                array(
                                    __('January', 'monetize'),
                                    __('February', 'monetize'),
                                    __('March', 'monetize'),
                                    __('April', 'monetize'),
                                    __('May', 'monetize'),
                                    __('June', 'monetize'),
                                    __('July', 'monetize'),
                                    __('August', 'monetize'),
                                    __('September', 'monetize'),
                                    __('October', 'monetize'),
                                    __('November', 'monetize'),
                                    __('December', 'monetize'),
                                ),
                            'monthNamesShort' =>
                                array(
                                    __('Jan', 'monetize'),
                                    __('Feb', 'monetize'),
                                    __('Mar', 'monetize'),
                                    __('Apr', 'monetize'),
                                    __('May', 'monetize'),
                                    __('Jun', 'monetize'),
                                    __('Jul', 'monetize'),
                                    __('Aug', 'monetize'),
                                    __('Sep', 'monetize'),
                                    __('Oct', 'monetize'),
                                    __('Nov', 'monetize'),
                                    __('Dec', 'monetize'),
                                ),
                            'dayNames' =>
                            array(
                                __('Sunday', 'monetize'),
                                __('Monday', 'monetize'),
                                __('Tuesday', 'monetize'),
                                __('Wednesday', 'monetize'),
                                __('Thursday', 'monetize'),
                                __('Friday', 'monetize'),
                                __('Saturday', 'monetize'),
                            ),
                            'dayNamesShort' =>
                                array(
                                    __('Sun', 'monetize'),
                                    __('Mon', 'monetize'),
                                    __('Tue', 'monetize'),
                                    __('Wed', 'monetize'),
                                    __('Thu', 'monetize'),
                                    __('Fri', 'monetize'),
                                    __('Sat', 'monetize'),
                                ),
                            'dayNamesMin' =>
                                array(
                                    __('Su', 'monetize'),
                                    __('Mo', 'monetize'),
                                    __('Tu', 'monetize'),
                                    __('We', 'monetize'),
                                    __('Th', 'monetize'),
                                    __('Fr', 'monetize'),
                                    __('Sa', 'monetize'),
                                ),
                            'weekHeader' => __('Wk', 'monetize'),
                            'dateFormat' => __('mm/dd/yy', 'monetize'),

                            // Timepicker
                            'currentText' => __('Now', 'monetize'),
                            'closeText' => __('Done', 'monetize'),
                            'amNames' =>
                                array(
                                    __('AM', 'monetize'),
                                    __('A', 'monetize')
                                ),
                            'pmNames' =>
                                array(
                                    __('PM', 'monetize'),
                                    __('P', 'monetize')
                                ),
                            'timeFormat' => __('HH:mm', 'monetize'),
                            'timeOnlyTitle' => __('Choose Time', 'monetize'),
                            'timeText' => __('Time', 'monetize'),
                            'hourText' => __('Hour', 'monetize'),
                            'minuteText' => __('Minute', 'monetize'),
                            'secondText' => __('Second', 'monetize'),
                            'millisecText' => __('Millisecond', 'monetize'),
                            'timezoneText' => __('Time Zone', 'monetize'),
                        )
                    )
                );
            }
        }

        if ($hook == 'units_page_monetize-units-new' ||
            ($hook == 'toplevel_page_monetize-units' &&
                isset($_GET['monetize-unit-id']))) {
            // Enqueue media manager
            wp_enqueue_media();

            if (file_exists($this->path.'/js/monetize-media'.$suffix.'.js')) {
                wp_enqueue_script(
                    'monetize-media',
                    $this->url. '/js/monetize-media'.$suffix.'.js',
                    array('jquery'),
                    self::version,
                    true
                );

                wp_localize_script(
                    'monetize-media',
                        'monetize_media', array(
                        'i18n' => array(
                            'custom_url' => __('Custom URL', 'monetize')
                        )
                    )
                );
            }
        }

        if($hook == 'impressions_page_monetize-impressions-trends' || $hook == 'clicks_page_monetize-clicks-trends') {
            wp_enqueue_script('monetize-google-api', 'https://www.google.com/jsapi', false, false, true);

            if($hook == 'impressions_page_monetize-impressions-trends') {
                if (file_exists($this->path . '/js/monetize-impressions-trends'.$suffix.'.js')) {
                    wp_enqueue_script('monetize-trends', $this->url. '/js/monetize-impressions-trends'.$suffix.'.js', array('monetize-google-api'), self::version, true);

                    wp_localize_script(
                        'monetize-trends',
                            'monetize_trends', array(
                            'i18n' => array(
                                'impressions' => __('Impressions', 'monetize'),
                                'date' => __('Dates', 'monetize'),
                            )
                        )
                    );
                }
            }

            if($hook == 'clicks_page_monetize-clicks-trends') {
                if (file_exists($this->path . '/js/monetize-clicks-trends'.$suffix.'.js')) {
                    wp_enqueue_script('monetize-trends', $this->url. '/js/monetize-clicks-trends'.$suffix.'.js', array('monetize-google-api'), self::version, true);

                    wp_localize_script(
                        'monetize-trends',
                            'monetize_trends', array(
                            'i18n' => array(
                                'clicks' => __('Clicks', 'monetize'),
                                'date' => __('Dates', 'monetize'),
                            )
                        )
                    );
                }
            }
        }
    }

    public function show_zone($zone_id) {
        if(isset($zone_id) && is_numeric($zone_id)) {
            if(defined('WP_CACHE') && WP_CACHE && !is_user_logged_in()) {
?>
<div class="monetize-zone" data-monetize-zone-id="<?php echo $zone_id; ?>"></div>
<?php
                if(!isset($this->options['hide_linkhome'])) {
?>
<div class="monetize-linkhome">
    <a href="<?php echo self::link; ?>" target="_blank">
        <?php echo __('Powered by Monetize', 'monetize') ?>
    </a>
</div>
<?php
                }
            } else {
                $unit = $this->get_random_unit($zone_id);

                if(isset($unit) && is_array($unit) && isset($unit['unit_html'])) {
                    $ip_data = $this->get_ip_data();

                    if(($impression_id = $this->new_impression(
                        $unit['unit_id'], $ip_data['url'], $ip_data['referer'])) !== false) {
                            $unit_html = stripslashes($unit['unit_html']);
                            $zone_width = stripslashes($unit['zone_width']);
                            $zone_height = stripslashes($unit['zone_height']);
                            $zone_css = stripslashes($unit['zone_css']);
?>
<div class="monetize-zone"
     data-monetize-zone-id="<?php echo $zone_id; ?>"
     data-monetize-impression-id="<?php echo $impression_id; ?>"
     style="<?php echo 'width:'.$zone_width.'px;'.'height:'.$zone_height.'px;'.$zone_css; ?>">
    <?php echo $unit_html; ?>
</div>
<?php

                        if(!isset($this->options['hide_linkhome'])) {
?>
<div class="monetize-linkhome">
    <a href="<?php echo self::link; ?>" target="_blank">
        <?php echo __('Powered by Monetize', 'monetize') ?>
    </a>
</div>
<?php
                        }
                    }
                }
            }
        }
    }

    public function delete_impressions($ids) {
        global $wpdb;

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';

        if(is_array($ids))
            $ids = implode(', ', esc_sql($ids));
        else
            $ids = esc_sql($ids);

        if (!empty($ids)) {
            $wpdb->query('DELETE FROM '.$impressions_table_name.' WHERE impression_id IN('.$ids.')');
        }
    }

    public function get_impressions(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $orderby = 'impression_created',
            $order = 'DESC',
            $start = 0,
            $offset = '18446744073709551615',
            $unit_user_id = null) {
        global $wpdb;

        $wpdb->flush();

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            impression_id,
            impression_ip,
            impression_created,
            impression_url,
            impression_agent,
            impression_referer,
            unit_id,
            unit_name,
            unit_user_id,
            user_login as unit_user_login
        FROM
            '.$impressions_table_name.'
        INNER JOIN
            '.$units_table_name.' ON unit_id = impression_unit_id
        INNER JOIN '.$wpdb->users.'
            ON '.$wpdb->users.'.ID = unit_user_id
        WHERE
            impression_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $select_sql .= ' ORDER BY
            '.esc_sql($orderby).' '.esc_sql($order).'
        LIMIT
            '.esc_sql($start).', '.esc_sql($offset).'';

        $impressions = $wpdb->get_results($select_sql, ARRAY_A);

        $wpdb->flush();

        return $impressions;
    }

    public function get_impressions_count(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $unit_user_id = null) {
        global $wpdb;

        $wpdb->flush();

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            COUNT(*)
        FROM
            '.$impressions_table_name.'
        INNER JOIN
            '.$units_table_name.' ON unit_id = impression_unit_id
        WHERE
            impression_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $count = $wpdb->get_var($select_sql);

        $wpdb->flush();

        return $count;
    }

    public function delete_clicks($ids) {
        global $wpdb;

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';

        if(is_array($ids))
            $ids = implode(', ', esc_sql($ids));
        else
            $ids = esc_sql($ids);

        if (!empty($ids)) {
            $wpdb->query('DELETE FROM '.$clicks_table_name.' WHERE click_id IN('.$ids.')');
        }
    }

    public function get_clicks(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $orderby = 'impression_created',
            $order = 'DESC',
            $start = 0,
            $offset = '18446744073709551615',
            $unit_user_id = null) {
        global $wpdb;

        $wpdb->flush();

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            click_id,
            impression_ip,
            impression_created,
            impression_url,
            impression_agent,
            impression_referer,
            unit_id,
            unit_name,
            unit_user_id,
            user_login as unit_user_login
        FROM
            '.$clicks_table_name.'
        INNER JOIN
            '.$impressions_table_name.' ON impression_id = click_impression_id
        INNER JOIN
            '.$units_table_name.' ON unit_id = impression_unit_id
        INNER JOIN '.$wpdb->users.'
            ON '.$wpdb->users.'.ID = unit_user_id
        WHERE
            impression_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $select_sql .= ' ORDER BY
            '.esc_sql($orderby).' '.esc_sql($order).'
        LIMIT
            '.esc_sql($start).', '.esc_sql($offset).'';

        $impressions = $wpdb->get_results($select_sql, ARRAY_A);

        $wpdb->flush();

        return $impressions;
    }

    public function get_clicks_count(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $unit_user_id = null) {
        global $wpdb;

        $wpdb->flush();

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            COUNT(*)
        FROM
            '.$clicks_table_name.'
        INNER JOIN
            '.$impressions_table_name.' ON impression_id = click_impression_id
        INNER JOIN
            '.$units_table_name.' ON unit_id = impression_unit_id
        WHERE
            impression_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $count = $wpdb->get_var($select_sql);

        $wpdb->flush();

        return $count;
    }

    public function delete_units($ids) {
        global $wpdb;

        $units_table_name = $wpdb->prefix . 'monetize_units';

        if(is_array($ids))
            $ids = implode(',', esc_sql($ids));
        else
            $ids = esc_sql($ids);

        if (!empty($ids)) {
            $wpdb->query('DELETE FROM '.$units_table_name.' WHERE unit_id IN('.$ids.')');
        }
    }

    public function get_units(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $orderby = 'unit_created',
            $order = 'DESC',
            $start = 0,
            $offset = '18446744073709551615',
            $unit_user_id = null) {
        global $wpdb;

        $wpdb->flush();

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';
        $zones_table_name = $wpdb->prefix . 'monetize_zones';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            unit_id,
            unit_user_id,
            user_login as unit_user_login,
            unit_created,
            COUNT(impression_id) AS impressions_count,
            COUNT(click_id) AS clicks_count,
            COUNT(click_id)/(COUNT(impression_id)) AS unit_ctr,
            unit_price,
            unit_cpm,
            unit_limit,
            unit_mode,
            unit_name,
            zone_id,
            zone_name
        FROM '.$units_table_name.'
        INNER JOIN '.$wpdb->users.'
            ON '.$wpdb->users.'.ID = unit_user_id
        LEFT JOIN '.$impressions_table_name.'
            ON impression_unit_id = unit_id
        LEFT JOIN '.$clicks_table_name.'
            ON click_impression_id = impression_id
        INNER JOIN '.$zones_table_name.'
            ON unit_zone_id = zone_id
        WHERE
            unit_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $select_sql .=
        ' GROUP BY
            unit_id
        ORDER BY
            '.esc_sql($orderby).' '.esc_sql($order).'
        LIMIT
            '.esc_sql($start).', '.esc_sql($offset);

        $units = $wpdb->get_results($select_sql, ARRAY_A);

        $wpdb->flush();

        return $units;
    }

    public function get_units_count(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $unit_user_id = null
        ) {
        global $wpdb;

        $wpdb->flush();

        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            COUNT(*)
        FROM
            '.$units_table_name.'
        WHERE
            unit_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        if(!empty($unit_user_id)) {
            $select_sql .= ' AND unit_user_id = '.esc_sql($unit_user_id);
        }

        $count = $wpdb->get_var($select_sql);

        $wpdb->flush();

        return $count;
    }

    public function delete_zones($ids) {
        global $wpdb;

        $zones_table_name = $wpdb->prefix . 'monetize_zones';

        if(is_array($ids))
            $ids = implode(',', esc_sql($ids));
        else
            $ids = esc_sql($ids);

        if (!empty($ids)) {
            $wpdb->query('DELETE FROM '.$zones_table_name.' WHERE zone_id IN('.$ids.')');
        }
    }

    public function get_zones(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $orderby = 'zone_created',
            $order = 'DESC',
            $start = 0,
            $offset = '18446744073709551615'
            ) {
        global $wpdb;

        $wpdb->flush();

        $zones_table_name = $wpdb->prefix . 'monetize_zones';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            COUNT(unit_id) AS units_count,
            zone_id,
            zone_created,
            zone_name,
            zone_width,
            zone_height
        FROM
            '.$zones_table_name.'
        LEFT JOIN '.$units_table_name.'
            ON unit_zone_id = zone_id
        WHERE
            zone_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"
        GROUP BY
            zone_id
        ORDER BY
            '.esc_sql($orderby).' '.esc_sql($order).'
        LIMIT
            '.esc_sql($start).', '.esc_sql($offset);

        $zones = $wpdb->get_results($select_sql, ARRAY_A);

        $wpdb->flush();

        return $zones;
    }

    public function get_zones_count($start_timestamp = 0, $end_timestamp = 2147483647) {
        global $wpdb;

        $wpdb->flush();

        $zones_table_name = $wpdb->prefix . 'monetize_zones';

        $select_sql =
        'SELECT
            COUNT(*)
        FROM
            '.$zones_table_name.'
        WHERE
            zone_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $end_timestamp)).'"';

        $count = $wpdb->get_var($select_sql);

        $wpdb->flush();

        return $count;
    }

    public function get_impressions_trends(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $unit_user_id = null) {
        global $wpdb;

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $calendar_table_name = $wpdb->prefix . 'monetize_calendar';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
            'SELECT
                DATE_FORMAT(dt, "%Y/%m/%d") as date,
                IFNULL(icount, 0) as impressions
            FROM '.$calendar_table_name.'
            JOIN (
                SELECT
                    DATE(impression_created) AS idate,
                    COUNT(impression_created) AS icount
                FROM '.$impressions_table_name;

        if(!empty($unit_user_id)) {
            $select_sql .= ' INNER JOIN '.$units_table_name.'
                ON unit_id = impression_unit_id
            WHERE unit_user_id = '.esc_sql($unit_user_id);
        }

        $select_sql .= ' GROUP BY idate
            ) AS r ON idate = dt
            WHERE
                dt BETWEEN
                "'.gmdate('Y-m-d', $start_timestamp).'" AND
                    "'.gmdate('Y-m-d', $end_timestamp).'";';



        $stats = $wpdb->get_results($select_sql);
        if(empty($stats))
            $stats = $this->no_stats($start_timestamp, $end_timestamp);

        return $stats;
    }

    public function get_clicks_trends(
            $start_timestamp = 0,
            $end_timestamp = 2147483647,
            $unit_user_id = null) {
        global $wpdb;

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $calendar_table_name = $wpdb->prefix . 'monetize_calendar';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
            'SELECT
                DATE_FORMAT(dt, "%Y/%m/%d") as date,
                IFNULL(ccount, 0) as clicks
            FROM '.$calendar_table_name.'
            JOIN (
                SELECT
                    DATE(click_created) AS cdate,
                    COUNT(click_created) AS ccount
                FROM '.$impressions_table_name.'
                INNER JOIN '.$clicks_table_name.'
                    ON click_impression_id = impression_id';

        if(!empty($unit_user_id)) {
            $select_sql .= ' INNER JOIN '.$units_table_name.'
                ON unit_id = impression_unit_id
            WHERE unit_user_id = '.esc_sql($unit_user_id);
        }

        $select_sql .= ' GROUP BY cdate
            ) AS r ON cdate = dt
            WHERE
                dt BETWEEN
                "'.gmdate('Y-m-d', $start_timestamp).'" AND
                    "'.gmdate('Y-m-d', $end_timestamp).'";';



        $stats = $wpdb->get_results($select_sql);
        if(empty($stats))
            $stats = $this->no_stats($start_timestamp, $end_timestamp);

        return $stats;
    }

    public function add_admin_menu() {
        add_menu_page(__('Zones', 'monetize'), __('Zones', 'monetize'), self::admin_cap, 'monetize-zones', array($this, 'all_zones_html'));
        add_submenu_page('monetize-zones', __('All Zones', 'monetize'), __('All Zones', 'monetize'), self::admin_cap, 'monetize-zones', array($this, 'all_zones_html'));
        add_submenu_page('monetize-zones', __('Add New', 'monetize'), __('Add New', 'monetize'), self::admin_cap, 'monetize-zones-new', array($this, 'new_zone_html'));

        add_menu_page(__('Units', 'monetize'), __('Units', 'monetize'), self::client_cap, 'monetize-units', array($this, 'all_units_html'));
        add_submenu_page('monetize-units', __('All Units', 'monetize'), __('All Units', 'monetize'), self::client_cap, 'monetize-units', array($this, 'all_units_html'));
        add_submenu_page('monetize-units', __('Add New', 'monetize'), __('Add New', 'monetize'), self::admin_cap, 'monetize-units-new', array($this, 'new_unit_html'));

        add_menu_page(__('Impressions', 'monetize'), __('Impressions', 'monetize'), self::client_cap, 'monetize-impressions', array($this, 'all_impressions_html'));
        add_submenu_page('monetize-impressions', __('Trends', 'monetize'), __('Trends', 'monetize'), self::client_cap, 'monetize-impressions-trends', array($this, 'impressions_trends_html'));

        add_menu_page(__('Clicks', 'monetize'), __('Clicks', 'monetize'), self::client_cap, 'monetize-clicks', array($this, 'all_clicks_html'));
        add_submenu_page('monetize-clicks', __('Trends', 'monetize'), __('Trends', 'monetize'), self::client_cap, 'monetize-clicks-trends', array($this, 'clicks_trends_html'));

        // Adds custom separator after comments
        if(current_user_can(self::admin_cap)) {
            $this->add_admin_menu_separator('monetize-zones', 'before');
        } else {
            $this->add_admin_menu_separator('monetize-units', 'before');
        }
        $this->add_admin_menu_separator('monetize-clicks', 'after');

    }

    public function all_zones_html() {
        if(isset($_GET['monetize-zone-id']) && is_numeric($_GET['monetize-zone-id'])) {
            // Edit
            global $wpdb;
            $zone_id = $_GET['monetize-zone-id'];

            $zones_table_name = $wpdb->prefix . 'monetize_zones';

            $select_sql =
            'SELECT
                *
            FROM
                '.$zones_table_name.'
            WHERE
                zone_id = '.esc_sql($zone_id).'';

            $zone = $wpdb->get_row($select_sql, ARRAY_A);

            if(!empty($zone) && is_array($zone)) {
                $this->new_edit_zone_html('edit', $zone, $zone_id);
            } else {
                $notices = array();

                $notices[] = array('class' => 'error', 'message' => __('Unfortunately zone you have requested does not exist.', 'monetize'));
                $notices[] = array('class' => 'error', 'message' => sprintf(__('<a href="%s">&larr; Back to Zones</a>', 'monetize'), admin_url('admin.php?page=monetize-zones')));

                $this->edit_zone_404_html($notices);
            }
        }else{
?>
<div class="wrap">
    <h2>
        <?php _e('Zones', 'monetize'); ?>
        <a class="add-new-h2" href="<?php echo admin_url('admin.php?page=monetize-zones-new') ?>"><?php _e('Add New', 'monetize') ?></a>
    </h2>

    <form id="monetize-zones-list" method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <?php
        $list_table = new Monetize_Zones_List_Table();
        $list_table->prepare_items();
        $list_table->display()
        ?>
    </form>
</div>
<?php
        }
    }

    public function new_zone_html() {
        $this->new_edit_zone_html('new');
    }

    public function impressions_trends_html() {
?>
<div class="wrap">
    <h2><?php _e('Impressions Trends', 'monetize'); ?></h2>

    <form id="monetize-impressions-trends" method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <?php
        $list_table = new Monetize_Impressions_List_Table();
        $list_table->prepare_impressions_trends();
        $list_table->display_impressions_trends();
        ?>
    </form>
</div>
<?php
    }

    public function clicks_trends_html() {
?>
<div class="wrap">
    <h2><?php _e('Clicks Trends', 'monetize'); ?></h2>

    <form id="monetize-impressions-trends" method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <?php
        $list_table = new Monetize_Clicks_List_Table();
        $list_table->prepare_clicks_trends();
        $list_table->display_clicks_trends();
        ?>
    </form>
</div>
<?php
    }

    public function all_impressions_html() {
?>
<div class="wrap">
    <h2><?php _e('Impressions', 'monetize'); ?></h2>

    <form id="monetize-impressions-list" method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <?php
        $list_table = new Monetize_Impressions_List_Table();
        $list_table->prepare_items();
        $list_table->display();
        ?>
    </form>
</div>
<?php
    }

    public function all_clicks_html() {
?>
<div class="wrap">
    <h2><?php _e('Clicks', 'monetize'); ?></h2>

    <form id="monetize-impressions-list" method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <?php
        $list_table = new Monetize_Clicks_List_Table();
        $list_table->prepare_items();
        $list_table->display();
        ?>
    </form>
</div>
<?php
    }

    public function all_units_html() {
        if(current_user_can(self::admin_cap) &&
                isset($_GET['monetize-unit-id']) &&
                is_numeric($_GET['monetize-unit-id'])) {
            // Edit
            global $wpdb;
            $unit_id = $_GET['monetize-unit-id'];

            $units_table_name = $wpdb->prefix . 'monetize_units';

            $select_sql =
            'SELECT
                *
            FROM
                '.$units_table_name.'
            WHERE
                unit_id = '.esc_sql($unit_id).'';

            $unit = $wpdb->get_row($select_sql, ARRAY_A);

            if(!empty($unit) && is_array($unit)) {
                $this->new_edit_unit_html('edit', $unit, $unit_id);
            }else{
                $notices = array();

                $notices[] = array('class' => 'error', 'message' => __('Unfortunately unit you have requested does not exist.', 'monetize'));
                $notices[] = array('class' => 'error', 'message' => sprintf(__('<a href="%s">&larr; Back to Units</a>', 'monetize'), admin_url('admin.php?page=monetize-units')));

                $this->edit_unit_404_html($notices);
            }
        } else {
            ?>
            <div class="wrap">

                <h2>
                    <?php _e('Units', 'monetize'); ?>
                    <?php if(current_user_can(self::admin_cap)): ?>
                    <a class="add-new-h2" href="<?php echo admin_url('admin.php?page=monetize-units-new') ?>"><?php _e('Add New', 'monetize') ?></a>
                    <?php endif; ?>
                </h2>

                <form id="monetize-units-list" method="get">
                    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
                    <?php
                    $list_table = new Monetize_Units_List_Table();

                    $list_table->prepare_items();
                    $list_table->display();
                    ?>
                </form>
            </div>
            <?php
        }
    }

    public function new_unit_html() {
        $this->new_edit_unit_html('new');
    }

    public function options_validate($input) {
        $validation_errors = array();
        global $wp_version;

        if(!empty($validation_errors) && version_compare($wp_version, '3.0', '>=')) {
            foreach ($validation_errors as $error) {
                add_settings_error($error['setting'], $error['code'], $error['title'].' '.$error['message']);
            }
        }

        return $input;
    }

    public function update_db_check() {
        // Test is db update necessary
        if ($this->db_version != self::default_db_version) {
            $this->install();
        }
    }

    public function text_domain() {
        load_plugin_textdomain('monetize', false, dirname($this->basename) . '/languages/');
    }

    public function add_options_page() {
        add_options_page('Monetize', 'Monetize', self::admin_cap, __FILE__, array($this, 'options_page'));
        add_filter('plugin_action_links', array($this, 'action_links'), 10, 2);
    }

    public function action_links($links, $file) {
        if ($file == $this->basename) {
            $settings_link = '<a href="' . get_admin_url(null, 'admin.php?page='.$this->basename) . '">'.__('Settings', 'monetize').'</a>';
            $links[] = $settings_link;
        }

        return $links;
    }

    public function plugin_meta($links, $file) {
        if ($file == $this->basename) {
            return array_merge(
                $links,
                array( '<a href="'.self::donate_link.'">'.__('Donate', 'quick-chat').'</a>' )
            );
        }
        return $links;
    }

    public function options_page() {
?>
    <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2><?php echo 'Monetize' ?></h2>
        <form action="options.php" method="post">
        <?php settings_fields('monetize_options'); ?>
        <?php do_settings_sections(__FILE__); ?>
        <p class="submit">
            <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
        </p>
        </form>
    </div>
<?php
    }

    public function settings_init() {
        register_setting('monetize_options', 'monetize_options', array($this, 'options_validate'));

        add_settings_section('donate_section', __('Donating', 'monetize'), array($this, 'settings_section_donate'), __FILE__);
        add_settings_section('version_section', __('Version information', 'monetize'), array($this, 'settings_section_version'), __FILE__);
        add_settings_section('general_section', __('General options:', 'monetize'), array($this, 'settings_section_general'), __FILE__);
        add_settings_section('appearance_section', __('Appearance options','monetize'), array($this, 'settings_section_appearance'), __FILE__);

        add_settings_field('monetize_paypal', __('Donate using PayPal (sincere thank you for your help):', 'monetize'), array($this, 'settings_field_paypal'), __FILE__, 'donate_section');
        add_settings_field('monetize_version', __('Monetize version:', 'monetize'), array($this, 'settings_field_version'), __FILE__, 'version_section');
        add_settings_field('monetize_faq', __('Monetize FAQ:', 'monetize'), array($this, 'settings_field_faq'), __FILE__, 'version_section');
        add_settings_field('monetize_changelog', __('Monetize changelog:', 'monetize'), array($this, 'settings_field_changelog'), __FILE__, 'version_section');
        add_settings_field('monetize_support_page', __('Monetize support page:', 'monetize'), array($this, 'settings_field_support_page'), __FILE__, 'version_section');

        add_settings_field('monetize_debug_mode', __('Debug mode (enable only when debugging):', 'monetize'), array($this, 'settings_field_debug_mode'), __FILE__, 'general_section');

        add_settings_field('monetize_hide_linkhome', __('Hide "Powered by Monetize" link (big thanks for not hiding it):', 'monetize'), array($this, 'settings_field_hide_linkhome'), __FILE__, 'appearance_section');
    }

    public function settings_section_donate() {
        echo '<p>';
        echo __('If you find Monetize useful you can donate to help it\'s development:', 'monetize');
        echo '</p>';
    }

    public function settings_section_version() {
        echo '<p>';
        echo __('Here you can review version information:', 'monetize');
        echo '</p>';
    }

    public function settings_section_general() {
        echo '<p>';
        echo __('Here you can control all general options:', 'monetize');
        echo '</p>';
    }

    public function settings_section_appearance() {
        echo '<p>';
        echo __('Here you can control all appearance options:', 'monetize');
        echo '</p>';
    }

    public function settings_field_debug_mode() {
        echo '<input id="monetize_debug_mode" name="monetize_options[debug_mode]" type="checkbox" value="1" ';
        if(isset($this->options['debug_mode'])) echo 'checked="checked"';
        echo '/>';
    }

    public function settings_field_hide_linkhome() {
        echo '<input id="monetize_hide_linkhome" name="monetize_options[hide_linkhome]" type="checkbox" value="1" ';
        if(isset($this->options['hide_linkhome'])) echo 'checked="checked"';
        echo '/>';
    }

    public function settings_field_faq() {
        echo '<a href="'.self::faq_link.'" target="_blank">'.__('FAQ', 'monetize').'</a>';
    }

    public function settings_field_version() {
        echo self::version;
    }

    public function settings_field_changelog() {
        echo '<a href="'.self::changelog_link.'" target="_blank">'.__('Changelog', 'monetize').'</a>';
    }

    public function settings_field_support_page() {
        echo '<a href="'.self::support_link.'" target="_blank">'.__('Monetize at TechyTalk.info', 'monetize').'</a>';
    }

    public function settings_field_paypal() {
        echo '<a href="'.self::donate_link.'" target="_blank"><img src="'.$this->url.'/img/paypal.gif" /></a>';
    }

    public function numeric_to_currency($number, $sign = true) {
        $number = round($number, 2);

        if($sign == true)
            return money_format('%.2n', $number);
        else
            return $number;
    }

    public function pixels_to_numeric($pixels) {
        $pixels = preg_replace('/[^0-9]/', '', $pixels);

        return intval($pixels);
    }

    public function numeric_to_pixels($number, $sign = true) {
        $number = intval($number);

        if($sign == true)
            return sprintf('%dpx', $number);
        else
            return $number;
    }

    public function currency_to_numeric($currency) {
        $currency = preg_replace('/([^0-9\\.\\-])/i', '', $currency);

        return round($currency, 2);
    }

    protected function new_impression($unit_id, $url = null, $referer = null) {
        global $wpdb;

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';

        if(empty($_SERVER['HTTP_X_FORWARD_FOR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }  else {
            $ip = $_SERVER['HTTP_X_FORWARD_FOR'];
        }

        if(empty($_SERVER['HTTP_USER_AGENT'])) {
            $agent = 'NULL';
        } else {
            $agent = '"'.esc_sql($_SERVER['HTTP_USER_AGENT']).'"';
        }

        if(empty($url)) {
            $url = 'NULL';
        } else {
            $url = '"'.esc_sql($url).'"';
        }

        if(empty($referer)) {
            $referer = 'NULL';
        } else {
            $referer = '"'.esc_sql($referer).'"';
        }

        $insert_sql =
        'INSERT INTO
            '.$impressions_table_name.'
        SET
            impression_unit_id = '.esc_sql($unit_id).',
            impression_ip = "'.esc_sql($ip).'",
            impression_created = UTC_TIMESTAMP(),
            impression_url = '.$url.',
            impression_agent = '.$agent.',
            impression_referer = '.$referer;

        $return = $wpdb->query($insert_sql);
        if($return === false) {
            return false;
        } else {
            return $wpdb->insert_id;
        }
    }

    protected function new_click($impression_id) {
        global $wpdb;

        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';

        $insert_sql =
        'INSERT INTO
            '.$clicks_table_name.'
        SET
            click_impression_id = '.esc_sql($impression_id).',
            click_created = UTC_TIMESTAMP()';

        $return = $wpdb->query($insert_sql);
        if($return === false) {
            return false;
        } else {
            return $wpdb->insert_id;
        }
    }

    protected function new_edit_unit($values, $unit_id = null) {
        global $wpdb;

        $units_table_name = $wpdb->prefix . 'monetize_units';

        $values = esc_sql($values);

        if($unit_id === null) {
            $insert_sql =
            'INSERT INTO
                '.$units_table_name.'
            SET
                unit_zone_id = '.$values['unit_zone_id'].',
                unit_user_id = '.$values['unit_user_id'].',
                unit_created = UTC_TIMESTAMP(),
                unit_price = '.$values['unit_price'].',
                unit_cpm = '.$values['unit_cpm'].',
                unit_limit = '.intval(($values['unit_price']/$values['unit_cpm']) * 1000).',
                unit_name = "'.$values['unit_name'].'",
                unit_html = "'.$values['unit_html'].'",
                unit_mode = '.$values['unit_mode'];

            $return = $wpdb->query($insert_sql);

            if($return === false)
                return false;
            else
                return $wpdb->insert_id;
        } else {
            $update_sql =
            'UPDATE
                '.$units_table_name.'
            SET
                unit_zone_id = '.$values['unit_zone_id'].',
                unit_user_id = '.$values['unit_user_id'].',
                unit_price = '.$values['unit_price'].',
                unit_cpm = '.$values['unit_cpm'].',
                unit_limit = '.intval(($values['unit_price']/$values['unit_cpm']) * 1000).',
                unit_name = "'.$values['unit_name'].'",
                unit_html = "'.$values['unit_html'].'",
                unit_mode = '.$values['unit_mode'].'
            WHERE
                unit_id = '.esc_sql($unit_id).'';

            $return = $wpdb->query($update_sql);

            if($return === false)
                return false;
            else
                return $wpdb->insert_id;
        }
    }

    protected function new_edit_zone($values, $zone_id = null) {
        global $wpdb;

        $zones_table_name = $wpdb->prefix . 'monetize_zones';

        $values = esc_sql($values);

        if($zone_id == null) {
            $insert_sql =
            'INSERT INTO
                '.$zones_table_name.'
            SET
                zone_created = UTC_TIMESTAMP(),
                zone_name = "'.$values['zone_name'].'",
                zone_width = "'.$values['zone_width'].'",
                zone_height = "'.$values['zone_height'].'",
                zone_css = "'.$values['zone_css'].'"';

            $return = $wpdb->query($insert_sql);

            if($return === false)
                return false;
            else
                return $wpdb->insert_id;
        } else {
            $update_sql =
            'UPDATE
                '.$zones_table_name.'
            SET
                zone_name = "'.$values['zone_name'].'",
                zone_width = "'.$values['zone_width'].'",
                zone_height = "'.$values['zone_height'].'",
                zone_css = "'.$values['zone_css'].'"
            WHERE
                zone_id = '.esc_sql($zone_id).'';

            $return = $wpdb->query($update_sql);

            if($return === false)
                return false;
            else
                return $wpdb->insert_id;
        }
    }

    protected function get_random_unit($zone_id) {
        global $wpdb;

        $zones_table_name = $wpdb->prefix . 'monetize_zones';
        $units_table_name = $wpdb->prefix . 'monetize_units';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';

        $select_sql =
        'SELECT
            unit_id,
            unit_html,
            zone_width,
            zone_height,
            zone_css
        FROM
            '.$units_table_name.'
        INNER JOIN '.$zones_table_name.'
            ON unit_zone_id = zone_id
        WHERE
            zone_id = '.esc_sql($zone_id).'
                AND
            unit_mode != 2
                AND
            (unit_mode = 1
                OR
            (SELECT
                COUNT(*)
            FROM
                '.$impressions_table_name.'
            WHERE
                impression_unit_id = unit_id) < unit_limit)
        ORDER BY
            RAND()
        LIMIT 1';

        return $wpdb->get_row($select_sql, ARRAY_A);
    }

    protected function get_client_users() {
        global $wpdb;

        $wp_roles = get_editable_roles();

        // Checj for self::admin_cap or self::client_cap added to users
        $search_fors = array(self::admin_cap, self::client_cap);

        // Check for self::admin_cap or self::client_cap added to roles
        foreach ($wp_roles as $role_name => $role_data) {
            if(!empty($role_data['capabilities'][self::admin_cap]) ||
                !empty($role_data['capabilities'][self::client_cap])) {
                if(!isset($search_fors[$role_name])) {
                    $search_fors[] = $role_name;
                }
            }
        }

        // Remove possible duplicates
        array_unique($search_fors);

        $where = '';
        $last = end($search_fors);
        foreach ($search_fors as $search_for) {
            $where .= 'meta_key = "wp_capabilities"
                AND meta_value LIKE "%'.esc_sql($search_for).'%"';
            if($search_for !== $last) {
                $where .= ' OR ';
            }
        }

        $select_sql =
        'SELECT
            user_id,
            user_login
        FROM '.$wpdb->users.'
        INNER JOIN '.$wpdb->usermeta.'
            ON '.$wpdb->users.'.ID = '.$wpdb->usermeta.'.user_id
        WHERE '.$where;

        return $wpdb->get_results($select_sql, ARRAY_A);

    }

    protected function get_ip_data() {
        if(empty($this->ip_data)) {
            if(!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URL'])) {
                $this->ip_data['url'] = (is_ssl() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URL'];
            } else {
                $this->ip_data['url'] = null;
            }

            if(!empty($_SERVER['HTTP_REFERER'])) {
                $this->ip_data['referer'] = $_SERVER['HTTP_REFERER'];
            } else {
                $this->ip_data['referer'] = null;
            }
        }

        return $this->ip_data;
    }

    protected function notices_html($notices) {
        $errors = array();
        $updates = array();

        foreach ($notices as $notice) {
            if($notice['class'] == 'error') {
                $errors[] = $notice['message'];
            }else if($notice['class'] == 'updated') {
                $updates[] = $notice['message'];
            }
        }

        if(!empty($errors)) {
            echo '<div class="error">';
            foreach ($errors as $error) {
                echo '<p>'.$error.'</p>';
            }
            echo '</div>';
        }

        if(!empty($updates)) {
            echo '<div class="updated">';
            foreach ($updates as $update) {
                echo '<p>'.$update.'</p>';
            }
            echo '</div>';
        }
    }

    protected function edit_unit_404_html($notices) {
?>
<div class="wrap">
    <h2><?php _e('Edit Unit', 'monetize'); ?></h2>
    <?php $this->notices_html($notices); ?>
</div>
<?php
    }

    protected function edit_zone_404_html($notices) {
?>
<div class="wrap">
    <h2><?php _e('Edit Zone', 'monetize'); ?></h2>
    <?php $this->notices_html($notices); ?>
</div>
<?php
    }

    /**
     *
     * @param string $mode 'new' or 'edit'
     * @param array $init_values Default values for edit zone mode
     * @param int $zone_id Zone id for edit mode
     */
    protected function new_edit_zone_html($mode = 'new', $init_values = null, $zone_id = null) {
        if($mode === 'new') {
            $init_values = array(
                'zone_name' => '',
                'zone_width' => '',
                'zone_height' => '',
                'zone_css' => ''
            );
        }

        $notices = array();

        if(isset($_POST['monetize-zone-name'])) {
            $zone_name = stripslashes(trim($_POST['monetize-zone-name']));

            $zone_width = stripslashes(trim($_POST['monetize-zone-width']));
            $zone_width_numeric = $this->pixels_to_numeric($zone_width);
            $zone_width_pixels = $this->numeric_to_pixels($zone_width_numeric);

            $zone_height = stripslashes(trim($_POST['monetize-zone-height']));
            $zone_height_numeric = $this->pixels_to_numeric($zone_height);
            $zone_height_pixels = $this->numeric_to_pixels($zone_height_numeric);

            $zone_css = stripslashes(trim($_POST['monetize-zone-css']));

            if ($zone_name != '' &&
                    $zone_width != '' &&
                    $zone_width_numeric > 0 &&
                    $zone_width_numeric < 10000 &&

                    $zone_height !== '' &&
                    $zone_height_numeric > 0 &&
                    $zone_height_numeric < 10000) {
                if(($this->new_edit_zone(            array(
                        'zone_name' => $zone_name,
                        'zone_width' => $zone_width_numeric,
                        'zone_height' => $zone_height_numeric,
                        'zone_css' => $zone_css
                    ), $zone_id)) === false) {
                    if($mode === 'new') {
                        $notices[] = array('class' => 'error', 'message' => __('Could not update zone.', 'monetize'));
                    } else {
                        $notices[] = array('class' => 'error', 'message' => __('Could not update zone.', 'monetize'));
                    }
                    $values =
                    array(
                        'zone_name' => $zone_name,
                        'zone_width' => $zone_width_pixels,
                        'zone_height' => $zone_height_pixels,
                        'zone_css' => $zone_css
                    );
                }else{
                    if($mode === 'new') {
                        $notices[] = array('class' => 'updated', 'message' => __('Zone created.', 'monetize'));
                    } else {
                        $notices[] = array('class' => 'updated', 'message' => __('Zone updated.', 'monetize'));
                    }
                    $notices[] = array('class' => 'updated', 'message' => sprintf(__('<a href="%s">&larr; Back to Zones</a>', 'monetize'), admin_url('admin.php?page=monetize-zones')));
                    $values =
                    array(
                        'zone_name' => $zone_name,
                        'zone_width' => $zone_width_pixels,
                        'zone_height' => $zone_height_pixels,
                        'zone_css' => $zone_css
                    );
                }
            }  else {
                $values =
                array(
                    'zone_name' => $zone_name,
                    'zone_width' => $zone_width_pixels,
                    'zone_height' => $zone_height_pixels,
                    'zone_css' => $zone_css
                );

                if ($zone_name == '' ) {
                    $notices[] = array('class' => 'error', 'message' => __('Please enter a zone name.', 'monetize'));
                }

                if ($zone_width == '' || $zone_width_numeric <= 0 || $zone_width_numeric >= 10000) {
                    $values['zone_width'] = $zone_width;
                    if ($zone_width  == '') {
                        $notices[] = array('class' => 'error', 'message' => __('Please enter a zone width.', 'monetize'));
                    }else if ($zone_width_numeric <= 0) {
                        $notices[] = array('class' => 'error', 'message' => __('Zone width must be positive and numeric.', 'monetize'));
                    }elseif ($zone_width_numeric >= 10000) {
                        $notices[] = array('class' => 'error', 'message' => __('Zone width must be less than 10000.', 'monetize'));
                    }
                }

                if ($zone_height == '' || $zone_height_numeric <= 0 || $zone_height_numeric >= 10000) {
                    $values['zone_height'] = $zone_height;
                    if ($zone_height  == '') {
                        $notices[] = array('class' => 'error', 'message' => __('Please enter a zone height.', 'monetize'));
                    }else if ($zone_height_numeric <= 0) {
                        $notices[] = array('class' => 'error', 'message' => __('Zone height must be positive and numeric.', 'monetize'));
                    }elseif ($zone_height_numeric >= 10000) {
                        $notices[] = array('class' => 'error', 'message' => __('Zone height must be less than 10000.', 'monetize'));
                    }
                }
            }
        } else {
            $values = $init_values;
            if($mode == 'edit') {
                $values['zone_width'] = $this->numeric_to_pixels($values['zone_width']);
                $values['zone_height'] = $this->numeric_to_pixels($values['zone_height']);
            }
        }

        if($mode === 'new') {
            $title = __('Add New Zone', 'monetize');
            $description = __('Create a brand new zone.', 'monetize');
            $button_value = __('Add New Zone', 'monetize');
        } else {
            $title = __('Edit Zone', 'monetize');
            $description = __('Edit existing zone.', 'monetize');
            $button_value = __('Update Zone', 'monetize');
        }

?>
<div class="wrap">
    <h2><?php echo $title; ?></h2>
    <?php $this->notices_html($notices); ?>
    <p><?php echo $description; ?></p>
    <form id="monetize-zone-new-edit-form" name ="monetize-zone-new-edit-form" method="post" action="">
        <table class="form-table">
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-zone-name"><?php _e('Name', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <input style="width: 25em;" type="text" value="<?php echo htmlspecialchars($values['zone_name'], ENT_QUOTES) ?>" id="monetize-zone-name" name="monetize-zone-name" size="25"/>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-zone-width"><?php _e('Width in pixels', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <input style="width: 10em;" type="text" value="<?php echo htmlspecialchars($values['zone_width'], ENT_QUOTES) ?>" id="monetize-zone-width" name="monetize-zone-width" size="10"/>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-zone-height"><?php _e('Height in pixels', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <input style="width: 10em;" type="text" value="<?php echo htmlspecialchars($values['zone_height'], ENT_QUOTES) ?>" id="monetize-zone-height" name="monetize-zone-height" size="10"/>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-zone-css"><?php _e('CSS', 'monetize') ?></label>
                </th>
                <td>
                    <textarea style="width: 50em; height: 10em;" cols="30" rows="5" id="monetize-zone-css" name="monetize-zone-css"><?php echo htmlspecialchars($values['zone_css'], ENT_QUOTES) ?></textarea>
                    <br>
                    <p class="description"><?php _e('List of inline CSS rules for this zone in format <code>margin: 0 auto; border: 1px solid black;</code>.', 'monetize'); ?></p>
                </td>
            </tr>
        </table>

    <p class="submit">
        <input type="submit" value="<?php echo $button_value; ?>" class="button-primary" id="monetize-zone-update" name="monetize-zone-new-edit" />
    </p>
    </form>
</div>
<?php
    }

    /**
     *
     * @param string $mode 'new' or 'edit'
     * @param array $init_values Default values for edit unit mode
     * @param int $unit_id Unit id for edit mode
     */
    protected function new_edit_unit_html($mode = 'new', $init_values = null, $edit_unit_id = null) {
        if($mode === 'new') {
            $init_values = array(
                'unit_name' => '',
                'unit_price' => '',
                'unit_cpm' => '',
                'unit_html' => '',
                'unit_zone_id' => 0,
                'unit_mode' => 0,
                'unit_user_id' => -1
            );
        }

        $notices = array();

        if( isset($_POST['monetize-unit-name']) &&
            isset($_POST['monetize-unit-price']) &&
            isset($_POST['monetize-unit-cpm']) &&
            isset($_POST['monetize-unit-html']) &&
            isset($_POST['monetize-unit-zone-id']) &&
            isset($_POST['monetize-unit-user-id'])) {

            $unit_zone_id = $_POST['monetize-unit-zone-id'];
            $unit_user_id = $_POST['monetize-unit-user-id'];
            $unit_name = stripslashes(trim($_POST['monetize-unit-name']));

            $unit_price = stripslashes(trim($_POST['monetize-unit-price']));
            $unit_price_numeric = $this->currency_to_numeric($unit_price);
            $unit_price_currency = $this->numeric_to_currency($unit_price_numeric);

            $unit_cpm = stripslashes(trim($_POST['monetize-unit-cpm']));
            $unit_cpm_numeric = $this->currency_to_numeric($unit_cpm);
            $unit_cpm_currency = $this->numeric_to_currency($unit_cpm_numeric);

            $unit_html = stripslashes(trim($_POST['monetize-unit-html']));

            $unit_mode = $_POST['monetize-unit-mode'];

            if (    $unit_user_id > 0 &&
                    $unit_zone_id > 0 &&
                    $unit_name != '' &&

                    $unit_price != '' &&
                    $unit_price_numeric > 0 &&
                    $unit_price_numeric < 100000000 &&

                    $unit_cpm != '' &&
                    $unit_cpm_numeric > 0 &&
                    $unit_cpm_numeric < 100000000) {
                if(($this->new_edit_unit(array(
                        'unit_name' => $unit_name,
                        'unit_price' => $unit_price_numeric,
                        'unit_cpm' => $unit_cpm_numeric,
                        'unit_html' => $unit_html,
                        'unit_zone_id' => $unit_zone_id,
                        'unit_user_id' => $unit_user_id,
                        'unit_mode' => $unit_mode
                    ), $edit_unit_id)) === false) {
                    if($mode === 'new') {
                        $notices[] = array('class' => 'error', 'message' => __('Could not create unit.', 'monetize'));
                    } else {
                        $notices[] = array('class' => 'error', 'message' => __('Could not update unit.', 'monetize'));
                    }

                    $values = array(
                        'unit_name' => $unit_name,
                        'unit_price' => $unit_price_currency,
                        'unit_cpm' => $unit_cpm_currency,
                        'unit_html' => $unit_html,
                        'unit_zone_id' => $unit_zone_id,
                        'unit_user_id' => $unit_user_id,
                        'unit_mode' => $unit_mode
                    );
                }else{
                    if($mode === 'new') {
                        $notices[] = array('class' => 'updated', 'message' => __('Unit created.', 'monetize'));
                    } else {
                        $notices[] = array('class' => 'updated', 'message' => __('Unit updated.', 'monetize'));
                    }
                    $notices[] = array('class' => 'updated', 'message' => sprintf(__('<a href="%s">&larr; Back to Units</a>', 'monetize'), admin_url('admin.php?page=monetize-units')));
                    $values = array(
                        'unit_name' => $unit_name,
                        'unit_price' => $unit_price_currency,
                        'unit_cpm' => $unit_cpm_currency,
                        'unit_html' => $unit_html,
                        'unit_zone_id' => $unit_zone_id,
                        'unit_user_id' => $unit_user_id,
                        'unit_mode' => $unit_mode
                    );
                }
            }  else {
                $values =
                array(
                    'unit_name' => $unit_name,
                    'unit_price' => $unit_price_currency,
                    'unit_cpm' => $unit_cpm_currency,
                    'unit_html' => $unit_html,
                    'unit_zone_id' => $unit_zone_id,
                    'unit_user_id' => $unit_user_id,
                    'unit_mode' => $unit_mode
                );

                if ($unit_zone_id < 1 ) {
                    $notices[] = array('class' => 'error', 'message' => __('Please select owner.', 'monetize'));
                }

                if ($unit_zone_id < 1 ) {
                    $notices[] = array('class' => 'error', 'message' => __('Please select zone.', 'monetize'));
                }

                if ($unit_name == '' ) {
                    $notices[] = array('class' => 'error', 'message' =>__('Please enter a unit name.', 'monetize'));
                }

                if($unit_price  == '' || $unit_price_numeric <= 0 || $unit_price_numeric >= 100000000) {
                    $values['unit_price'] = $unit_price;
                    if ($unit_price  == '') {
                        $notices[] = array('class' => 'error', 'message' => __('Please enter a unit price.', 'monetize'));
                    }else if ($unit_price_numeric <= 0) {
                        $notices[] = array('class' => 'error', 'message' => __('Unit price must be positive and numeric.', 'monetize'));
                    }elseif ($unit_price_numeric >= 100000000) {
                        $notices[] = array('class' => 'error', 'message' => __('Unit price must be less than 100000000.', 'monetize'));
                    }
                }

                if($unit_cpm  == '' || $unit_cpm_numeric <= 0 || $unit_cpm_numeric >= 100000000) {
                    $values['unit_cpm'] = $unit_cpm;
                    if ($unit_cpm  == '') {
                        $notices[] = array('class' => 'error', 'message' => __('Please enter a unit cpm.', 'monetize'));
                    }else if ($unit_cpm_numeric <= 0) {
                        $notices[] = array('class' => 'error', 'message' => __('Unit cpm must be positive and numeric.', 'monetize'));
                    }elseif ($unit_cpm_numeric >= 100000000) {
                        $notices[] = array('class' => 'error', 'message' => __('Unit cpm must be less than 100000000.', 'monetize'));
                    }
                }
            }

        }else{
            // First load
            $values = $init_values;
            if($mode == 'edit') {
                $values['unit_price'] = $this->numeric_to_currency($values['unit_price']);
                $values['unit_cpm'] = $this->numeric_to_currency($values['unit_cpm']);
            }
        }

        $zones = $this->get_zones();
        $users = $this->get_client_users();

        if($mode === 'new') {
            $title = __('Add New Unit', 'monetize');
            $description = __('Create a brand new unit.', 'monetize');
            $button_value = __('Add New Unit', 'monetize');
        } else {
            $title = __('Edit Unit', 'monetize');
            $description = __('Edit existing unit.', 'monetize');
            $button_value = __('Update Unit', 'monetize');
        }

        $localeconv = localeconv();
?>
<div class="wrap">
    <h2><?php echo $title; ?></h2>
    <?php $this->notices_html($notices); ?>
    <p><?php echo $description; ?></p>
    <form id="monetize-unit-new-edit-form" name ="monetize-unit-new-form" method="post" action="">
        <table class="form-table">
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-zone-id"><?php _e('Zone', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <select style="width: 25em;" id="monetize-unit-zone-id" name="monetize-unit-zone-id">
                        <option value="0">...</option>
                        <?php foreach ($zones as $contaner): ?>
                        <option value="<?php echo $contaner['zone_id'] ?>" <?php if($values['unit_zone_id'] == $contaner['zone_id']) echo ' selected="selected"'; ?>>
                        <?php
                        printf( '%s (%sx%s)',
                                htmlspecialchars($contaner['zone_name'], ENT_QUOTES),
                                htmlspecialchars($contaner['zone_width'], ENT_QUOTES),
                                htmlspecialchars($contaner['zone_height'], ENT_QUOTES)
                        ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <p class="description"><?php _e('Zone where you want this unit to appear.', 'monetize') ?></p>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-user-id"><?php _e('Owner', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <select style="width: 25em;" id="monetize-unit-user-id" name="monetize-unit-user-id">
                        <option value="0">...</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id'] ?>" <?php if($values['unit_user_id'] == $user['user_id']) echo ' selected="selected"'; ?>>
                        <?php echo htmlspecialchars($user['user_login'], ENT_QUOTES); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <p class="description"><?php _e('Client who paid for this unit.', 'monetize') ?></p>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-name"><?php _e('Name', 'monetize') ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <input style="width: 25em;" type="text" value="<?php echo htmlspecialchars($values['unit_name'], ENT_QUOTES) ?>" id="monetize-unit-name" name="monetize-unit-name" size="25"/>
                    <br>
                    <p class="description"><?php _e('Unit name to help identify it later.', 'monetize') ?></p>
                </td>
            </tr>
            <tr class="form-field">
            <th scope="row">
                <label for="monetize-unit-price"><?php printf(__('Price in %s', 'monetize'), $localeconv['int_curr_symbol']); ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
            </th>
            <td>
                <input style="width: 25em;" type="text" value="<?php echo htmlspecialchars($values['unit_price'], ENT_QUOTES) ?>" id="monetize-unit-price" name="monetize-unit-price" size="25"/>
                <br>
                <p class="description"><?php _e('Amount of money client paid for this unit.', 'monetize') ?></p>
            </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-cpm"><?php printf(__('CPM in %s', 'monetize'), $localeconv['int_curr_symbol']); ?> <span class="description">(<?php _e('required', 'monetize'); ?>)</span></label>
                </th>
                <td>
                    <input style="width: 25em;" type="text" value="<?php echo htmlspecialchars($values['unit_cpm'], ENT_QUOTES) ?>" id="monetize-unit-cpm" name="monetize-unit-cpm" size="25"/>
                    <br>
                    <p class="description"><?php _e('Cost per 1000 impressions for this unit.', 'monetize') ?></p>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-mode"><?php _e('Mode', 'monetize') ?></label>
                </th>
                <td>
                    <input type="radio" value="0" id="monetize-unit-mode" name="monetize-unit-mode" <?php echo ($values['unit_mode'] == 0) ? 'checked="checked"' : ''; ?> /> <span><?php _e('Auto', 'monetize'); ?></span>
                    <br>
                    <p class="description"><?php _e('Unit will stop appearing when it reaches limit.', 'monetize') ?></p>
                    <input type="radio" value="1" id="monetize-unit-mode" name="monetize-unit-mode" <?php echo ($values['unit_mode'] == 1) ? 'checked="checked"' : ''; ?> /> <span><?php _e('Force activated', 'monetize'); ?></span>
                    <br>
                    <p class="description"><?php _e('Unit will appear even when limit has been reached.', 'monetize') ?></p>
                    <input type="radio" value="2" id="monetize-unit-mode" name="monetize-unit-mode" <?php echo ($values['unit_mode'] == 2) ? 'checked="checked"' : ''; ?> /> <span><?php _e('Force deactivated', 'monetize'); ?></span>
                    <br>
                    <p class="description"><?php _e('Unit will not appear even when limit hasn\'t been reached.', 'monetize') ?></p>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row"></th>
                <td>
                    <a href="#" class="monetize-unit-media-open button button-secondary" title="<?php esc_attr_e( 'Click here to open the Media', 'monetize' ); ?>">
                        <span class="wp-media-buttons-icon"></span>
                        <?php _e('Add Media', 'monetize' ); ?>
                    </a>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row">
                    <label for="monetize-unit-html"><?php _e('HTML', 'monetize') ?></label>
                </th>
                <td>
                    <textarea style="width: 50em; height: 20em;" cols="30" rows="5" id="monetize-unit-html" name="monetize-unit-html"><?php echo htmlspecialchars($values['unit_html'], ENT_QUOTES) ?></textarea>
                    <br>
                    <p class="description"><?php _e('HTML code rendered where you place this unit.', 'monetize'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" value="<?php echo $button_value; ?>" class="button-primary" id="monetize-unit-add-edit" name="monetize-unit-add-edit" />
        </p>
    </form>
</div>
<?php
    }

    /**
     * Generate array for use when there aren't any impressions yet.
     *
     * @param int $start_timestamp Start UNIX timestamp
     * @param int $end_timestamp End UNIX timestamp
     * @param string $step Date function accepted string as step
     * @param type $format Date fimctopm accepted format
     * @return array Array in format:
     * array(
     *      array(
     *          'date' => '2000/01/01',
     *          'impressions' => 0
     *      ),
     *      array(
     *          'date' => '2000/01/02',
     *          'impressions' => 0
     *      ),
     *      array(
     *          'date' => '2000/01/03',
     *          'impressions' => 0
     *      ),
     *      .
     *      .
     *      .
     * )
     */
    protected function no_stats($start_timestamp, $end_timestamp, $step = '+1 day', $format = 'd/m/Y' ) {
        $stats = array();

        while($start_timestamp <= $end_timestamp) {
            $stats[] = array('date' => date($format, $start_timestamp), 'impressions' => 0);
            $start_timestamp = strtotime($step, $start_timestamp);
        }

        return $stats;
    }

    protected function install() {
        global $wpdb;

        // Table names
        $units_table_name = $wpdb->prefix . 'monetize_units';
        $zones_table_name = $wpdb->prefix . 'monetize_zones';
        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $clicks_table_name = $wpdb->prefix . 'monetize_clicks';
        $calendar_table_name = $wpdb->prefix . 'monetize_calendar';
        $ints_table_name = $wpdb->prefix . 'monetize_ints';

        // Do all tables exist?
        $units_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$units_table_name.'\'') == $units_table_name) ? 1: 0;
        $zones_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$zones_table_name.'\'') == $zones_table_name) ? 1: 0;
        $impressions_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$impressions_table_name.'\'') == $impressions_table_name) ? 1: 0;
        $clicks_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$clicks_table_name.'\'') == $clicks_table_name) ? 1: 0;
        $ints_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$ints_table_name.'\';') == $ints_table_name) ? 1: 0;
        $calendar_table_exists = ($wpdb->get_var('SHOW TABLES LIKE \''.$calendar_table_name.'\';') == $calendar_table_name) ? 1: 0;

        // wp_users must be on INNODB engine
        $wp_users_engine_check_sql = 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = "'.$wpdb->dbname.'" AND TABLE_NAME = "'.$wpdb->users.'";';

        $wp_users_enqine = $wpdb->get_var($wp_users_engine_check_sql);

        if($wp_users_enqine && $wp_users_enqine != 'InnoDB') {
            $wp_users_engine_alter = 'ALTER TABLE '.$wpdb->users.' ENGINE = InnoDB';
            $wpdb->query($wp_users_engine_alter);
        }

        if($ints_table_exists == 0) {
            $sql_ints =
            'CREATE TABLE '.$ints_table_name.' (
                i TINYINT
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci;';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_ints);

            $sql_ints_insert = 'INSERT INTO '.$ints_table_name.' VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);';
            $wpdb->query($sql_ints_insert);
        } else{
            // Future upgrades
        }

        if($calendar_table_exists == 0) {
            $sql_ints =
            'CREATE TABLE '.$calendar_table_name.' (
                dt DATE NOT NULL PRIMARY KEY
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci;';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_ints);

            $calendar_start = '2000-01-01';
            $calendar_end = '2040-12-31';
            $sql_calendar_insert = 'INSERT INTO '.$calendar_table_name.' (dt)
                SELECT
                    DATE("'.$calendar_start.'") + INTERVAL a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i DAY
                FROM '.$ints_table_name.' a JOIN '.$ints_table_name.' b JOIN '.$ints_table_name.' c JOIN '.$ints_table_name.' d JOIN '.$ints_table_name.' e
                WHERE (a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i) <= (DATEDIFF("'.$calendar_end.'", "'.$calendar_start.'"))
                ORDER BY 1;';
            $wpdb->query($sql_calendar_insert);
        } else{
            // Future upgrades
        }

        // If this is first run create table, else upgrade if necessary
        if($zones_table_exists == 0) {
            $sql_units =
            'CREATE TABLE IF NOT EXISTS '.$zones_table_name.' (
                zone_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                zone_created DATETIME NOT NULL,
                zone_name VARCHAR(60) NOT NULL,
                zone_width INT UNSIGNED NOT NULL,
                zone_height INT UNSIGNED NOT NULL,
                zone_css TEXT NOT NULL,
                KEY(zone_created),
                PRIMARY KEY(zone_id)
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_units);
        } else{
            // Future upgrades
        }

        // If this is first run create table, else upgrade if necessary
        if($units_table_exists == 0) {
            $sql_units =
            'CREATE TABLE IF NOT EXISTS '.$units_table_name.' (
                unit_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                unit_user_id BIGINT(20) UNSIGNED NOT NULL,
                unit_zone_id INT UNSIGNED NOT NULL,
                unit_created DATETIME NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                unit_cpm DECIMAL(10,2) NOT NULL,
                unit_limit INT UNSIGNED NOT NULL,
                unit_name VARCHAR(60) NOT NULL,
                unit_html TEXT NOT NULL,
                unit_mode TINYINT(1) UNSIGNED NOT NULL,
                KEY(unit_limit),
                KEY(unit_created),
                KEY(unit_mode),
                PRIMARY KEY(unit_id),
                FOREIGN KEY(unit_user_id)
                    REFERENCES
                        '.$wpdb->users.'(ID)
                    ON DELETE
                        CASCADE
                    ON UPDATE
                        CASCADE,
                FOREIGN KEY(unit_zone_id)
                    REFERENCES
                        '.$zones_table_name.'(zone_id)
                    ON DELETE
                        CASCADE
                    ON UPDATE
                        CASCADE
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_units);
        } else{
            // Future upgrades
        }

        if($impressions_table_exists == 0) {
            $sql_impressions =
            'CREATE TABLE IF NOT EXISTS '.$impressions_table_name.' (
                impression_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                impression_unit_id INT UNSIGNED NOT NULL,
                impression_ip VARCHAR(39) NOT NULL,
                impression_created DATETIME NOT NULL,
                impression_url TEXT NULL,
                impression_agent TEXT NULL,
                impression_referer TEXT NULL,
                KEY(impression_created),
                PRIMARY KEY(impression_id),
                FOREIGN KEY(impression_unit_id)
                    REFERENCES
                        '.$units_table_name.'(unit_id)
                    ON DELETE
                        CASCADE
                    ON UPDATE
                        CASCADE
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_impressions);

        } else{
            // Future upgrades
        }

        if($clicks_table_exists == 0) {
            $sql_clicks =
            'CREATE TABLE IF NOT EXISTS '.$clicks_table_name.' (
                click_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                click_impression_id INT UNSIGNED NOT NULL,
                click_created DATETIME NOT NULL,
                KEY(click_created),
                PRIMARY KEY(click_id),
                FOREIGN KEY(click_impression_id)
                    REFERENCES
                        '.$impressions_table_name.'(impression_id)
                    ON DELETE
                        CASCADE
                    ON UPDATE
                        CASCADE
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8, COLLATE utf8_general_ci';

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_clicks);

        } else{
            // Future upgrades
        }

        // Update options and db version
        update_option('monetize_options', $this->options);
        update_option('monetize_db_version', self::default_db_version);
    }

    protected function log($title, $code = null, $message = null) {
        if(isset($this->options['debug_mode']) || (defined('WP_DEBUG') && WP_DEBUG)) {
            $log_file_append = '['.gmdate('D, d M Y H:i:s \G\M\T').'] ' . $title;

            if($code !== null) {
               $log_file_append .= ', code: ' . $code;
            }

            if($message !== null) {
               $log_file_append .= ', message: ' . $message;
            }
            file_put_contents($this->log_file, $log_file_append . "\n", FILE_APPEND);
        }
    }
}
global $monetize;
$monetize = new Monetize();

require_once(dirname(__FILE__) . '/class-monetize-clicks-list-table.php');
require_once(dirname(__FILE__) . '/class-monetize-impressions-list-table.php');
require_once(dirname(__FILE__) . '/class-monetize-units-list-table.php');
require_once(dirname(__FILE__) . '/class-monetize-zones-list-table.php');
?>
