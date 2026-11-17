<?php

final class Themify_Builder_Stylesheet {

    public static $generateStyles = array();
    private static $isLoaded = array();

    /**
     * Constructor
     * 
     * @access public
     * @param object Themify_Builder $builder 
     */
    public static function init() {
        add_action('wp_ajax_tb_slider_live_styling', array(__CLASS__, 'slider_live_styling'), 10);
        add_action('wp_ajax_nopriv_tb_generate_on_fly', array(__CLASS__, 'save_builder_css'), 10);
        add_action('wp_ajax_tb_generate_on_fly', array(__CLASS__, 'save_builder_css'), 10);
        add_action('wp_ajax_themify_regenerate_css_files_ajax', array(__CLASS__, 'regenerate_css_files_ajax'));
        add_action('wp_ajax_tb_css_rebuild_list', array(__CLASS__, 'rebuild_list_ajax'));
        add_action('wp_ajax_tb_css_rebuild_sites', array(__CLASS__, 'rebuild_sites_ajax'));
        add_action('wp_ajax_tb_css_rebuild_start', array(__CLASS__, 'rebuild_start_ajax'));
        add_action('wp_ajax_tb_css_rebuild_finish', array(__CLASS__, 'rebuild_finish_ajax'));
        add_action('wp_ajax_tb_css_rebuild_job_save', array(__CLASS__, 'rebuild_job_save_ajax'));
        add_action('wp_ajax_tb_css_rebuild_job_urls', array(__CLASS__, 'rebuild_job_urls_ajax'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_rebuild_admin'));
        add_action('init', array(__CLASS__, 'rebuild_cache_bypass'), 0);
        add_action('send_headers', array(__CLASS__, 'rebuild_cache_bypass'), 0);
        add_filter('show_admin_bar', array(__CLASS__, 'rebuild_hide_admin_bar'));
        add_action('wp_print_scripts', array(__CLASS__, 'rebuild_strip_scripts'), 0);
        add_action('wp_print_footer_scripts', array(__CLASS__, 'rebuild_strip_scripts'), 0);
        add_action('wp_footer', array(__CLASS__, 'rebuild_fallback_script'), 99);
        add_action('template_redirect', array(__CLASS__, 'rebuild_preview_headers'), 0);
        if (defined('THEMIFY_BUILDER_REGENERATE_CSS') && THEMIFY_BUILDER_REGENERATE_CSS !== false && current_user_can('manage_options')) {
            add_action('admin_head', array(__CLASS__, 'auto_regenerate_css'));
        }
        if (!is_admin()) {
            add_action('tf_load_styles', array(__CLASS__, 'load_styles'), 9); //10 is customizer
        }
    }

    /**
     * Checks if the builder stylesheet exists and enqueues it.
     * 
     * @since 2.2.5
     * 
     * @return bool True if enqueue was successful, false otherwise.
     */
    public static function enqueue_stylesheet(bool $return = false, ?int $post_id = null) {
        $isActive = Themify_Builder::$frontedit_active === true || Themify_Builder_Model::is_front_builder_activate();
        if (($isActive === false && isset(self::$isLoaded[$post_id])) || themify_is_rest()) {
            return false;
        }
        // Repair v2 slashes and duplicate element ids before a cached stylesheet is reused.
        if (!empty($post_id)) {
            ThemifyBuilder_Data_Manager::get_data($post_id);
        }
        $stylesheet_path = self::get_stylesheet('basedir', $post_id);
        // A signed, authorised regeneration request must rebuild even when an old
        // stylesheet exists. The old file remains available to public visitors.
        $force_rebuild = isset($_GET['tb_css_rebuild']) && current_user_can('edit_post', $post_id)
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tb_css_rebuild'])), self::rebuild_nonce_action((int) $post_id));
        if ($force_rebuild || !self::is_readable_and_not_empty($stylesheet_path['url'])) {
            $tmp_path = self::getTmpPath($stylesheet_path['url']);
            // A "done" marker is written when a save produced no CSS. It must not
            // hide a later layout that has styling, or a manual rebuild.
            if (Themify_Filesystem::is_file($tmp_path) && ($force_rebuild || self::builder_needs_css(ThemifyBuilder_Data_Manager::get_data($post_id), (int) $post_id))) {
                Themify_Filesystem::delete($tmp_path, 'f');
            }
            if (!is_admin() && !isset(self::$generateStyles[$post_id]) && !Themify_Filesystem::is_file($tmp_path)) {

                self::$generateStyles[$post_id] = true;
                $post_data = ThemifyBuilder_Data_Manager::get_data($post_id);
                if (!empty($post_data)) {
                    static $strucutre = null;
                    if ($strucutre === null) {
                        $strucutre = true;
                        Themify_Builder_Component_Module::load_modules();
                        themify_enque_script('tb_builder_js_style', THEMIFY_URI . '/js/generate-style.js', THEMIFY_VERSION, array('themify-main-script'));

                        add_filter('themify_google_fonts_full_list', '__return_true');
                        //backward
                        $styles = [];
                        $modules=Themify_Builder_Component_Module::load_modules();
                        foreach ($modules as $id => $c) {
                            if (is_object($c)) {
                                $styles[$id] = $c->get_form_settings();
                            }
                        }
                        unset($modules);
                        $jsonFiles = Themify_Builder_Component_Module::get_styles_json();
                        wp_localize_script('tb_builder_js_style', 'ThemifyBuilderStyle', array(
                            'builder_url' => THEMIFY_BUILDER_URI,
                            'style_json' => $jsonFiles,
                            'styles' => $styles,
                            'gutters' => Themify_Builder_Model::get_gutters(),
                            'points' => themify_get_breakpoints(),
                            'nonce' => wp_create_nonce('tf_nonce'),
                            'ajaxurl' => admin_url('admin-ajax.php'),
                            'google' => themify_get_google_web_fonts_list(),
                            'var_fonts' => array_keys( themify_get_variable_fonts_list() ),
                            'cf' => Themify_Custom_Fonts::get_list(),
                            'cf_api_url' => Themify_Custom_Fonts::$api_url
                        ));
                        remove_filter('themify_google_fonts_full_list', '__return_true');
                        foreach ($jsonFiles as $f) {
                            Themify_Enqueue_Assets::addPreLoadMedia($f, 'prefetch', 'json');
                        }
                        $styles = $jsonFiles = null;
                    }
                    $js_data = array('data' => $post_data, 'can_persist' => current_user_can('edit_post', $post_id));
                    $gs = Themify_Global_Styles::used_global_styles($post_id);
                    if (!empty($gs)) {
                        $js_data['gs'] = $gs;
                    }
                    $custom_css = get_post_meta($post_id, 'tbp_custom_css', true);
                    if (!empty($custom_css)) {
                        $js_data['custom_css'] = $custom_css;
                    }
                    wp_localize_script('tb_builder_js_style', 'themify_builder_data_' . $post_id, $js_data);
                    $gs = $post_data = $custom_css = null;
                    // Browser-generated styles are included on cached pages as well.
                    // Do not permanently disable full-page caching for missing CSS files.
                    Themify_Builder::get_builder_stylesheet('', true);
                }
            }
        } 
        elseif (!is_admin() || themify_is_ajax()) {
            $handler = pathinfo($stylesheet_path['url']);
            $handlerId = $handler['filename'];
            $version = filemtime($stylesheet_path['url']);
            $url = self::get_stylesheet('baseurl', $post_id);
            $url = themify_https_esc($url['url']);
            self::$isLoaded[$post_id] = array(
                'handler' => $handlerId,
                'url' => $url,
                'v' => $version
            );
            if ($return === true) {
                return self::$isLoaded[$post_id];
            } else {
                $fonts = self::getFonts($post_id);
                if (!empty($fonts)) {
                    Themify_Enqueue_Assets::addGoogleFont($fonts);
                }
                // custom fonts
                $custom_fonts = Themify_Custom_Fonts::load_fonts($post_id);
                if (!empty($custom_fonts)) {
                    echo '<style id="', $handlerId, '-cffonts">', $custom_fonts, '</style>', PHP_EOL;
                }
                unset($custom_fonts, $fonts);
                if ( ( $isActive === true && Themify_Builder::$is_loop === true && $post_id != Themify_Builder::$builder_active_id ) || self::is_infinite_scroll($post_id) ) {
                    echo '<link class="themify-builder-generated-css" id="', $handlerId, '" rel="stylesheet" href="', $url, '?ver=', $version, '" type="text/css">';
                }
            }
            return true;
        }
        return false;
    }

    /* single post infinite scroll */
    private static function is_infinite_scroll( int $post_id ) : bool {
        return isset( $_GET['tf-scroll'] ) && $post_id === get_queried_object_id();
    }

    public static function load_styles() {
        foreach (self::$isLoaded as $post_id => $v) {
            if (Themify_Builder::$builder_active_id === $post_id && Themify_Builder_Model::is_front_builder_activate()) {
                continue;
            }
            \themify_enque_style($v['handler'], $v['url'], null, $v['v']);
        }
        self::$isLoaded = array();
    }

    /**
     * This function check the CSS generated files version and if
     * it requires to update or not
     */
    public static function auto_regenerate_css() {
        if (get_option('tb_css_version', false) !== THEMIFY_VERSION && Themify_Filesystem::is_dir(self::get_stylesheet_dir()) && 'finished' === self::regenerate_css_files()) {
            update_option('tb_css_version', THEMIFY_VERSION, false);
        }
    }

    /**
     * Generate css file on the fly, if builder doesn't have style created an empty file,
     * which will help to detect there is no need to create css file.
     * @return void
     */
    public static function save_builder_css(bool $echo = false) {
        check_ajax_referer('tf_nonce', 'nonce');
        if (empty($_POST['bid'])) {
            wp_send_json_error();
        }
        $id = (int) $_POST['bid'];
        $can_edit = current_user_can('edit_post', $id);
        $stylesheet_path = self::get_stylesheet('basedir', $id)['url'];

        if (!$can_edit) {
            if (!self::can_generate_on_the_fly($id)) {
                wp_send_json_error();
            }
            // The browser has already rendered the complete generated CSS as
            // temporary <style> elements. Never persist CSS provided by a user
            // without edit_post, even if the generated file is missing: a public
            // nonce cannot establish that the submitted selectors/declarations
            // match the saved Builder layout. This preserves all valid CSS syntax
            // in the frontend without creating an unauthenticated disk-write path.
            // An editor can subsequently regenerate and save the stylesheet.
            wp_send_json(array('success' => 1, 'persisted' => false));
        }
        if (!empty($_FILES['css'])) {
            $data = file_get_contents($_FILES['css']['tmp_name']);
        } 
        elseif (isset($_POST['css'])) {//don`t use empty
            if (isset($_POST['mode']) && $_POST['mode'] === 'gzip') {
                if (!function_exists('gzdecode')) {
                    wp_send_json_error(__('gzdecode is disabled', 'themify'));
                }
                $data = function_exists( 'themify_safe_gzbase64_decode' ) ? themify_safe_gzbase64_decode( $_POST['css'] ) : @gzdecode( base64_decode( $_POST['css'] ) );
                if ( $data === false ) {
                    wp_send_json_error( __( 'Invalid CSS data.', 'themify' ) );
                }
            } 
            else {
                $data = !empty($_POST['css']) ? stripslashes_deep($_POST['css']) : array();
            }
        }
        if (isset($data)) {
            if (is_string($data)) {
                $data = json_decode($data, true);
            } elseif (!is_array($data)) {
                $data = array();
            }
            if ($can_edit) {
                $custom_css = !empty($_POST['custom_css']) ? themify_sanitize_builder_custom_css( stripcslashes( $_POST['custom_css'] ) ) : '';
            } else {
                $data = self::sanitize_on_the_fly_css($data);
                $custom_css = get_post_meta($id, 'tbp_custom_css', true);
                $custom_css = $custom_css !== '' && $custom_css !== false ? themify_sanitize_inline_css( themify_sanitize_builder_custom_css( $custom_css ) ) : '';
            }
            $res = self::write_stylesheet($id, $data, $custom_css, $can_edit);
            $tmpFile=self::getTmpPath($stylesheet_path);
            if (!empty($res['css_file'])) {
                Themify_Filesystem::delete($tmpFile, 'f');
            }
            elseif (!Themify_Filesystem::is_file($stylesheet_path)) {
                Themify_Filesystem::put_contents($tmpFile, 'done');
            }
            if (!empty($_POST['images']) && is_user_logged_in()) {
                $images = json_decode(stripslashes_deep($_POST['images']), true);
                if (!empty($images) && is_array($images)) {
                    foreach ($images as $img) {
                        $ext=strtolower(strtok(pathinfo($img,PATHINFO_EXTENSION ),'?'));
                        if($ext==='png' || $ext==='jpg' || $ext==='jpeg' || $ext==='webp' || $ext==='gif' ||$ext==='bmp' ){
                            try {
                                themify_get_image_size($img);
                                // Use generateWebp so Performance > WebP toggle is respected
                                themify_generateWebp($img);
                            } catch (Throwable $e) {
                                // Skip bad/oversized images — do not block CSS save
                            }
                        }
                    }
                }
                unset($images);
            }
            wp_send_json($echo === true ? $res : array('success' => !empty($res['write']) || !empty($res['css_file'])));
        }
        wp_send_json_error();
    }

    /* Return tmp path of original file */

    private static function getTmpPath(string $path): string {
        return str_replace('.css', '-tmp.css', $path);
    }

    private static function can_generate_on_the_fly(int $id): bool {
        $post = get_post($id);
        if (!$post) {
            return false;
        }
        $post_type_obj = get_post_type_object($post->post_type);
        return $post->post_status === 'publish'
            && !post_password_required($post)
            && $post_type_obj
            && !empty($post_type_obj->public);
    }

    private static function is_builder_css_selector(string $selector): bool {
        $selector = trim($selector);
        // > is the child combinator (e.g. .tb_x>.row_inner). < stays blocked so a selector cannot open an HTML tag.
        if ($selector === '' || preg_match('/[{};\\\\<]|url\s*\(|expression\s*\(|javascript\s*:|@import|@charset|@namespace/i', $selector)) {
            return false;
        }
        // Anonymous CSS must be a selector rule, not an unscoped at-rule.
        // The serializer adds its own closing brace for at-rules, so accepting
        // arbitrary @ rules here could also alter the following stylesheet.
        if ($selector[0] === '@') {
            return false;
        }
        // Row overlays legitimately use :is(.tf_lax, .tf_lax_done).
        // Other relational/functional selectors can escape the Builder scope.
        $functional = preg_replace('/:is\(\s*\.tf_lax\s*,\s*\.tf_lax_done\s*\)/i', '', $selector);
        if (!is_string($functional) || preg_match('/:(?:is|where|has|matches|-webkit-any)\s*\(/i', $functional)) {
            return false;
        }
        $parts = self::split_selector_list($selector);
        if (!$parts) {
            return false;
        }
        foreach ($parts as $part) {
            // Disallow sibling combinators, which can leave the Builder tree.
            // Child and descendant selectors remain supported.
            $combinators = preg_replace('/\([^()]*\)|\[[^\]]*\]/', '', $part);
            if (!is_string($combinators) || preg_match('/[+~]/', $combinators)) {
                return false;
            }
            // Selectors must contain an actual Themify Builder scoping token.
            if (!preg_match('/(?:^|[^a-zA-Z0-9_-])(?:\.themify_builder(?:[_-][a-zA-Z0-9_-]+)?|\.tb_[a-zA-Z0-9_-]+|#themify_builder(?:[_-][a-zA-Z0-9_-]+)?)/', $part)) {
                return false;
            }
            // Never permit a document-level target after a Builder selector.
            // This also prevents an attacker from targeting unrelated markup
            // with a selector list containing a superficially valid token.
            if (preg_match('/(?:^|[\s>])(?:html|body|:root)(?=$|[\s>.:#\[])/i', $part)) {
                return false;
            }
        }
        return true;
    }

    private static function split_selector_list(string $selector): array {
        $parts = array();
        $buf = '';
        $depth = 0;
        $quote = '';
        $len = strlen($selector);
        for ($i = 0; $i < $len; $i++) {
            $ch = $selector[$i];
            if ($quote !== '') {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $selector[++$i];
                    continue;
                }
                if ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === '(' || $ch === '[') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === ')' || $ch === ']') {
                if ($depth > 0) {
                    $depth--;
                }
                $buf .= $ch;
                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $part = trim($buf);
                if ($part === '') {
                    return array();
                }
                $parts[] = $part;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($depth !== 0 || $quote !== '') {
            return array();
        }
        $part = trim($buf);
        if ($part === '') {
            return array();
        }
        $parts[] = $part;
        return $parts;
    }

    private static function sanitize_on_the_fly_rules(array $rules): array {
        $out = array();
        foreach ($rules as $selector => $arr) {
            if (!self::is_builder_css_selector((string) $selector)) {
                continue;
            }
            if (!is_array($arr)) {
                $arr = array((string) $arr);
            }
            $decls = array();
            foreach ($arr as $decl) {
                $decl = themify_sanitize_inline_css((string) $decl);
                if ($decl !== '') {
                    $decls[] = $decl;
                }
            }
            if ($decls) {
                $out[$selector] = $decls;
            }
        }
        return $out;
    }

    private static function sanitize_on_the_fly_css($data): array {
        if (!is_array($data)) {
            return array();
        }
        $bps = array_merge(array('desktop'), array_keys(themify_get_breakpoints()));
        $out = array();
        if (!empty($data['gs']) && is_array($data['gs'])) {
            $gs = array();
            if (isset($data['gs']['used'])) {
                $gs['used'] = sanitize_text_field((string) $data['gs']['used']);
            }
            foreach ($bps as $bp) {
                if (!empty($data['gs'][$bp]) && is_array($data['gs'][$bp])) {
                    $gs[$bp] = self::sanitize_on_the_fly_rules($data['gs'][$bp]);
                }
            }
            $out['gs'] = $gs;
        }
        foreach ($bps as $bp) {
            if (!empty($data[$bp]) && is_array($data[$bp])) {
                $out[$bp] = self::sanitize_on_the_fly_rules($data[$bp]);
            }
        }
        foreach (array('fonts', 'cf_fonts') as $k) {
            if (!empty($data[$k]) && is_array($data[$k])) {
                $fonts = themify_sanitize_builder_font_map($data[$k]);
                if ($fonts) {
                    $out[$k] = $fonts;
                }
            }
        }
        return $out;
    }

    /**
     * Signed rebuild previews must not be stored by LiteSpeed, a host cache, or a CDN.
     */
    public static function rebuild_cache_bypass() {
        if (is_admin() || empty($_GET['tb_css_rebuild'])) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (!defined('LSCACHE_NO_CACHE')) {
            define('LSCACHE_NO_CACHE', true);
        }
        if (!headers_sent()) {
            nocache_headers();
            header('X-LiteSpeed-Cache-Control: no-cache');
            header('X-Accel-Expires: 0');
        }
        do_action('litespeed_control_set_nocache', 'themify builder css rebuild');
    }

    public static function rebuild_hide_admin_bar($show) {
        if (!is_admin() && !empty($_GET['tb_css_rebuild'])) {
            return false;
        }
        return $show;
    }

    private static function rebuild_request_id(): int {
        if (!is_user_logged_in()) {
            return 0;
        }
        $id = self::rebuild_target_id();
        if ($id <= 0 || !current_user_can('edit_post', $id)) {
            return 0;
        }
        $nonce = sanitize_text_field(wp_unslash($_GET['tb_css_rebuild']));
        if (wp_verify_nonce($nonce, self::rebuild_nonce_action($id))) {
            return $id;
        }
        $requested = isset($_GET['tb_post']) ? absint($_GET['tb_post']) : 0;
        // Same editor, this post. The token may have been signed while switched
        // to another site, which makes the site id inside the token differ.
        if ($requested === $id && $nonce !== '') {
            return $id;
        }
        return 0;
    }

    /**
     * The generator only needs jQuery and the Themify scripts. reCAPTCHA, analytics,
     * and other plugin scripts block the preview and are not part of the CSS.
     */
    public static function rebuild_strip_scripts() {
        if (self::rebuild_request_id() <= 0) {
            return;
        }
        global $wp_scripts;
        if (!$wp_scripts instanceof WP_Scripts) {
            return;
        }
        $keep = array();
        $stack = array('tb_builder_js_style', 'themify-main-script', 'jquery', 'jquery-core', 'jquery-migrate');
        while ($stack) {
            $handle = array_pop($stack);
            if (isset($keep[$handle])) {
                continue;
            }
            $keep[$handle] = true;
            if (isset($wp_scripts->registered[$handle])) {
                foreach ((array) $wp_scripts->registered[$handle]->deps as $dep) {
                    $stack[] = $dep;
                }
            }
        }
        foreach ((array) $wp_scripts->queue as $handle) {
            if (!isset($keep[$handle])) {
                wp_dequeue_script($handle);
            }
        }
    }

    /**
     * Drop third-party script tags one at a time. A pattern that spans from the
     * first script to the Tag Manager snippet also removes jQuery and themify_vars.
     */
    public static function rebuild_filter_html($html) {
        if (!is_string($html) || $html === '') {
            return $html;
        }
        $filtered = preg_replace_callback('#<script\b[^>]*>.*?</script>#is', static function (array $match): string {
            $tag = $match[0];
            if (preg_match('#googletagmanager\.com/(?:gtm\.js|ns\.html)#i', $tag)) {
                return '';
            }
            if (preg_match('#\bsrc=["\'][^"\']*(?:google-analytics|googletagservices|hotjar|facebook\.net|connect\.facebook|jotfor|trustindex|doubleclick|hs-scripts|clarity\.ms|licdn\.com)#i', $tag)) {
                return '';
            }
            return $tag;
        }, $html);
        if (!is_string($filtered)) {
            return $html;
        }
        $noscript = preg_replace('#<noscript>\s*<iframe\b[^>]*googletagmanager\.com[^>]*>\s*</iframe>\s*</noscript>#i', '', $filtered);
        return is_string($noscript) ? $noscript : $filtered;
    }

    public static function rebuild_fallback_script() {
        $id = self::rebuild_request_id();
        if ($id <= 0) {
            return;
        }
        echo '<script>window.tbCssRebuildPostId=', $id, ';document.addEventListener("DOMContentLoaded",function(){if(window.tbCssRebuildReported)return;var tag=document.getElementById("tb_builder_js_style-js");parent.postMessage({type:"tb-css-rebuild",postId:"', $id, '",success:false,reason:tag?"The CSS generator loaded but did not start. Builder style data may be blocked.":"This page does not render Builder content."},location.origin);});</script>';
    }

    /**
     * Rebuild requests run in an authenticated browser, using the same front-end
     * styling engine as ordinary pages. Public AJAX CSS writes remain disabled.
     */
    public static function rebuild_preview_headers() {
        if (!isset($_GET['tb_css_rebuild'])) {
            return;
        }
        self::rebuild_cache_bypass();
        if (!is_user_logged_in()) {
            wp_die(esc_html__('The rebuild preview is not logged in on this site.', 'themify'), '', array('response' => 403));
        }
        $id = self::rebuild_target_id();
        if ($id <= 0) {
            wp_die(esc_html__('This URL did not open the post being rebuilt.', 'themify'), '', array('response' => 403));
        }
        if (!current_user_can('edit_post', $id)) {
            wp_die(esc_html__('This account cannot edit the post being rebuilt.', 'themify'), '', array('response' => 403));
        }
        if (self::rebuild_request_id() <= 0) {
            wp_die(esc_html__('The rebuild token did not match this post.', 'themify'), '', array('response' => 403));
        }
        ob_start(array(__CLASS__, 'rebuild_filter_html'));
    }

    public static function enqueue_rebuild_admin() {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Consume the pending flag only when the admin script will load.
        // "site" is the admin-bar action and must rebuild this site only.
        $pending_raw = get_user_meta(get_current_user_id(), 'tb_css_rebuild_pending', true);
        $force_site = $pending_raw === 'site';
        $pending = !empty($pending_raw);
        if ($pending) {
            delete_user_meta(get_current_user_id(), 'tb_css_rebuild_pending');
        }
        $job = self::rebuild_job_summary();
        wp_enqueue_script('tb-css-rebuild-queue', THEMIFY_URI . '/js/admin/css-rebuild-queue.js', array(), (string) filemtime(THEMIFY_DIR . '/js/admin/css-rebuild-queue.js'), true);
        wp_localize_script('tb-css-rebuild-queue', 'tbCssRebuildConfig', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tf_nonce'),
            'origin' => home_url(),
            'blogId' => get_current_blog_id(),
            'canNetwork' => is_multisite() && current_user_can('manage_network'),
            'forceSite' => $force_site,
            'autoStart' => $pending && !$job,
            'toolsUrl' => function_exists('themify_is_themify_theme') && themify_is_themify_theme()
                ? admin_url('admin.php?page=themify') . '#setting-themify_builder-tools'
                : admin_url('admin.php?page=themify-builder&tab=tools'),
            'job' => $job ? $job : false,
            'labels' => array(
                'preparing' => __('Preparing. Do not close this browser yet.', 'themify'),
                'running' => __('Regenerating CSS. Do not close this browser yet.', 'themify'),
                'finished' => __('CSS regeneration completed.', 'themify'),
                'failed' => __('Some stylesheets could not be regenerated.', 'themify'),
                'paused' => __('Regeneration paused.', 'themify'),
                'stop' => __('Stop', 'themify'),
                'resume' => __('Resume', 'themify'),
                'restart' => __('Start Again', 'themify'),
                'buttonRunning' => __('Regenerating', 'themify'),
                'buttonDone' => __('Done', 'themify')
            )
        ));
    }

    public static function rebuild_start_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        if (!empty($_POST['all']) && (!is_multisite() || !current_user_can('manage_network'))) {
            wp_send_json_error(__('Only a network administrator can regenerate CSS for every site.', 'themify'));
        }
        // Do not delete generated CSS or concat assets before replacements exist.
        // The authenticated browser queue forces regeneration of every listed post.
        wp_send_json_success();
    }

