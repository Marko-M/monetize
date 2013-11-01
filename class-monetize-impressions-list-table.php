<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Monetize_Impressions_List_Table extends WP_List_Table {
    protected $start_timestamp = 0;
    protected $end_timestamp = 2147483647;
    protected $user_id = null;
    protected $date_format;
    protected $time_format;
    protected $gmt_offset;
    protected $trends;

    function __construct(){
        parent::__construct( array(
            'singular'  => __('impression', 'monetize'),
            'plural'    => __('impressions', 'monetize'),
            'ajax'      => false
        ) );

        $this->gmt_offset = get_option('gmt_offset') * 3600;

        $this->prepare_filter();
    }

    public function display() {
        $this->filter_html();

        parent::display();
    }

    public function display_impressions_trends() {
        $this->filter_html();

?>
    <div id="monetize-impressions-trends-chart"
         class="monetize-charts"
         data-monetize-impressions-trends='<?php echo json_encode($this->trends) ?>'></div>
<?php
    }

    function column_default($item, $column_name){
        $this->date_format = get_option('date_format');
        $this->time_format = get_option('time_format');

        switch($column_name){
            case 'impression_ip':
                if(current_user_can(Monetize::admin_cap)) {
                    $actions = array(
                        'delete'    => sprintf('<a href="?page=%s&action=%s&impression_id=%s">'.__('Delete', 'monetize').'</a>',$_REQUEST['page'],'delete',$item['impression_id']),
                    );

                    return sprintf('<strong>%1$s</strong> %2$s',
                        $item['impression_ip'],
                        $this->row_actions($actions)
                    );
                } else {
                    return '<strong>'.$item['impression_ip'].'</strong>';
                }
                break;
            case 'unit_user_login':
                if(current_user_can(Monetize::admin_cap)) {
                    return '<a href="'.  admin_url('user-edit.php?user_id='.$item['unit_user_id']).'">'.$item['unit_user_login'].'</a>';
                } else {
                    return $item['unit_user_login'];
                }
            case 'impression_created':
                return date_i18n($this->date_format.' - '.$this->time_format, strtotime($item['impression_created'].' GMT') + $this->gmt_offset);
            case 'impression_referer':
                if(!empty($item['impression_referer'])){
                    $limit = 50;
                    $referer = $item['impression_referer'];
                    if(strlen($referer) > $limit){
                        $referer = substr($referer, 0, $limit).'...';
                    }
                    return '<a href="'.$item['impression_referer'].'" target="_blank">'.$referer.'</a>';
                } else{
                    return __('Unknown', 'monetize');
                }
                break;
            case 'impression_agent':
                if(!empty($item['impression_agent']))
                    return $item['impression_agent'];
                else
                    return __('Unknown', 'monetize');
                break;
            case 'impression_url':
                $limit = 50;
                $url = $item['impression_url'];
                if(strlen($url) > $limit){
                    $url = substr($url, 0, $limit).'...';
                }
                return '<a href="'.$item['impression_url'].'" target="_blank">'.$url.'</a>';
                break;
            case 'unit_name':
                if(current_user_can(Monetize::admin_cap)) {
                    return '<a href="?page=monetize-units&monetize-unit-id='.$item['unit_id'].'">'.$item['unit_name'].'</a>';
                } else {
                    return $item['unit_name'];
                }
                break;
            default:
                return print_r($item,true);
        }
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            'impression_id',
            $item['impression_id']
        );
    }

    function get_columns(){
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'impression_ip' => __('IP', 'monetize'),
            'unit_user_login' => __('Unit Owner', 'monetize'),
            'unit_name' => __('Unit', 'monetize'),
            'impression_created' => __('Created', 'monetize'),
            'impression_url' => __('URL', 'monetize'),
            'impression_agent' => __('Agent', 'monetize'),
            'impression_referer' => __('Referer', 'monetize')
        );

        if(!current_user_can(Monetize::admin_cap)) {
            unset($columns['cb']);
            unset($columns['unit_user_login']);
        }

        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'impression_ip' => array('impression_ip',false),
            'unit_user_login' => array('unit_user_login', false),
            'unit_name' => array('unit_name',false),
            'impression_created' => array('impression_created',false),
            'impression_url' => array('impression_url',false),
            'impression_agent' => array('impression_agent',false),
            'impression_referer' => array('impression_referer',false)
        );

        if(!current_user_can(Monetize::admin_cap)) {
            unset($sortable_columns['unit_user_login']);
        }

        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete'
        );

        if(!current_user_can(Monetize::admin_cap)) {
            unset($actions['delete']);
        }

        return $actions;
    }


    function process_bulk_action() {
        if(!current_user_can(Monetize::admin_cap))
            return false;

        global $monetize;

        $ids = isset($_REQUEST['impression_id']) ? $_REQUEST['impression_id'] : array();

        if($this->current_action() === 'delete') {
            $monetize->delete_impressions($ids);
        }
    }

    public function prepare_impressions_trends() {
        global $monetize;

        $this->trends = $monetize->get_impressions_trends (
            $this->start_timestamp, $this->end_timestamp,
            $this->user_id
        );
    }

    function prepare_items() {
        global $monetize;

        $per_page = 20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'impression_created';
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC';

        $current_page = $this->get_pagenum();

        $start = (($current_page-1)*$per_page);

        $total_items = $monetize->get_impressions_count(
            $this->start_timestamp,
            $this->end_timestamp,
            $this->user_id
        );

        $this->items = $monetize->get_impressions(
            $this->start_timestamp,
            $this->end_timestamp,
            $orderby,
            $order,
            $start, $per_page,
            $this->user_id
        );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items/$per_page)
        ) );
    }


    protected function prepare_filter() {
        $this->start_timestamp = gmmktime(0, 0, 0, gmdate('m'), 1, gmdate('Y'));
        if(isset($_GET['monetize-filter-start'])){
            $maybe_start_timestamp = strtotime(trim($_GET['monetize-filter-start']));
            if($maybe_start_timestamp !== false){
                $this->start_timestamp = $maybe_start_timestamp - $this->gmt_offset;
            }
        }

        $this->end_timestamp = time();
        if(isset($_GET['monetize-filter-end'])){
            $maybe_end_timestamp = strtotime(trim($_GET['monetize-filter-end']));
            if($maybe_end_timestamp !== false){
                $this->end_timestamp = $maybe_end_timestamp - $this->gmt_offset;
            }
        }

        if(current_user_can(Monetize::admin_cap)) {
            $this->user_id = 0;
            if(isset($_GET['monetize-filter-user-id'])){
                $maybe_user_id = trim($_GET['monetize-filter-user-id']);
                if(is_numeric($maybe_user_id)){
                    $this->user_id = intval($maybe_user_id);
                }
            }
        } else {
            $this->user_id = get_current_user_id();
        }
    }

    protected function filter_html() {
        $users = $this->get_client_users();
?>
<table id="monetize-filter">
    <tr>
        <td class="monetize-filter-label">
            <label for="monetize-filter-start"><?php _e('Start:', 'monetize') ?></label>
        </td>
        <td class="monetize-filter-input">
            <input style="width: 15em;" readonly="readonly" type="text" id="monetize-filter-start" name="monetize-filter-start" value="<?php echo gmdate('Y/m/d H:i:s', $this->start_timestamp + $this->gmt_offset) ?>" />
        </td>
        <td class="monetize-filter-label">
            <label for="monetize-filter-end"><?php _e('End:', 'monetize') ?></label>
        </td>
        <td class="monetize-filter-input">
            <input style="width: 15em;" type="text" readonly="readonly" id="monetize-filter-end" name="monetize-filter-end" value="<?php echo gmdate('Y/m/d H:i:s', $this->end_timestamp + $this->gmt_offset) ?>" />
        </td>
        <?php if(current_user_can(Monetize::admin_cap)): ?>
        <td class="monetize-filter-label">
            <label for="monetize-filter-user"><?php _e('Unit Owner:', 'monetize'); ?>
            </label>
        </td>
        <td class="monetize-filter-input">
            <select  style="width: 15em;" id="monetize-filter-user-id" name="monetize-filter-user-id">
                <option value="0">...</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['user_id'] ?>" <?php if($this->user_id == $user['user_id']) echo ' selected="selected"'; ?>>
                <?php echo htmlspecialchars($user['user_login'] , ENT_QUOTES); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
        <?php endif; ?>
        <td class="monetize-filter-input">
            <input type="submit" value="<?php _e('Filter', 'monetize'); ?>" class="button-secondary" />
        </td>
    </tr>
</table>
<?php
    }

    protected function get_client_users() {
        global $wpdb;

        $wpdb->flush();

        $impressions_table_name = $wpdb->prefix . 'monetize_impressions';
        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            '.$wpdb->users.'.ID as user_id,
            user_login
        FROM '.$wpdb->users.'
        INNER JOIN '.$units_table_name.'
            ON unit_user_id = '.$wpdb->users.'.ID
        INNER JOIN '.$impressions_table_name.'
            ON impression_unit_id = unit_id
        WHERE
            unit_created BETWEEN
                "'.esc_sql(gmdate('Y-m-d H:i:s', $this->start_timestamp)).'" AND
                    "'.esc_sql(gmdate('Y-m-d H:i:s', $this->end_timestamp)).'"
        GROUP BY
            unit_id';

        $users = $wpdb->get_results($select_sql, ARRAY_A);

        $wpdb->flush();

        return $users;
    }
}
?>