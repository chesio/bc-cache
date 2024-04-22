<?php

declare(strict_types=1);

namespace BlueChip\Cache;

class Viewer
{
    /**
     * @var string Slug for cache viewer page
     */
    private const ADMIN_PAGE_SLUG = 'bc-cache-view';

    /**
     * @var string Capability required to view cache viewer
     */
    public const REQUIRED_CAPABILITY = 'manage_options';

    /**
     * @var string Name of nonce used for any custom actions on admin pages
     */
    protected const NONCE_NAME = '_wpnonce';

    /**
     * @var string Name for start warm up action (used for both nonce action and submit name)
     */
    private const START_WARM_UP_ACTION = 'start-warm-up';

    private ListTable $list_table;


    /**
     * @param Core $cache
     * @param Crawler|null $cache_crawler Null value signals that cache warm up is disabled.
     * @param Feeder|null $cache_feeder Null value signals that cache warm up is disabled.
     */
    public function __construct(private Core $cache, private ?Crawler $cache_crawler, private ?Feeder $cache_feeder)
    {
    }


    /**
     * Initialize cache viewer.
     */
    public function init(): void
    {
        add_action('admin_menu', $this->addAdminPage(...));
    }


    /**
     * @return string URL of viewer page.
     */
    public function getUrl(): string
    {
        return add_query_arg('page', self::ADMIN_PAGE_SLUG, admin_url('tools.php'));
    }


