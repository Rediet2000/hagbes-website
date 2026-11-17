<?php
/** Conservative, bounded Themify cache garbage collection. */
defined('ABSPATH') || exit;
final class Themify_Cache_Prune {
    const OPTION = 'themify_cache_prune_days';
    const HOOK = 'themify_cache_prune_daily';
    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'automatic'));
        add_action('admin_init', array(__CLASS__, 'fallback'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin'));
        add_action('wp_ajax_themify_prune_cache', array(__CLASS__, 'ajax'));
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }
    public static function enqueue_admin($hook) {
        if (empty($_GET['page']) || !in_array(sanitize_key($_GET['page']), array('themify','themify-builder'), true)) return;
        wp_enqueue_script('themify-cache-prune', THEMIFY_URI . '/js/admin/cache-prune.js', array(), THEMIFY_VERSION, true);
        wp_add_inline_script('themify-cache-prune', 'window.themifyPruneNonce=' . wp_json_encode(wp_create_nonce('tf_nonce')) . ';', 'before');
    }
    public static function days() {
        $days = (int) get_option(self::OPTION, 60);
        return in_array($days, array(30, 60, 90, 180), true) ? $days : 60;
    }
    public static function automatic() { self::prune_site(); }
    /** Admin-only fallback for installations without a functioning WP-Cron. */
    public static function fallback() {
        if (!current_user_can('manage_options')) return;
        $now = time();
        if ((int) get_option('themify_cache_prune_last_attempt', 0) < $now - DAY_IN_SECONDS) {
            update_option('themify_cache_prune_last_attempt', $now, false);
            self::prune_site();
        }
    }
    public static function ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Permission denied.', 'themify'), 403);
        if (isset($_POST['days'])) {
            $days = absint($_POST['days']);
            if (!in_array($days, array(30,60,90,180), true)) wp_send_json_error(__('Invalid retention period.', 'themify'), 400);
            update_option(self::OPTION, $days, false);
        }
        if (!empty($_POST['save_only'])) wp_send_json_success(array('saved' => true));
        $network = !empty($_POST['network']);
        if ($network && (!is_multisite() || !is_super_admin() || !current_user_can('manage_network_options'))) {
            wp_send_json_error(__('Network permission denied.', 'themify'), 403);
        }
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $deleted = 0;
        $scanned = 0;
        if ($network) {
            // A bounded number of sites per AJAX request; never delete network content in one huge request.
            $sites = get_sites(array('number' => 3, 'offset' => $offset, 'fields' => 'ids', 'orderby' => 'id', 'order' => 'ASC'));
            foreach ($sites as $site_id) {
                switch_to_blog((int) $site_id);
                try { $stats = self::prune_site(); $deleted += $stats['deleted']; $scanned += $stats['scanned']; }
                finally { restore_current_blog(); }
            }
            $offset += count($sites);
            $more = count($sites) === 3;
        } else {
            $stats = self::prune_site();
            $deleted = $stats['deleted']; $scanned = $stats['scanned'];
            $more = false;
        }
        wp_send_json_success(array('deleted' => $deleted, 'scanned' => $scanned, 'offset' => $offset, 'more' => $more));
    }
    /** Never delete current-version concat files or CSS belonging to existing posts. */
    public static function prune_site() {
        $stats = array('deleted'=>0, 'scanned'=>0);
        $lock = 'themify_cache_prune_lock';
        if (get_transient($lock)) return $stats;
        set_transient($lock, 1, 2 * MINUTE_IN_SECONDS);
        try {
            $cutoff = time() - self::days() * DAY_IN_SECONDS;
            $basedir = themify_upload_dir('basedir');
            if (!is_string($basedir) || !is_dir($basedir)) return $stats;
            // Compute the active folder for THIS blog, not the static version cached by
            // Themify_Enqueue_Assets before switch_to_blog() in a network operation.
            global $wp_version;
            $theme = wp_get_theme(get_template());
            $key = THEMIFY_VERSION . $wp_version . $theme->get('Name') . $theme->get('Version');
            if (function_exists('themify_is_woocommerce_active') && themify_is_woocommerce_active()) {
                if (defined('WC_VERSION')) $key .= WC_VERSION;
                elseif (function_exists('WC') && is_object(WC()) && !empty(WC()->version)) $key .= WC()->version;
            }
            $current = (string) crc32($key);
            self::scan($basedir . '/themify-concate', $cutoff, 'concat', $stats, array($current, basename(rtrim(Themify_Enqueue_Assets::getCurrentVersionFolder(), '/'))));
            self::scan($basedir, $cutoff, 'customizer', $stats);
            self::scan($basedir . '/themify-css', $cutoff, 'builder', $stats);
            // The page cache lives in wp-content, not wp-content/uploads.
            if (class_exists('TFCache', false)) {
                self::scan(TFCache::get_cache_blog_dir(), $cutoff, 'html', $stats);
            }
        } finally {
            delete_transient($lock);
        }
        return $stats;
    }
    private static function scan($root, $cutoff, $kind, &$stats, $current='') {
        if (!is_dir($root) || is_link($root)) return;
        // Keep scanning bounded to avoid timeouts on hosts with enormous cache directories.
        $deadline = microtime(true) + 2.0;
        $max = 1500;
        $local_scanned = 0;
        $cursor_key = 'themify_prune_cursor_' . $kind;
        $cursor = (int) get_option($cursor_key, 0);
        $index = 0;
        $exhausted = true;
        try {
            $flags = FilesystemIterator::SKIP_DOTS;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, $flags), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                if ($index++ < $cursor) continue;
                if (++$local_scanned > $max || microtime(true) > $deadline) { $exhausted = false; break; }
                ++$stats['scanned'];
                $path = $file->getPathname();
                if ($file->isLink() || !$file->isFile() || $file->getMTime() >= $cutoff) continue;
                $name = $file->getFilename();
                $eligible = false;
                if ($kind === 'concat') {
                    // The active version folder is never pruned by age alone.
                    $relative = substr($path, strlen(rtrim($root,'/')) + 1);
                    $parts = explode(DIRECTORY_SEPARATOR, $relative);
                    $eligible = count($parts) > 1 && !in_array($parts[0], (array)$current, true) && preg_match('/^[0-9]+$/', $parts[0]);
                } elseif ($kind === 'customizer') {
                    $active = defined('THEMIFY_VERSION') && Themify_Enqueue_Assets::$themeVersion !== null ? 'themify-customizer-' . THEMIFY_VERSION . '-' . Themify_Enqueue_Assets::$themeVersion : null;
                    $eligible = $active !== null && preg_match('/^themify-customizer-[\w.\-]+\.css$/', $name) && $name !== $active . '.css';
                } elseif ($kind === 'builder') {
                    if (preg_match('/^themify-builder-(\d+)-generated(?:-tmp)?\.css$/', $name, $m)) {
                        // A deleted post may still be present in trash; preserve until permanently removed.
                        $eligible = get_post((int)$m[1]) === null;
                    }
                } elseif ($kind === 'html') {
                    $ttl = defined('TF_CACHE_TIME') && TF_CACHE_TIME ? (int) TF_CACHE_TIME * MINUTE_IN_SECONDS : WEEK_IN_SECONDS;
                    $eligible = preg_match('/\.html(?:\.gz)?$/', $name) && $file->getMTime() + $ttl < time();
                }
                if ($eligible && @unlink($path)) ++$stats['deleted'];
            }
        } catch (UnexpectedValueException $e) {
            // Ignore unreadable directories; do not interfere with page rendering.
            $exhausted = true;
        }
        update_option($cursor_key, $exhausted ? 0 : $index - 1, false);
    }
}
Themify_Cache_Prune::init();