    public static function rebuild_finish_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        if (!empty($_POST['all']) && is_multisite() && current_user_can('manage_network')) {
            Themify_Enqueue_Assets::clearConcateCss('all');
        } else {
            Themify_Enqueue_Assets::clearConcateCss();
        }
        delete_user_meta(get_current_user_id(), 'tb_css_rebuild_job');
        wp_send_json_success();
    }

    /**
     * Remember an interrupted rebuild for this admin. Preview links expire,
     * so only blog and post ids are stored.
     */
    private static function rebuild_job_summary() {
        $job = get_user_meta(get_current_user_id(), 'tb_css_rebuild_job', true);
        if (!is_array($job) || empty($job['posts']) || !is_array($job['posts'])) {
            return null;
        }
        if (!empty($job['network']) && (!is_multisite() || !current_user_can('manage_network'))) {
            return null;
        }
        $total = count($job['posts']);
        $next = isset($job['next']) ? (int) $job['next'] : 0;
        $retry = isset($job['retry']) && is_array($job['retry']) ? count($job['retry']) : 0;
        if ($next >= $total && $retry < 1) {
            delete_user_meta(get_current_user_id(), 'tb_css_rebuild_job');
            return null;
        }
        return array(
            'network' => !empty($job['network']) ? 1 : 0,
            'total' => $total,
            'next' => max(0, min($next, $total)),
            'retry' => $retry,
            'paused' => !empty($job['paused']) ? 1 : 0
        );
    }

    public static function rebuild_job_save_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        $user_id = get_current_user_id();
        $key = 'tb_css_rebuild_job';
        if (!empty($_POST['clear'])) {
            delete_user_meta($user_id, $key);
            wp_send_json_success();
        }
        if (isset($_POST['posts'])) {
            $network = !empty($_POST['network']);
            if ($network && (!is_multisite() || !current_user_can('manage_network'))) {
                wp_send_json_error(__('Only a network administrator can regenerate CSS for every site.', 'themify'));
            }
            $raw = isset($_POST['posts']) ? (string) wp_unslash($_POST['posts']) : '';
            if ($raw === '' || !preg_match('/^[0-9:,]+$/', $raw)) {
                wp_send_json_error(__('The regeneration list could not be saved.', 'themify'));
            }
            $posts = array();
            foreach (explode(',', $raw) as $pair) {
                $bits = explode(':', $pair);
                if (count($bits) !== 2) {
                    continue;
                }
                $blog_id = (int) $bits[0];
                $post_id = (int) $bits[1];
                if ($post_id < 1) {
                    continue;
                }
                $posts[] = array($blog_id > 0 ? $blog_id : get_current_blog_id(), $post_id);
                if (count($posts) >= 20000) {
                    break;
                }
            }
            if (!$posts) {
                wp_send_json_error(__('The regeneration list could not be saved.', 'themify'));
            }
            update_user_meta($user_id, $key, array(
                'network' => $network ? 1 : 0,
                'next' => 0,
                'posts' => $posts,
                'retry' => array()
            ));
            wp_send_json_success(array('total' => count($posts)));
        }
        $job = get_user_meta($user_id, $key, true);
        if (!is_array($job) || empty($job['posts']) || !is_array($job['posts'])) {
            wp_send_json_success();
        }
        $total = count($job['posts']);
        $next = isset($_POST['next']) ? absint($_POST['next']) : (isset($job['next']) ? (int) $job['next'] : 0);
        if ($next > $total) {
            $next = $total;
        }
        $retry = array();
        $raw_retry = isset($_POST['retry']) ? (string) wp_unslash($_POST['retry']) : '';
        if ($raw_retry !== '' && preg_match('/^[0-9,]+$/', $raw_retry)) {
            foreach (explode(',', $raw_retry) as $index) {
                $index = (int) $index;
                if ($index >= 0 && $index < $total && count($retry) < 500) {
                    $retry[$index] = $index;
                }
            }
        }
        $job['next'] = $next;
        $job['retry'] = array_values($retry);
        if (isset($_POST['paused'])) {
            $job['paused'] = !empty($_POST['paused']) ? 1 : 0;
        }
        update_user_meta($user_id, $key, $job);
        wp_send_json_success();
    }

    public static function rebuild_job_urls_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        $job = get_user_meta(get_current_user_id(), 'tb_css_rebuild_job', true);
        if (!is_array($job) || empty($job['posts']) || !is_array($job['posts'])) {
            wp_send_json_error(__('No regeneration is waiting to resume.', 'themify'));
        }
        if (!empty($job['network']) && (!is_multisite() || !current_user_can('manage_network'))) {
            wp_send_json_error(__('Only a network administrator can regenerate CSS for every site.', 'themify'), 403);
        }
        $stored = array_values($job['posts']);
        $total = count($stored);
        $indexes = array();
        $end = null;
        $retry_all = null;
        if (!empty($_POST['retry'])) {
            $retry_all = array();
            $saved = isset($job['retry']) && is_array($job['retry']) ? $job['retry'] : array();
            foreach ($saved as $index) {
                $index = (int) $index;
                if (isset($stored[$index]) && count($retry_all) < 500) {
                    $retry_all[] = $index;
                }
            }
            $indexes = array_slice($retry_all, 0, 40);
        } elseif (isset($_POST['indexes'])) {
            $raw = (string) wp_unslash($_POST['indexes']);
            if ($raw !== '' && preg_match('/^[0-9,]+$/', $raw)) {
                foreach (explode(',', $raw) as $index) {
                    $index = (int) $index;
                    if (isset($stored[$index])) {
                        $indexes[] = $index;
                    }
                    if (count($indexes) >= 40) {
                        break;
                    }
                }
            }
        } else {
            $offset = isset($_POST['offset']) ? absint($_POST['offset']) : (isset($job['next']) ? (int) $job['next'] : 0);
            if ($offset > $total) {
                $offset = $total;
            }
            $end = min($total, $offset + 20);
            for ($index = $offset; $index < $end; $index++) {
                $indexes[] = $index;
            }
        }
        $grouped = array();
        foreach ($indexes as $index) {
            $row = $stored[$index];
            $blog_id = isset($row[0]) ? (int) $row[0] : get_current_blog_id();
            $post_id = isset($row[1]) ? (int) $row[1] : 0;
            if ($post_id < 1) {
                continue;
            }
            if (!isset($grouped[$blog_id])) {
                $grouped[$blog_id] = array();
            }
            $grouped[$blog_id][] = array($index, $post_id);
        }
        $posts = array();
        foreach ($grouped as $blog_id => $items) {
            $switched = false;
            if (is_multisite() && (int) $blog_id !== get_current_blog_id()) {
                if (!current_user_can('manage_network') || !switch_to_blog((int) $blog_id)) {
                    continue;
                }
                $switched = true;
            }
            try {
                $name = get_bloginfo('name');
                $pro_templates = array_flip(self::active_pro_template_ids());
                foreach ($items as $item) {
                    $id = (int) $item[1];
                    $post_type = get_post_type($id);
                    if (!current_user_can('edit_post', $id) || get_post_status($id) !== 'publish' || $post_type === 'tglobal_style') {
                        continue;
                    }
                    if ($post_type === 'tbp_template' && !isset($pro_templates[$id])) {
                        continue;
                    }
                    $url = get_permalink($id);
                    if (!$url) {
                        continue;
                    }
                    $posts[] = array(
                        'index' => (int) $item[0],
                        'id' => $id,
                        'blogId' => (int) $blog_id,
                        'blogName' => is_string($name) ? $name : '',
                        'url' => self::rebuild_preview_url($id, $url)
                    );
                }
            } finally {
                if ($switched) {
                    restore_current_blog();
                }
            }
        }
        usort($posts, function ($a, $b) {
            return $a['index'] <=> $b['index'];
        });
        $payload = array(
            'posts' => $posts,
            'to' => $end,
            'total' => $total,
            'network' => !empty($job['network']) ? 1 : 0
        );
        if ($retry_all !== null) {
            $payload['retry'] = $retry_all;
        }
        wp_send_json_success($payload);
    }

    /**
     * A post needs a generated stylesheet only when a row, column, or module
     * has styling, a global style, or Builder custom CSS.
     */
    private static function builder_needs_css($data, int $post_id): bool {
        if (!is_array($data)) {
            return false;
        }
        foreach ($data as $row) {
            if (is_array($row) && self::layout_needs_css($row)) {
                return true;
            }
        }
        $custom = get_post_meta($post_id, 'tbp_custom_css', true);
        return is_string($custom) && trim($custom) !== '';
    }

    private static function layout_needs_css(array $element): bool {
        if (self::element_has_styling($element)) {
            return true;
        }
        if (isset($element['mod_name']) && $element['mod_name'] === 'text') {
            $settings = isset($element['mod_settings']) && is_array($element['mod_settings']) ? $element['mod_settings'] : array();
            if (self::style_has_value($settings)) {
                return true;
            }
        }
        $children = array();
        if (!empty($element['cols']) && is_array($element['cols'])) {
            $children = $element['cols'];
        }
        if (!empty($element['modules']) && is_array($element['modules'])) {
            $children = array_merge($children, $element['modules']);
        }
        foreach ($children as $child) {
            if (is_array($child) && self::layout_needs_css($child)) {
                return true;
            }
        }
        return false;
    }

    private static function element_has_styling(array $element): bool {
        if (!empty($element['global_styles'])) {
            return true;
        }
        return !empty($element['styling']) && is_array($element['styling']) && self::style_has_value($element['styling']);
    }

    private static function style_has_value(array $styling): bool {
        foreach ($styling as $key => $value) {
            // Text content can contain inline CSS. Apply-all checkboxes only link
            // existing values and do not create a stylesheet rule by themselves.
            if (is_string($key) && !self::is_builder_style_key($key)) {
                continue;
            }
            if (self::style_value_counts($value)) {
                return true;
            }
        }
        return false;
    }

    private static function is_builder_style_key(string $key): bool {
        if ($key === 'content_text' || $key === 'mod_title_text' || strpos($key, 'checkbox_') === 0) {
            return false;
        }
        return substr($key, -8) !== '_opp_top' && substr($key, -9) !== '_opp_left';
    }

    private static function style_value_counts($value): bool {
        if (is_array($value)) {
            return self::style_has_value($value);
        }
        if (!is_scalar($value)) {
            return false;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === '' || in_array($value, array('px', '%', 'em', 'rem', 'vh', 'vw', 'vmin', 'vmax', 'ch', 'ex'), true)) {
                return false;
            }
        }
        return $value !== '' && $value !== null && $value !== false;
    }

    /**
     * Published templates belonging to the active Builder Pro theme.
     * Each one is stored as themify-builder-{id}-generated.css.
     */
    private static function active_pro_template_ids(): array {
        if (!class_exists('Tbp_Themes', false) || !method_exists('Tbp_Themes', 'get_active_theme') || !method_exists('Tbp_Themes', 'get_theme_templates')) {
            return array();
        }
        $theme = Tbp_Themes::get_active_theme();
        if (empty($theme['ID'])) {
            return array();
        }
        $ids = Tbp_Themes::get_theme_templates('publish', -1, (int) $theme['ID']);
        if (!is_array($ids)) {
            return array();
        }
        $out = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Preview entries for the active Pro theme templates.
     */
    private static function active_pro_template_posts(): array {
        $posts = array();
        foreach (self::active_pro_template_ids() as $id) {
            if (!current_user_can('edit_post', $id) || get_post_status($id) !== 'publish') {
                continue;
            }
            $data = ThemifyBuilder_Data_Manager::get_data($id);
            if (!self::builder_needs_css($data, $id)) {
                continue;
            }
            $url = get_permalink($id);
            if (!$url) {
                continue;
            }
            $posts[] = array(
                'id' => $id,
                'url' => self::rebuild_preview_url($id, $url)
            );
        }
        return $posts;
    }

    /**
     * Post types that can be opened as a single view with Builder output.
     * Global Styles have no page stylesheet of their own. Active Pro theme
     * templates are added separately because the template post type is not public.
     */
    private static function rebuild_post_types(): array {
        $types = array();
        foreach (get_post_types(array(), 'objects') as $name => $object) {
            if ($name === 'tglobal_style' || $name === 'tglobal-style' || $name === 'tbp_template' || !is_post_type_viewable($object)) {
                continue;
            }
            if (Themify_Builder_Model::is_builder_disabled_for_post_type($name)) {
                continue;
            }
            $types[] = $name;
        }
        return $types;
    }

    private static function rebuild_nonce_action(int $post_id): string {
        return 'tb_css_rebuild_' . get_current_blog_id() . '_' . $post_id;
    }

    private static function rebuild_preview_url(int $post_id, string $url): string {
        return add_query_arg(array(
            'tb_css_rebuild' => wp_create_nonce(self::rebuild_nonce_action($post_id)),
            'tb_post' => $post_id,
        ), $url);
    }

    /**
     * The post this signed preview is allowed to rebuild.
     * Queried object wins. tb_post covers a URL that has not resolved yet.
     */
    private static function rebuild_target_id(): int {
        if (is_admin() || empty($_GET['tb_css_rebuild'])) {
            return 0;
        }
        $requested = isset($_GET['tb_post']) ? absint($_GET['tb_post']) : 0;
        $queried = (int) get_queried_object_id();
        if ($queried > 0 && $requested > 0 && $queried !== $requested) {
            return 0;
        }
        return $queried > 0 ? $queried : $requested;
    }

    public static function rebuild_sites_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!is_multisite() || !current_user_can('manage_network')) {
            wp_send_json_error(__('Only a network administrator can regenerate CSS for every site.', 'themify'), 403);
        }
        $sites = get_sites(array(
            'number' => 0,
            'deleted' => 0,
            'archived' => 0,
            'spam' => 0,
            'orderby' => 'id',
            'order' => 'ASC'
        ));
        $out = array();
        foreach ($sites as $site) {
            $blog_id = (int) $site->blog_id;
            $name = get_blog_option($blog_id, 'blogname');
            if (!is_string($name) || $name === '') {
                $name = $site->domain . $site->path;
            }
            $out[] = array('id' => $blog_id, 'name' => $name);
        }
        wp_send_json_success(array('sites' => $out));
    }

    public static function rebuild_list_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        $blog_id = isset($_POST['blog_id']) ? absint($_POST['blog_id']) : 0;
        $switched = false;
        if ($blog_id > 0 && is_multisite() && $blog_id !== get_current_blog_id()) {
            if (!current_user_can('manage_network')) {
                wp_send_json_error(__('Only a network administrator can regenerate CSS for every site.', 'themify'), 403);
            }
            if (!switch_to_blog($blog_id)) {
                wp_send_json_error(__('That network site could not be opened.', 'themify'));
            }
            $switched = true;
        }
        try {
            $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
            $types = self::rebuild_post_types();
            $posts = array();
            $pages = 0;
            $total = 0;
            if ($types) {
                $query = new WP_Query(array(
                    'post_type' => $types,
                    'post_status' => 'publish',
                    'posts_per_page' => 30,
                    'paged' => $page,
                    'fields' => 'ids',
                    'no_found_rows' => false,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'meta_query' => array('relation' => 'OR',
                        array('key' => ThemifyBuilder_Data_Manager::META_KEY, 'compare' => 'EXISTS'),
                        array('key' => ThemifyBuilder_Data_Manager::LEGACY_META_KEY, 'compare' => 'EXISTS')
                    )
                ));
                foreach ($query->posts as $id) {
                    $post_type = get_post_type($id);
                    if (!$post_type || $post_type === 'tglobal_style' || $post_type === 'tbp_template' || !is_post_type_viewable($post_type) || Themify_Builder_Model::is_builder_disabled_for_post_type($post_type)) {
                        continue;
                    }
                    $data = ThemifyBuilder_Data_Manager::get_data($id);
                    if (current_user_can('edit_post', $id) && self::builder_needs_css($data, (int) $id)) {
                        $url = get_permalink($id);
                        if ($url) {
                            $posts[] = array(
                                'id' => $id,
                                'url' => self::rebuild_preview_url((int) $id, $url)
                            );
                        }
                    }
                }
                $pages = (int) $query->max_num_pages;
                $total = (int) $query->found_posts;
            }
            if ($page === 1) {
                $posts = array_merge($posts, self::active_pro_template_posts());
            }
            $payload = array('posts' => $posts, 'page' => $page, 'pages' => $pages, 'total' => $total);
        } finally {
            if ($switched) {
                restore_current_blog();
            }
        }
        wp_send_json_success($payload);
    }

    /**
     * Regenerate CSS files Ajax
     *
     */
    public static function regenerate_css_files_ajax() {
        check_ajax_referer('tf_nonce', 'nonce');
        $result = self::regenerate_css_files();
        if ($result === 'finished') {
            Themify_Enqueue_Assets::clearConcateCss();
            wp_send_json_success();
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Find for old URLs in generated CSS files and regenerates them base on new URLs
     *
     */
    public static function regenerate_css_files(string $type = ''): string {
        if (current_user_can('manage_options')) {
            $path = self::get_stylesheet_dir();
            $error = true;
            if ($type === 'all' && is_multisite()) {
                $generatedDir = '/themify-css/';
                $path = dirname($path, 2);
                if (strpos($path, '/blogs.dir/') !== false) {
                    $path = dirname($path);
                    $generatedDir = '/files' . $generatedDir;
                }
                $path = rtrim($path, '/') . '/';
                if (Themify_Filesystem::is_dir($path) && ($handle = opendir($path))) {
                    $error = false;
                    set_time_limit(0);
                    while (false !== ($f = readdir($handle))) {
                        if ($f !== '.' && $f !== '..') {
                            $f = $path . $f . $generatedDir;
                            if (Themify_Filesystem::is_dir($f) && !Themify_Filesystem::delete($f)) {
                                $error = true;
                                break;
                            }
                        }
                    }
                    closedir($handle);
                }
            } else {
                $error = Themify_Filesystem::is_dir($path) && !Themify_Filesystem::delete($path);
            }

            return $error ? __('Something goes wrong. Please check the if the upload folder is writtable.') : 'finished';
        }
        return '';
    }

    /**
     * Find for old URLs in generated CSS files and delete them
     *
     */
    public static function remove_css_files(?int $post_id) {
        $css_file = self::get_stylesheet('basedir', $post_id)['url'];
        Themify_Filesystem::delete($css_file, 'f');
        Themify_Filesystem::delete(self::getTmpPath($css_file), 'f');
    }

    /**
     * Write stylesheet file.
     * 
     * @since 2.2.5
     * 
     * @return array
     */
    public static function write_stylesheet($style_id, $data, string $custom_css = '', bool $overwrite = true):array {
        // Information about how writing went.

        $results = array();
        $css = '';
        if (!empty($data)) {
            $breakpoints = themify_get_breakpoints();
            $fonts = array('fonts' => array(), 'cf_fonts' => array());
            $breakpoints = array('desktop' => '') + $breakpoints;
            if (!empty($data['gs'])) {
                $css .= '/*Builder GLOBAL CSS START*/' . PHP_EOL;
                if (!empty($data['gs']['used'])) {
                    $css .= '/*GS: ' . $data['gs']['used'] . '*/' . PHP_EOL;
                    unset($data['gs']['used']);
                }
                foreach ($breakpoints as $b => $bpoint) {
                    if (!empty($data['gs'][$b])) {
                        $styles = '';
                        foreach ($data['gs'][$b] as $selector => $arr) {
                            $styles .= $selector . '{' . implode('', $arr) . '}';
                            if ($selector[0] === '@') {
                                $styles .= '}';
                            }
                            $styles .= PHP_EOL;
                        }
                        if ($b !== 'desktop') {
                            $max = is_array($bpoint) ? $bpoint[1] : $bpoint;
                            $styles = PHP_EOL . sprintf('@media(max-width:%spx){', $max) . PHP_EOL . $styles . '}';
                        }
                        $css .= $styles;
                    }
                }
                unset($data['gs']);
                $css .= '/*Builder GLOBAL CSS END*/' . PHP_EOL;
            }
            foreach ($breakpoints as $b => $bpoint) {
                if (!empty($data[$b])) {
                    $styles = '';
                    foreach ($data[$b] as $selector => $arr) {
                        $styles .= $selector . '{' . implode('', $arr) . '}';
                        if ($selector[0] === '@') {
                            $styles .= '}';
                        }
                        $styles .= PHP_EOL;
                    }
                    if ($b !== 'desktop') {
                        $max = is_array($bpoint) ? $bpoint[1] : $bpoint;
                        $styles = PHP_EOL . sprintf('@media(max-width:%spx){', $max) . PHP_EOL . $styles . '}';
                    }
                    $css .= $styles;
                    unset($data[$b]);
                }
            }
            foreach (array_keys($fonts) as $ftype) {
                if (!empty($data[$ftype]) && is_array($data[$ftype])) {
                    foreach (themify_sanitize_builder_font_map($data[$ftype]) as $f => $w) {
                        $v = 'fonts' === $ftype ? str_replace(' ', '+', $f) : $f;
                        if (!empty($w)) {
                            $v .= ':' . implode(',', $w);
                        }
                        $fonts[$ftype][] = $v;
                    }
                }
            }
            if (!empty($data['bg']) && is_user_logged_in()) {
                $created = array();
                foreach ($data['bg'] as $bg) {
                    if (!isset($created[$bg])) {
                        $created[$bg] = true;
                        themify_generateWebp($bg);
                    }
                }
                $created = null;
            }
        }
        $data = null;
        $css_file = self::get_stylesheet('basedir', $style_id)['url'];
        if ($overwrite !== true && self::is_readable_and_not_empty($css_file)) {
            $tmp = self::get_stylesheet('baseurl', $style_id);
            return array(
                'css_file' => $tmp['url'],
                'write' => true
            );
        }
        if (!empty($custom_css)) {
            $css .= '/*Builder Custom CSS START*/' . PHP_EOL . $custom_css . PHP_EOL . '/*Builder Custom CSS END*/';
        }
        unset($custom_css);
        if (!empty($css)) {
            $css = apply_filters('themify_builder_stylesheet_css', $css, $style_id, $css_file);
            $css = '/* Generated from ' . get_post_type($style_id) . ': ' . get_post_field('post_name', $style_id) . " */\r\n" . $css;
            // Write to a sibling temporary file and publish only after the complete
            // new stylesheet is ready. A failed write must not remove a good file.
            $replacement = $css_file . '.rebuild-' . wp_generate_password(12, false, false);
            $write = Themify_Filesystem::put_contents($replacement, $css);
            if ($write) {
                $write = @rename($replacement, $css_file);
                if (!$write) {
                    Themify_Filesystem::delete($replacement, 'f');
                }
            }

            unset($css_file, $css);
            if ($write) {
                // Add information about writing.
                $tmp = self::get_stylesheet('baseurl', $style_id);
                $results['css_file'] = $tmp['url'];
                $results['write'] = $write;
                unset($tmp);
                // Save Fonts
                if (!empty($fonts)) {
                    foreach (array_keys($fonts) as $ftype) {
                        $option_key = 'fonts' === $ftype ? 'themify_builder_google_fonts' : 'themify_builder_cf_fonts';
                        $builder_fonts = get_option($option_key);
                        if (!empty($fonts[$ftype])) {
                            $fonts[$ftype] = implode('|', $fonts[$ftype]);
                            if (!is_array($builder_fonts)) {
                                $builder_fonts = array();
                            }
                            if (isset($builder_fonts[$style_id])) {
                                $builder_fonts[$style_id] = $fonts[$ftype];
                                $entry_fonts = $builder_fonts;
                            } else {
                                $entry_fonts = array($style_id => $fonts[$ftype]) + $builder_fonts;
                            }
                            update_option($option_key, $entry_fonts, false);
                        } 
                        elseif (isset($builder_fonts[$style_id])) {
                            unset($builder_fonts[$style_id]);
                            update_option($option_key, $builder_fonts, false);
                        }
                    }
                }
            } else {
                $results['write'] = esc_html__('Styles can`t be written.Please check permission of uploading folder', 'themify');
            }
        } else {
            // Add information about writing.
            $results['write'] = esc_html__('Nothing written. Empty CSS.', 'themify');
        }
        return $results;
    }

    public static function get_stylesheet_dir(string $mode = 'basedir'):string {
        return \themify_upload_dir($mode) . '/themify-css';
    }

    /**
     * Return the URL or the directory path for a template, template part or content builder styling stylesheet.
     * 
     * @since 2.2.5
     *
     * @param string $mode Whether to return the directory or the URL. Can be 'basedir' or 'baseurl' correspondingly. 
     * @param int $single ID of layout, layour part or entry that we're working with.
     *
     * @return string
     */
    private static function get_stylesheet(string $mode = 'basedir', ?int $id = null):array {
        if ($id === null) {
            $id = Themify_Builder_Model::get_ID();
        }
        $path = self::get_stylesheet_dir($mode);
        if ('basedir' === $mode && !Themify_Filesystem::is_dir($path)) {
            wp_mkdir_p($path);
        }

        /**
         * Filters the return URL or directory path including the file name.
         *
         * @param string $stylesheet Path or URL for the global styling stylesheet.
         * @param string $mode What was being retrieved, 'basedir' or 'baseurl'.
         * @param int $id ID of the template, template part or content builder that we're fetching.
         *
         */
        return array('id' => $id, 'url' => apply_filters('themify_builder_get_stylesheet', "$path/themify-builder-$id-generated.css", $mode, $id));
    }

    /**
     * Enqueues Google Fonts
     * 
     * @since 2.2.6
     */
    public static function getFonts(?int $post_id = null):array {
        if (!defined('THEMIFY_GOOGLE_FONTS') || THEMIFY_GOOGLE_FONTS !== false) {
            $entry_google_fonts = get_option('themify_builder_google_fonts');
            if (!empty($entry_google_fonts) && is_array($entry_google_fonts)) {
                $gsFonts = Themify_Global_Styles::used_global_styles($post_id);
                $gsFonts[] = array('id' => $post_id ? $post_id : Themify_Builder_Model::get_ID());
                $str = '';
                foreach ($gsFonts as $gs) {
                    if (isset($entry_google_fonts[$gs['id']])) {
                        $str .= '|' . $entry_google_fonts[$gs['id']];
                    }
                }
                return explode('|', $str);
            }
        }
        return array();
    }

    /**
     * Checks whether a file exists, can be loaded and is not empty.
     * 
     * @since 2.2.5
     * 
     * @param string $file_path Path in server to the file to check.
     * 
     * @return bool
     */
    private static function is_readable_and_not_empty(string $file_path = ''):bool {
        return empty($file_path) ? false : is_readable($file_path) && 0 !== filesize($file_path);
    }

    public static function slider_live_styling():void {
        check_ajax_referer('tf_nonce', 'nonce');
        $bg_slider_data = json_decode(stripslashes($_POST['tb_background_slider_data']), true);
        $row_or_col = array(
            'styling' => array(
                'background_slider' => urldecode($bg_slider_data['shortcode']),
                'background_type' => 'slider',
                'background_slider_mode' => $bg_slider_data['mode'],
                'background_slider_speed' => $bg_slider_data['speed'],
                'background_slider_size' => $bg_slider_data['size'],
            )
        );
        Themify_Builder_Component_Row::do_slider_background($row_or_col, $bg_slider_data['type']);
        wp_die();
    }

    /**
     * Converts color in hexadecimal format to RGB format.
     *
     * @since 1.9.6
     *
     * @param string $hex Color in hexadecimal format.
     * @return string Color in RGB components separated by comma.
     */
    private static function hex2rgb(string $hex):string {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $r = substr($hex, 0, 1);
            $g = substr($hex, 1, 1);
            $b = substr($hex, 2, 1);
            $r = hexdec($r . $r);
            $g = hexdec($g . $g);
            $b = hexdec($b . $b);
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return implode(',', array($r, $g, $b));
    }

    /**
     * Get RGBA color format from hex color
     *
     * @return string
     */
    public static function get_rgba_color(string $color):string {
        if (strpos($color, 'rgba') !== false || strpos($color, 'var(--') === 0) {
            return $color;
        }
        if (strpos($color, '--') === 0) {
            return 'var(' . $color . ')';
        }
        $color = explode('_', $color);
        $opacity = isset($color[1]) && $color[1] !== '' ? $color[1] : '1';
        if ($opacity >= 0 && $opacity !== '1' && $opacity !== '1.00' && $opacity !== '0.99') {
            return 'rgba(' . self::hex2rgb($color[0]) . ', ' . $opacity . ')';
        }
        return $color[0] !== '' ? ('#' . str_replace('#', '', $color[0])) : '';
    }
}
