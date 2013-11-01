<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Monetize_Zones_List_Table extends WP_List_Table {
    protected $start_timestamp = 0;
    protected $end_timestamp = 2147483647;
    protected $user_id = null;
    protected $date_format;
    protected $time_format;
    protected $gmt_offset;

    function __construct(){
        parent::__construct( array(
            'singular'  => __('zone', 'monetize'),
            'plural'    => __('zones', 'monetize'),
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
            case 'zone_name':
                $actions = array(
                    'delete'    => sprintf('<a href="?page=%s&action=%s&zone_id=%s">'.__('Delete', 'monetize').'</a>',$_REQUEST['page'],'delete',$item['zone_id']),
                    'edit'    => sprintf('<a href="?page=monetize-zones&monetize-zone-id=%s">'.__('Edit', 'monetize').'</a>',$item['zone_id'])
                );

                $zone_name_link = '<a href="?page=monetize-zones&monetize-zone-id='.$item['zone_id'].'">'.$item['zone_name'].'</a>';

                return sprintf('<strong>%1$s</strong> %2$s',
                    $zone_name_link,
                    $this->row_actions($actions)
                );
                break;
            case 'zone_width':
                return $monetize->numeric_to_pixels($item['zone_width']);
                break;
            case 'zone_height':
                return $monetize->numeric_to_pixels($item['zone_height']);
                break;
            case 'zone_height':
                return $item['zone_width'];
                break;
            case 'units_count':
                return $item['units_count'];
            case 'zone_created':
                return date_i18n($this->date_format.' - '.$this->time_format, strtotime($item['zone_created'].' GMT') + $this->gmt_offset);
            case 'zone_code':
                return '<code>&lt;?php global $monetize; if(isset($monetize)) {$monetize->show_zone('.$item['zone_id'].');} ?&gt;</code>';
                break;
            default:
                return print_r($item,true);
        }
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            'zone_id',
            $item['zone_id']
        );
    }

    function get_columns(){
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'zone_name' => __('Name', 'monetize'),
            'zone_width' => __('Width', 'monetize'),
            'zone_height' => __('Height', 'monetize'),
            'units_count' => __('Units', 'monetize'),
            'zone_created' => __('Created', 'monetize'),
            'zone_code' => __('Code', 'monetize')
        );

        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'zone_name' => array('zone_name',false),
            'zone_width' => array('zone_width',false),
            'zone_height' => array('zone_height',false),
            'units_count' => array('units_count',false),
            'zone_created' => array('zone_created',false)
        );

        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete'
        );
        return $actions;
    }


    function process_bulk_action() {
        global $monetize;

        $ids = isset($_REQUEST['zone_id']) ? $_REQUEST['zone_id'] : array();

        if($this->current_action() === 'delete'){
            $monetize->delete_zones($ids);
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

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'zone_created';
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC';

        $current_page = $this->get_pagenum();

        $start = (($current_page-1)*$per_page);

        $total_items = $monetize->get_zones_count(
            $this->start_timestamp, $this->end_timestamp
        );

        $this->items = $monetize->get_zones(
            $this->start_timestamp,
            $this->end_timestamp,
            $orderby,
            $order,
            $start,
            $per_page
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
        <td class="monetize-filter-input">
            <input type="submit" value="<?php _e('Filter', 'monetize'); ?>" class="button-secondary" />
        </td>
    </tr>
</table>
<?php
    }
}
?>