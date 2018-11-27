<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

/**
 * @internal \WP_List_Table is not part of an official API, so it can change anytime.
 */
class ListTable extends \WP_List_Table
{
    /**
     * @var int Number of items displayed per page.
     */
    const PER_PAGE = 100;

    /**
     * @var string String to display when actual value is unknown.
     */
    const UNKNOWN_VALUE = '--';

    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;

    /**
     * @var string Sorting direction (asc or desc)
     */
    protected $order = 'desc';

    /**
     * @var string Sorting column
     */
    protected $order_by = '';


    /**
     * @param \BlueChip\Cache\Core $cache
     */
    public function __construct(Core $cache)
    {
        parent::__construct([
            'singular' => __('Entry', 'bc-cache'),
            'plural' => __('Entries', 'bc-cache'),
            'ajax' => false,
        ]);

        $this->cache = $cache;

        $order_by = filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_STRING);
        if (in_array($order_by, $this->get_sortable_columns(), true)) {
            $this->order_by = $order_by;
        }

        $order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING);
        if ($order === 'asc' || $order === 'desc') {
            $this->order = $order;
        }
    }


    /**
     * Return content for "Relative path" column.
     *
     * @param array $item
     * @return string
     */
    public function column_relative_path(array $item): string // phpcs:ignore
    {
        return '<code>' . esc_html($item['relative_path']) . '</code>';
    }


    /**
     * Return content for "Size" column.
     *
     * @param array $item
     * @return string
     */
    public function column_size(array $item): string // phpcs:ignore
    {
        return $item['size'] ? esc_html(size_format($item['size'])) : self::UNKNOWN_VALUE;
    }


    /**
     * Return content for "Created" column.
     *
     * @param array $item
     * @return string
     */
    public function column_timestamp(array $item): string // phpcs:ignore
    {
        return $item['timestamp']
            ? (date('Y-m-d', $item['timestamp']) . '<br>' . date('H:i:s', $item['timestamp']))
            : self::UNKNOWN_VALUE
        ;
    }


    /**
     * Return content for "URL" column.
     *
     * @param array $item
     * @return string
     */
    public function column_url(array $item): string // phpcs:ignore
    {
        return '<a href="' . esc_url($item['url']) . '">' . esc_html($item['url']) . '</a>';
    }


    /**
     * Declare table columns.
     * @return array
     */
    public function get_columns() // phpcs:ignore
    {
        return [
            'relative_path' => __('Relative path', 'bc-cache'),
            'url' => __('IP address', 'URL'),
            'timestamp' => __('Created', 'bc-cache'),
            'size' => __('Size', 'bc-cache'),
        ];
    }


    /**
     * Declare sortable table columns.
     * @return array
     */
    public function get_sortable_columns() // phpcs:ignore
    {
        // All columns are sortable.
        return [
            'relative_path' => 'relative_path',
            'url' => 'url',
            'timestamp' => 'timestamp',
            'size' => 'size',
        ];
    }


    /**
     * Output "no items" message.
     */
    public function no_items() // phpcs:ignore
    {
        esc_html_e('No entries to display.', 'bc-cache');
    }


    /**
     * Prepare items for table.
     */
    public function prepare_items() // phpcs:ignore
    {
        $state = $this->cache->inspect();

        // Sort items. Sort by key (ie. absolute path), if no explicit sorting column is selected.
        if ($this->order === 'asc') {
            empty($this->order_by) ? krsort($state) : usort($state, self::getAscSortingMethod($this->order_by));
        } else {
            empty($this->order_by) ? ksort($state) : usort($state, self::getDescSortingMethod($this->order_by));
        }

        $current_page = $this->get_pagenum();
        $per_page = self::PER_PAGE;

        $total_items = count($state);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
        ]);

        $this->items = array_slice($state, ($current_page - 1) * $per_page, $per_page);
    }


    /**
     * @param string $order_by
     * @return callable
     */
    private static function getAscSortingMethod(string $order_by): callable
    {
        return function (array $a, array $b) use ($order_by): int {
            if ($a[$order_by] < $b[$order_by]) {
                return -1;
            } elseif ($a[$order_by] > $b[$order_by]) {
                return 1;
            } else {
                return 0;
            }
        };
    }


    /**
     * @param string $order_by
     * @return callable
     */
    private static function getDescSortingMethod(string $order_by): callable
    {
        return function (array $a, array $b) use ($order_by): int {
            if ($a[$order_by] < $b[$order_by]) {
                return 1;
            } elseif ($a[$order_by] > $b[$order_by]) {
                return -1;
            } else {
                return 0;
            }
        };
    }
}
