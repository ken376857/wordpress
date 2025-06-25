<?php
/**
 * Plugin Name: WordPress Auto Save System
 * Plugin URI: https://github.com/ken376857/wordpress
 * Description: 高度な自動下書き保存システム。リアルタイムで下書きを保存し、データの損失を防ぎます。
 * Version: 1.0.0
 * Author: Ken Tanabe
 * License: GPL v2 or later
 * Text Domain: wp-auto-save
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_AUTO_SAVE_VERSION', '1.0.0');
define('WP_AUTO_SAVE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_AUTO_SAVE_PLUGIN_PATH', plugin_dir_path(__FILE__));

class WP_Auto_Save_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('wp-auto-save', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        add_action('wp_ajax_auto_save_draft', array($this, 'handle_auto_save'));
        add_action('wp_ajax_nopriv_auto_save_draft', array($this, 'handle_auto_save'));
        add_action('wp_ajax_get_draft_content', array($this, 'get_draft_content'));
        add_action('wp_ajax_nopriv_get_draft_content', array($this, 'get_draft_content'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        add_action('wp_footer', array($this, 'add_frontend_editor'));
        
        $this->create_drafts_table();
        $this->schedule_cleanup();
    }
    
    public function admin_init() {
        add_settings_section(
            'wp_auto_save_settings',
            __('自動保存設定', 'wp-auto-save'),
            array($this, 'settings_section_callback'),
            'writing'
        );
        
        add_settings_field(
            'auto_save_interval',
            __('自動保存間隔（秒）', 'wp-auto-save'),
            array($this, 'auto_save_interval_callback'),
            'writing',
            'wp_auto_save_settings'
        );
        
        register_setting('writing', 'auto_save_interval', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => array($this, 'sanitize_interval')
        ));
    }
    
    public function settings_section_callback() {
        echo '<p>' . __('WordPress自動保存システムの設定を行います。', 'wp-auto-save') . '</p>';
    }
    
    public function auto_save_interval_callback() {
        $interval = get_option('auto_save_interval', 30);
        echo '<input type="number" name="auto_save_interval" value="' . esc_attr($interval) . '" min="5" max="300" />';
        echo '<p class="description">' . __('5秒から300秒の間で設定してください。', 'wp-auto-save') . '</p>';
    }
    
    public function sanitize_interval($value) {
        $value = intval($value);
        return max(5, min(300, $value));
    }
    
    public function create_drafts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            post_id bigint(20) unsigned DEFAULT 0,
            title text,
            content longtext,
            excerpt text,
            post_type varchar(20) DEFAULT 'post',
            post_status varchar(20) DEFAULT 'auto-draft',
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY updated_at (updated_at),
            KEY post_type (post_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function enqueue_frontend_scripts() {
        if ($this->should_load_editor()) {
            wp_enqueue_script(
                'wp-auto-save-frontend',
                WP_AUTO_SAVE_PLUGIN_URL . 'auto-save.js',
                array('jquery'),
                WP_AUTO_SAVE_VERSION,
                true
            );
            
            $this->localize_script('wp-auto-save-frontend');
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script(
                'wp-auto-save-admin',
                WP_AUTO_SAVE_PLUGIN_URL . 'auto-save.js',
                array('jquery'),
                WP_AUTO_SAVE_VERSION,
                true
            );
            
            $this->localize_script('wp-auto-save-admin');
        }
    }
    
    private function localize_script($handle) {
        $interval = get_option('auto_save_interval', 30) * 1000;
        
        wp_localize_script($handle, 'wpAutoSaveConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_auto_save_nonce'),
            'postId' => get_the_ID() ?: 0,
            'userId' => get_current_user_id(),
            'interval' => $interval,
            'messages' => array(
                'saved' => __('下書きが保存されました', 'wp-auto-save'),
                'saving' => __('保存中...', 'wp-auto-save'),
                'error' => __('保存に失敗しました', 'wp-auto-save'),
                'restored' => __('下書きが復元されました', 'wp-auto-save')
            )
        ));
        
        wp_add_inline_script($handle, '
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof AutoSave !== "undefined") {
                    window.wpAutoSaveInstance = new AutoSave({
                        endpoint: wpAutoSaveConfig.ajaxUrl,
                        nonce: wpAutoSaveConfig.nonce,
                        postId: wpAutoSaveConfig.postId,
                        interval: wpAutoSaveConfig.interval,
                        action: "auto_save_draft"
                    });
                }
            });
        ');
    }
    
    private function should_load_editor() {
        return isset($_GET['wp_editor']) || is_page('editor');
    }
    
    public function add_frontend_editor() {
        if ($this->should_load_editor()) {
            include WP_AUTO_SAVE_PLUGIN_PATH . 'wp-editor.html';
        }
    }
    
    public function handle_auto_save() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_auto_save_nonce')) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'wp-auto-save'));
        }
        
        $session_id = $this->get_session_id();
        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        $existing = null;
        if ($post_id > 0) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d ORDER BY updated_at DESC LIMIT 1",
                $post_id
            ));
        } else {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s AND post_id = 0 ORDER BY updated_at DESC LIMIT 1",
                $session_id
            ));
        }
        
        $data = array(
            'session_id' => $session_id,
            'user_id' => $user_id,
            'title' => $title,
            'content' => $content,
            'post_type' => $post_type,
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id),
                array('%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            $draft_id = $existing->id;
        } else {
            $data['post_id'] = $post_id;
            $data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert($table_name, $data);
            $draft_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            $response_data = array(
                'message' => __('下書きが保存されました', 'wp-auto-save'),
                'draft_id' => $draft_id,
                'timestamp' => current_time('mysql'),
                'session_id' => $session_id
            );
            
            if ($post_id == 0 && (!empty($title) || !empty($content))) {
                $new_post_id = $this->create_wp_post($title, $content, $post_type, $user_id);
                if ($new_post_id) {
                    $wpdb->update(
                        $table_name,
                        array('post_id' => $new_post_id),
                        array('id' => $draft_id),
                        array('%d'),
                        array('%d')
                    );
                    $response_data['post_id'] = $new_post_id;
                }
            }
            
            do_action('wp_auto_save_draft_saved', $draft_id, $response_data);
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(__('下書きの保存に失敗しました', 'wp-auto-save'));
        }
    }
    
    public function get_draft_content() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_auto_save_nonce')) {
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'wp-auto-save'));
        }
        
        $session_id = $_POST['session_id'] ?? $this->get_session_id();
        $post_id = intval($_POST['post_id'] ?? 0);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        if ($post_id > 0) {
            $draft = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d ORDER BY updated_at DESC LIMIT 1",
                $post_id
            ));
        } else {
            $draft = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s ORDER BY updated_at DESC LIMIT 1",
                $session_id
            ));
        }
        
        if ($draft) {
            wp_send_json_success(array(
                'title' => $draft->title,
                'content' => $draft->content,
                'post_type' => $draft->post_type,
                'updated_at' => $draft->updated_at,
                'draft_id' => $draft->id
            ));
        } else {
            wp_send_json_error(__('下書きが見つかりませんでした', 'wp-auto-save'));
        }
    }
    
    private function create_wp_post($title, $content, $post_type = 'post', $user_id = 0) {
        if (empty($title) && empty($content)) {
            return false;
        }
        
        $post_data = array(
            'post_title' => $title ?: __('無題', 'wp-auto-save'),
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => $post_type,
            'post_author' => $user_id ?: 1,
            'meta_input' => array(
                '_auto_save_origin' => true,
                '_auto_save_timestamp' => current_time('timestamp')
            )
        );
        
        return wp_insert_post($post_data);
    }
    
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['wp_auto_save_session_id'])) {
            $_SESSION['wp_auto_save_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['wp_auto_save_session_id'];
    }
    
    public function schedule_cleanup() {
        if (!wp_next_scheduled('wp_auto_save_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_auto_save_cleanup');
        }
        
        add_action('wp_auto_save_cleanup', array($this, 'cleanup_old_drafts'));
    }
    
    public function cleanup_old_drafts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        $days = apply_filters('wp_auto_save_cleanup_days', 7);
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($deleted) {
            error_log("WP Auto Save: Cleaned up {$deleted} old drafts older than {$days} days");
        }
    }
    
    public function activate() {
        $this->create_drafts_table();
        $this->schedule_cleanup();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('wp_auto_save_cleanup');
    }
    
    public static function uninstall() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        delete_option('auto_save_interval');
        
        wp_clear_scheduled_hook('wp_auto_save_cleanup');
    }
}

WP_Auto_Save_Plugin::get_instance();

register_uninstall_hook(__FILE__, array('WP_Auto_Save_Plugin', 'uninstall'));
?>