    /**
     * Register method to be run on page load.
     *
     * @link https://developer.wordpress.org/reference/hooks/load-page_hook/
     *
     * @param string $page_hook
     */
    private function setPageHook(string $page_hook): void
    {
        add_action('load-' . $page_hook, $this->loadPage(...));
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    private function addAdminPage(): void
    {
        $page_hook = add_management_page(
            __('BC Cache Viewer', 'bc-cache'),
            __('Cache Viewer', 'bc-cache'),
            self::REQUIRED_CAPABILITY,
            self::ADMIN_PAGE_SLUG,
            $this->renderAdminPage(...)
        );

        if ($page_hook) {
            // If page has been added properly, register method to run on page load.
            $this->setPageHook($page_hook);
        }
    }


    /**
     * @internal ListTable instance cannot be initialized in the constructor, because \WP_List_Table is unknown to PHP
     * at the time constructor is invoked.
     *
     * @action https://developer.wordpress.org/reference/hooks/load-page_hook/
     */
    private function loadPage(): void
    {
        $this->processActions();

        $this->list_table = new ListTable($this->cache, $this->cache_crawler, $this->cache_feeder, $this->getUrl());
        $this->list_table->processActions(); // may trigger wp_redirect()
        $this->list_table->displayNotices();
        $this->list_table->prepare_items();

        $this->checkCacheSize();
    }


    /**
     * Process any actions according to POST-ed data.
     */
    private function processActions(): void
    {
        $nonce = \filter_input(INPUT_POST, self::NONCE_NAME);
        if (empty($nonce)) {
            // No nonce, no action.
            return;
        }

        // Start cache warm up action requested?
        if (\array_key_exists(self::START_WARM_UP_ACTION, $_POST) && wp_verify_nonce($nonce, self::START_WARM_UP_ACTION)) {
            if (!$this->cache_crawler) {
                AdminNotices::add(
                    __('Cannot start cache warm up, because cache warm up is disabled.', 'bc-cache'),
                    AdminNotices::ERROR
                );
            } elseif ($this->cache_crawler->activate(true)) {
                AdminNotices::add(
                    __('Cache warm up successfully started.', 'bc-cache'),
                    AdminNotices::SUCCESS
                );
            } else {
                AdminNotices::add(
                    __('There has been an error when attempting to start cache warm up.', 'bc-cache'),
                    AdminNotices::ERROR
                );
            }
        }
    }


    private function renderAdminPage(): void
    {
        echo '<div class="wrap">';

        // Page heading
        echo '<h1>' . esc_html__('BC Cache Viewer', 'bc-cache') . '</h1>';

        $this->renderCacheDirectoryInfo();

        $this->renderCacheSizeInfo();

        $this->renderWarmUpQueueInfo();

        // View table
        $this->list_table->views();
        echo '<form method="post">';
        $this->list_table->display();
        echo '</form>';

        echo '</div>';
    }

    /**
     * Display information about cache directory path.
     */
    private function renderCacheDirectoryInfo(): void
    {
        echo '<p>';
        echo \sprintf(
            esc_html__('Cache data are stored in %s directory.', 'bc-cache'),
            '<code>' . Plugin::CACHE_DIR . '</code>'
        );
        echo '</p>';
    }


    /**
     * Display information about overall cache size.
     */
    private function renderCacheSizeInfo(): void
    {
        // Print section header
        echo '<h2>' . esc_html__('Cache status', 'bc-cache') . '</h2>';

        // Gather cache statistics (age and size), if available.
        $cache_info = [];

        if (\is_int($cache_age = $this->cache->getAge())) {
            $cache_info[] = \sprintf(
                esc_html__('Cache has been fully flushed %s ago.', 'bc-cache'),
                '<strong><abbr title="' . wp_date('Y-m-d H:i:s', $cache_age) . '">' . human_time_diff($cache_age) . '</abbr></strong>'
            );
        }

        if (\is_int($cache_files_size = $this->list_table->getCacheFilesSize())) {
            $cache_info[] = \sprintf(
                esc_html__('Cache files occupy %s of space in total.', 'bc-cache'),
                '<strong><abbr title="' . \sprintf(_n('%d byte', '%d bytes', $cache_files_size, 'bc-cache'), $cache_files_size) . '">' . size_format($cache_files_size) . '</abbr></strong>'
            );
        }

        if ($cache_info !== []) {
            echo '<p>' . \implode(' ', $cache_info) . '</p>';
        }
    }


    /**
     * Display optional cache warm up information.
     */
    private function renderWarmUpQueueInfo(): void
    {
        // Print section header
        echo '<h2>' . esc_html__('Warm up queue', 'bc-cache') . '</h2>';

        echo '<p>' . $this->getWarmUpStatus() . '</p>';

        $this->renderWarmUpActivationForm();
    }


    /**
     * @return string Info about warm up status.
     */
    private function getWarmUpStatus(): string
    {
        if (($this->cache_crawler === null) || ($this->cache_feeder === null)) {
            return esc_html__('Cache warm up is not enabled.', 'bc-cache');
        }

        if (!$this->cache_feeder->synchronize()) {
            return esc_html__('Warm up queue statistics could not be synchronised with cache state.', 'bc-cache');
        }

        // Note: calling getStats() implicitly rebuilds the queue if it has not been rebuild yet.
        ['processed' => $processed, 'waiting' => $waiting, 'total' => $total] = $this->cache_feeder->getStats();

        if ($total === 0) {
            return esc_html__('Warm up queue statistics are not available.', 'bc-cache');
        }

        // Calculate progress in %:
        $progress = (int) (\round($processed / $total, 2) * 100);

        // Prepare stats information.
        $stats = sprintf(
            esc_html__('%s of known frontend pages is cached (%d in queue | %d processed | %d total)', 'bc-cache'),
            sprintf('<strong>%d%%</strong>', $progress), // render progress in bold
            $waiting,
            $processed,
            $total
        );

        if ($processed === $total) {
            return sprintf(esc_html__('Website should be fully cached: %s', 'bc-cache'), $stats);
        }

        $next_run_timestamp = $this->cache_crawler->getNextScheduled();

        if ($next_run_timestamp === null) {
            // Somehow there is no cron job scheduled...
            return sprintf(esc_html__('Warm up stalled at: %s', 'bc-cache'), $stats);
        }

        if ($next_run_timestamp <= time()) {
            return sprintf(esc_html__('Warm up runs in background: %s', 'bc-cache'), $stats);
        }

        return sprintf(
            esc_html__('Warm up starts in %s: %s', 'bc-cache'),
            human_time_diff($next_run_timestamp),
            $stats
        );
    }


    private function renderWarmUpActivationForm(): void
    {
        if (($this->cache_crawler === null) || ($this->cache_feeder === null)) {
            // Cache warm up not enabled, bail.
            return;
        }

        if ($this->cache_feeder->getSize() === 0) {
            // Cache queue is empty, no need to activate the warm up.
            return;
        }

        $next_run_timestamp = $this->cache_crawler->getNextScheduled();

        if (($next_run_timestamp !== null) && ($next_run_timestamp <= time())) {
            // Cache warm up is already running.
            return;
        }

        // Phrase button text accordingly: warm up stalled => resume; not running yet => start.
        $button_text = ($next_run_timestamp === null)
            ? __('Resume warm up now', 'bc-cache')
            : __('Start warm up now', 'bc-cache')
        ;

        echo '<form method="post">';
        wp_nonce_field(self::START_WARM_UP_ACTION, self::NONCE_NAME);
        submit_button($button_text, 'small', self::START_WARM_UP_ACTION, false);
        echo '</form>';
    }


    /**
     * Display a warning if total size of cache files differs from total size of files in cache folder.
     */
    private function checkCacheSize(): void
    {
        $cache_files_size = $this->list_table->getCacheFilesSize();
        $cache_size = $this->cache->getSize(true);

        if (\is_int($cache_files_size) && \is_int($cache_size) && ($cache_files_size !== $cache_size)) {
            AdminNotices::add(
                \sprintf(
                    __('Total size of recognized cache files (%s) differs from total size of all files in cache directory (%s). Please, make sure that you have set up request variants filters correctly and then flush the cache.', 'bc-cache'),
                    '<strong>' . \sprintf(_n('%d byte', '%d bytes', $cache_files_size, 'bc-cache'), $cache_files_size) . '</strong>',
                    '<strong>' . \sprintf(_n('%d byte', '%d bytes', $cache_size, 'bc-cache'), $cache_size) . '</strong>'
                ),
                AdminNotices::WARNING,
                false,
                false
            );
        }
    }
}
