<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Monetize_Units_List_Table extends WP_List_Table {
    protected $start_timestamp = 0;
    protected $end_timestamp = 2147483647;
    protected $user_id = null;
    protected $date_format;
    protected $time_format;
    protected $gmt_offset;

    function __construct(){
        parent::__construct( array(
            'singular'  => __('unit', 'monetize'),
            'plural'    => __('units', 'monetize'),
            'ajax'      => false
        ) );

        $this->gmt_offset = get_option('gmt_offset') * 3600;

        $this->prepare_filter();
    }

    public function display() {
        $this->filter_html();

        parent::display();
    }

    function column_default($item, $column_name){
        global $monetize;

        $this->date_format = get_option('date_format');
        $this->time_format = get_option('time_format');

        switch($column_name){
            case 'unit_name':
                if(current_user_can(Monetize::admin_cap)) {
                    $actions = array(
                        'delete'    => sprintf('<a href="?page=%s&action=%s&unit_id=%s">'.__('Delete', 'monetize').'</a>',$_REQUEST['page'],'delete',$item['unit_id']),
                        'edit'    => sprintf('<a href="?page=monetize-units&monetize-unit-id=%s">'.__('Edit', 'monetize').'</a>',$item['unit_id'])
                    );

                    $unit_name_link = '<a href="?page=monetize-units&monetize-unit-id='.$item['unit_id'].'">'.$item['unit_name'].'</a>';

                    return sprintf('<strong>%1$s</strong> %2$s',
                        $unit_name_link,
                        $this->row_actions($actions)
                    );
                } else {
                    return '<strong>'.$item['unit_name'].'</strong>';
                }
                break;
            case 'unit_user_login':
                if(current_user_can(Monetize::admin_cap)) {
                    return '<a href="'.admin_url('user-edit.php?user_id='.$item['unit_user_id']).'">'.$item['unit_user_login'].'</a>';
                } else {
                    return $item['unit_user_login'];
                }
            case 'zone_name':
                if(current_user_can(Monetize::admin_cap)) {
                    return '<a href="?page=monetize-zones&monetize-zone-id='.$item['zone_id'].'">'.$item['zone_name'].'</a>';
                } else {
                    return $item['zone_name'];
                }
                break;
            case 'unit_created':
                return date_i18n($this->date_format.' - '.$this->time_format, strtotime($item['unit_created'].' GMT') + $this->gmt_offset);
            case 'unit_price':
                return $monetize->numeric_to_currency($item['unit_price']);
                break;
            case 'unit_cpm':
                return $monetize->numeric_to_currency($item['unit_cpm']);
                break;
            case 'unit_limit':
                if($item['unit_mode'] == 1) {
                    return __('No limit', 'monetize');
                } else if($item['unit_mode'] == 2) {
                    return 0;
                } else {
                    return $item['unit_limit'];
                }
                break;
            case 'impressions_count':
                return $item['impressions_count'];
                break;
            case 'clicks_count':
                return $item['clicks_count'];
                break;
            case 'unit_ctr':
                return sprintf('%s%%', number_format_i18n($item['unit_ctr'] * 100, 2));
                break;
            default:
                return print_r($item,true);
        }
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            'unit_id',
            $item['unit_id']
        );
    }

    function get_columns(){
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'unit_name' => __('Name', 'monetize'),
            'unit_user_login' => __('Owner', 'monetize'),
            'zone_name' => __('Zone', 'monetize'),
            'unit_created' => __('Created', 'monetize'),
            'unit_price' => __('Price', 'monetize'),
            'unit_cpm' => __('CPM', 'monetize'),
            'unit_limit' => __('Limit', 'monetize'),
            'impressions_count' => __('Impressions', 'monetize'),
            'clicks_count' => __('Clicks', 'monetize'),
            'unit_ctr' => __('CTR', 'monetize')
        );

        if(!current_user_can(Monetize::admin_cap)) {
            unset($columns['cb']);
            unset($columns['unit_user_login']);
        }

        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'unit_name' => array('unit_name',false),
            'unit_user_login' => array('unit_user_login', false),
            'zone_name' => array('zone_name',false),
            'unit_created' => array('unit_created',false),
            'unit_price' => array('unit_price',false),
            'unit_cpm' => array('unit_cpm',false),
            'unit_limit' => array('unit_limit',false),
            'impressions_count' => array('impressions_count',false),
            'clicks_count' => array('clicks_count',false),
            'unit_ctr' => array('unit_ctr', false)
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

        $ids = isset($_REQUEST['unit_id']) ? $_REQUEST['unit_id'] : array();

        if($this->current_action() === 'delete'){
            $monetize->delete_units($ids);
        }
    }

    function prepare_items() {
        global $monetize;

        $per_page = 20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'unit_created';
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC';

        $current_page = $this->get_pagenum();

        $start = (($current_page-1)*$per_page);

        $total_items = $monetize->get_units_count(
            $this->start_timestamp,
            $this->end_timestamp,
            $this->user_id
        );

        $this->items = $monetize->get_units(
            $this->start_timestamp,
            $this->end_timestamp,
            $orderby,
            $order,
            $start,
            $per_page,
            $this->user_id
        );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items/$per_page)
        ) );
    }


    protected function prepare_filter() {
        $this->start_timestamp = gmmktime(0, 0, 0, 1, 1, gmdate('Y'));
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
            <input style="width: 15em;" readonly="readonly" type="text" id="monetize-filter-end" name="monetize-filter-end" value="<?php echo gmdate('Y/m/d H:i:s', $this->end_timestamp + $this->gmt_offset) ?>" />
        </td>
        <?php if(current_user_can(Monetize::admin_cap)): ?>
        <td class="monetize-filter-label">
            <label for="monetize-filter-user"><?php _e('Owner:', 'monetize'); ?>
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

        $units_table_name = $wpdb->prefix . 'monetize_units';

        $select_sql =
        'SELECT
            '.$wpdb->users.'.ID as user_id,
            user_login
        FROM '.$wpdb->users.'
        INNER JOIN '.$units_table_name.'
            ON '.$wpdb->users.'.ID = unit_user_id
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