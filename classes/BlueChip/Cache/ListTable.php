<?php

namespace BlueChip\Cache;

/**
 * @internal \WP_List_Table is not part of an official API, so it can change anytime.
 */
class ListTable extends \WP_List_Table
{
    /**
     * @var string Name of delete action
     */
    private const ACTION_DELETE = 'delete';

    /**
     * @var string Name of bulk delete action
     */
    private const BULK_ACTION_DELETE = 'bulk-delete';

    /**
     * @var string Name of deleted notice query argument
     */
    private const NOTICE_ENTRY_DELETED = 'deleted';

    /**
     * @var string Name of failed notice query argument
     */
    private const NOTICE_ENTRY_FAILED = 'failed';

    /**
     * @var string Nonce name used for actions
     */
    private const NONCE_NAME = '_wpnonce';

    /**
     * @var int Number of items displayed per page.
     */
    private const PER_PAGE = 100;

    /**
     * @var string String to display when actual value is unknown.
     */
    private const UNKNOWN_VALUE = '--';

    /**
     * @var Core
     */
    private $cache;

    /**
     * @var Crawler|null
     */
    private $cache_crawler;

    /**
     * @var Feeder|null
     */
    private $cache_feeder;

    /**
     * @var string[] List of known request variants: id => label
     */
    private $request_variants = [];

    /**
     * @var string Sorting direction (asc or desc)
     */
    private $order = 'asc';

    /**
     * @var string Sorting column
     */
    private $order_by = '';

    /**
     * @var string Base URL of admin page (without any status-like query string parameters)
     */
    private $url;

    /**
     * @var int|null Total size of all files (entries) reported in the list.
     */
    private $cache_files_size = null;


    /**
     * @param Core $cache
     * @param Feeder|null $cache_feeder Null value signals that cache warm up is disabled.
     * @param string $url
     */
    public function __construct(Core $cache, ?Feeder $cache_feeder, string $url)
    {
        parent::__construct([
            'singular' => __('Entry', 'bc-cache'),
            'plural' => __('Entries', 'bc-cache'),
            'ajax' => false,
        ]);

        $this->cache = $cache;
        $this->cache_feeder = $cache_feeder;
        $this->url = $url;

        // Get list of request variants.
        $this->request_variants = $cache->getRequestVariants();

        $order_by = \filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_STRING);
        if (\in_array($order_by, $this->get_sortable_columns(), true)) {
            $this->order_by = $order_by;
            $this->url = add_query_arg('orderby', $order_by, $this->url);
        }

        $order = \filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING);
        if ($order === 'asc' || $order === 'desc') {
            $this->order = $order;
            $this->url = add_query_arg('order', $order, $this->url);
        }
    }


    /**
     * @return int|null Total size of all files (entries) reported in the list or null if unknown.
     */
    public function getCacheFilesSize(): ?int
    {
        return $this->cache_files_size;
    }


    /**
     * Return content for "checkbox" column.
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_cb($item) // phpcs:ignore
    {
        return \sprintf(
            '<input type="checkbox" name="urls[]" value="%s" />',
            (string) $item
        );
    }


    /**
     * Return content for "ID" column (including row actions).
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_entry_id(ListTableItem $item): string // phpcs:ignore
    {
        return
            '<code>' . esc_html($item->getEntryId()) . '</code>' . '<br>' .
            $this->row_actions($this->getRowActions($item))
        ;
    }


    /**
     * Return content for "Request variant" column.
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_request_variant(ListTableItem $item): string // phpcs:ignore
    {
        return esc_html($this->request_variants[$item->getRequestVariant()]);
    }


    /**
     * Return content for "Size" column.
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_size(ListTableItem $item): string // phpcs:ignore
    {
        return \sprintf(
            '%s | %s | %s',
            esc_html(size_format($item->getTotalDiskSize())),
            esc_html(size_format($item->getHtmlFileSize())),
            esc_html(size_format($item->getGzipFileSize()))
        );
    }


    /**
     * Return content for "Created" column.
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_timestamp(ListTableItem $item): string // phpcs:ignore
    {
        $timestamp = $item->getTimestamp();

        return $timestamp !== null
            ? (wp_date('Y-m-d', $timestamp) . '<br>' . wp_date('H:i:s', $timestamp))
            : self::UNKNOWN_VALUE
        ;
    }


    /**
     * Return content for "URL" column.
     *
     * @param ListTableItem $item
     *
     * @return string
     */
    public function column_url(ListTableItem $item): string // phpcs:ignore
    {
        return '<a href="' . esc_url($item->getUrl()) . '">' . esc_html($item->getUrl()) . '</a>';
    }


    /**
     * @return string[]
     */
    public function get_bulk_actions() // phpcs:ignore
    {
        $actions = [];

        if (Plugin::canUserFlushCache()) {
            $actions[self::BULK_ACTION_DELETE] = __('Delete', 'bc-cache');
        }

        return $actions;
    }


    /**
     * Declare table columns.
     *
     * @return string[]
     */
    public function get_columns() // phpcs:ignore
    {
        return [
            'cb' => '<input type="checkbox">',
            'entry_id' => __('ID', 'bc-cache'),
            'url' => __('URL', 'bc-cache'),
            'request_variant' => __('Request variant', 'bc-cache'),
            'timestamp' => __('Created', 'bc-cache'),
            'size' => __('Size: total | html | gzipped', 'bc-cache'),
        ];
    }


    /**
     * Declare sortable table columns.
     *
     * @return string[]
     */
    public function get_sortable_columns() // phpcs:ignore
    {
        // All columns but request variant are sortable.
        return [
            'entry_id' => 'entry_id',
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

        if ($state === null) {
            // There has been an error...
            AdminNotices::add(__('Failed to read cache state information!', 'bc-cache'), AdminNotices::ERROR, false);
            // ...thus there is nothing to show.
            $state = [];
        } else {
            // Sort items. Sort by key (ie. absolute path) if no explicit sorting column is selected.
            if ($this->order === 'asc') {
                empty($this->order_by) ? \krsort($state) : \usort($state, self::getAscSortingMethod($this->order_by));
            } else {
                empty($this->order_by) ? \ksort($state) : \usort($state, self::getDescSortingMethod($this->order_by));
            }

            // Also calculate total cache files size.
            $this->cache_files_size = \array_sum(
                \array_map(
                    function (ListTableItem $item): int { return $item->getTotalDiskSize(); }, // phpcs:ignore
                    $state
                )
            );
        }

        $current_page = $this->get_pagenum();
        $per_page = self::PER_PAGE;

        $total_items = \count($state);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
        ]);

        $this->items = \array_slice($state, ($current_page - 1) * $per_page, $per_page);
    }


    /**
     * Process any actions like deleting etc.
     *
     * @return void
     */
    public function processActions(): void
    {
        // Delete single entry?
        if (($action = \filter_input(INPUT_GET, 'action'))) {
            // Get URL of entry to act upon.
            $url = \filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
            if (empty($url)) {
                return;
            }

            $request_variant = \filter_input(INPUT_GET, 'request_variant', FILTER_SANITIZE_STRING);
            if (!isset($this->request_variants[$request_variant])) {
                return;
            }

            $nonce = \filter_input(INPUT_GET, self::NONCE_NAME);
            if (!wp_verify_nonce($nonce, \sprintf('%s:%s', $action, $url))) {
                // Nonce check failed
                return;
            }

            if (($action === self::ACTION_DELETE) && Plugin::canUserFlushCache()) {
                $cache_item = new Item($url, $request_variant);

                // Attempt to delete URL from cache and set proper query argument for notice based on return value.
                if ($this->cache->delete($cache_item)) {
                    if ($this->cache_feeder !== null) {
                        // Push item to feeder...
                        if ($this->cache_feeder->push($cache_item)) {
                            // ...and activate crawler if pushed successfully.
                            if ($this->cache_crawler !== null) {
                                $this->cache_crawler->activate();
                            }
                        }
                    }

                    $query_arg = self::NOTICE_ENTRY_DELETED;
                } else {
                    $query_arg = self::NOTICE_ENTRY_FAILED;
                }

                wp_redirect(add_query_arg($query_arg, 1, $this->url));
            }
        }

        // Bulk delete?
        if ((self::BULK_ACTION_DELETE === $this->current_action()) && Plugin::canUserFlushCache() && isset($_POST['urls']) && \is_array($_POST['urls'])) {
            // Sanitize.
            $sanitized = \array_filter(
                \filter_input_array(INPUT_POST, ['urls' => ['filter' => FILTER_VALIDATE_URL, 'flags' => FILTER_REQUIRE_ARRAY]])
            );

            // Get URLs.
            $urls = $sanitized['urls'];

            // Number of entries really deleted.
            $items_deleted = 0;
            $items_pushed_to_warm_up_queue = 0;

            foreach ($urls as $url_with_request_variant) {
                $cache_item = Item::createFromString($url_with_request_variant);

                if ($this->cache->delete($cache_item)) {
                    $items_deleted += 1;
                    // If cache warm up is enabled...
                    if ($this->cache_feeder !== null) {
                        // ...push item to feeder.
                        if ($this->cache_feeder->push($cache_item)) {
                            $items_pushed_to_warm_up_queue += 1;
                        }
                    }
                }
            }

            // Activate cache crawler if any items has been pushed to the warm up queue.
            if (($items_pushed_to_warm_up_queue > 0) && ($this->cache_crawler !== null)) {
                $this->cache_crawler->activate();
            }

            if ($items_deleted < \count($urls)) {
                wp_redirect(add_query_arg(self::NOTICE_ENTRY_FAILED, \count($urls), $this->url));
            } else {
                wp_redirect(add_query_arg(self::NOTICE_ENTRY_DELETED, $items_deleted, $this->url));
            }
        }
    }


    /**
     * Display (dismissible) admin notice informing user about actions that have been performed.
     */
    public function displayNotices(): void
    {
        $this->displayNotice(
            self::NOTICE_ENTRY_DELETED,
            'Selected entry has been removed.',
            'Selected entries have been removed.',
            AdminNotices::SUCCESS
        );

        $this->displayNotice(
            self::NOTICE_ENTRY_FAILED,
            'Failed to delete selected entry.',
            'Failed to delete all selected entries.',
            AdminNotices::ERROR
        );
    }


    /**
     * Display (dismissible) admin notice informing user that an action has been performed with given outcome.
     *
     * @param string $action Name of query string argument that indicates number of items affected (or not) by action.
     * @param string $single The text to be used in notice if action affected (or not) single item.
     * @param string $plural The text to be used in notice if action affected (or not) multiple items.
     * @param string $type The type of the notice.
     */
    private function displayNotice(string $action, string $single, string $plural, string $type): void
    {
        // Have any items been affected by given action?
        $result = \filter_input(INPUT_GET, $action, FILTER_VALIDATE_INT);
        if (\is_int($result) && ($result > 0)) {
            AdminNotices::add(
                _n($single, $plural, $result, 'bc-cache'),
                $type
            );
            add_filter('removable_query_args', function (array $removable_query_args) use ($action): array {
                $removable_query_args[] = $action;
                return $removable_query_args;
            });
        }
    }


    /**
     * @param string $order_by
     *
     * @return callable
     */
    private static function getAscSortingMethod(string $order_by): callable
    {
        return function (object $a, object $b) use ($order_by): int {
            if ($a->$order_by < $b->$order_by) {
                return -1;
            } elseif ($a->$order_by > $b->$order_by) {
                return 1;
            } else {
                return 0;
            }
        };
    }


    /**
     * @param string $order_by
     *
     * @return callable
     */
    private static function getDescSortingMethod(string $order_by): callable
    {
        return function (object $a, object $b) use ($order_by): int {
            if ($a->$order_by < $b->$order_by) {
                return 1;
            } elseif ($a->$order_by > $b->$order_by) {
                return -1;
            } else {
                return 0;
            }
        };
    }


    /**
     * @param Item $item
     *
     * @return string[]
     */
    private function getRowActions(Item $item): array
    {
        $actions = [];

        if (Plugin::canUserFlushCache()) {
            $actions[self::ACTION_DELETE] = $this->renderRowAction(
                self::ACTION_DELETE,
                $item->getUrl(),
                $item->getRequestVariant(),
                'delete',
                __('Delete entry', 'bc-cache')
            );
        }

        return $actions;
    }


    /**
     * Return HTML for specified row action link.
     *
     * @param string $action
     * @param string $url
     * @param string $request_variant
     * @param string $class
     * @param string $label
     *
     * @return string
     */
    private function renderRowAction(string $action, string $url, string $request_variant, string $class, string $label): string
    {
        return \sprintf(
            '<span class="' . $class . '"><a href="%s">%s</a></span>',
            wp_nonce_url(
                add_query_arg(
                    ['action' => $action, 'url' => $url, 'request_variant' => $request_variant],
                    $this->url
                ),
                \sprintf('%s:%s', $action, $url),
                self::NONCE_NAME
            ),
            esc_html($label)
        );
    }
}
