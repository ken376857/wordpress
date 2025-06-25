<?php
class AutoSaveHandler {
    
    public function __construct() {
        add_action('wp_ajax_auto_save_draft', array($this, 'handle_auto_save'));
        add_action('wp_ajax_nopriv_auto_save_draft', array($this, 'handle_auto_save'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        $this->create_drafts_table();
    }
    
    public function create_drafts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            post_id mediumint(9) DEFAULT 0,
            title text,
            content longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY post_id (post_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function enqueue_scripts() {
        if (is_admin() || $this->is_post_editor_page()) {
            wp_enqueue_script(
                'auto-save-js',
                plugins_url('auto-save.js', __FILE__),
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_localize_script('auto-save-js', 'autoSaveConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('auto_save_nonce'),
                'postId' => get_the_ID() ?: 0,
                'interval' => apply_filters('auto_save_interval', 30000)
            ));
            
            wp_add_inline_script('auto-save-js', '
                document.addEventListener("DOMContentLoaded", function() {
                    if (typeof AutoSave !== "undefined") {
                        window.autoSaveInstance = new AutoSave({
                            endpoint: autoSaveConfig.ajaxUrl,
                            nonce: autoSaveConfig.nonce,
                            postId: autoSaveConfig.postId,
                            interval: autoSaveConfig.interval
                        });
                    }
                });
            ');
        }
    }
    
    private function is_post_editor_page() {
        global $pagenow;
        return in_array($pagenow, array('post.php', 'post-new.php')) || 
               (isset($_GET['page']) && strpos($_GET['page'], 'edit') !== false);
    }
    
    public function handle_auto_save() {
        if (!wp_verify_nonce($_POST['nonce'], 'auto_save_nonce')) {
            wp_die('Security check failed');
        }
        
        $session_id = $this->get_session_id();
        $post_id = intval($_POST['post_id']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
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
        
        if ($existing) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'title' => $title,
                    'content' => $content,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            $draft_id = $existing->id;
        } else {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'post_id' => $post_id,
                    'title' => $title,
                    'content' => $content
                ),
                array('%s', '%d', '%s', '%s')
            );
            
            $draft_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            $response_data = array(
                'message' => '下書きが保存されました',
                'draft_id' => $draft_id,
                'timestamp' => current_time('mysql')
            );
            
            if ($post_id == 0) {
                $new_post_id = $this->create_new_post($title, $content);
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
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error('下書きの保存に失敗しました');
        }
    }
    
    private function create_new_post($title, $content) {
        if (empty($title) && empty($content)) {
            return false;
        }
        
        $post_data = array(
            'post_title' => $title ?: '無題',
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id() ?: 1
        );
        
        return wp_insert_post($post_data);
    }
    
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['auto_save_session_id'])) {
            $_SESSION['auto_save_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['auto_save_session_id'];
    }
    
    public function get_draft_by_session($session_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY updated_at DESC LIMIT 1",
            $session_id
        ));
    }
    
    public function clean_old_drafts($days = 7) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    public function restore_draft_content($post_id_or_session) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'auto_save_drafts';
        
        if (is_numeric($post_id_or_session)) {
            $draft = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d ORDER BY updated_at DESC LIMIT 1",
                $post_id_or_session
            ));
        } else {
            $draft = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s ORDER BY updated_at DESC LIMIT 1",
                $post_id_or_session
            ));
        }
        
        return $draft;
    }
}

new AutoSaveHandler();

add_action('wp_ajax_get_draft_content', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'auto_save_nonce')) {
        wp_die('Security check failed');
    }
    
    $handler = new AutoSaveHandler();
    $session_id = $_POST['session_id'] ?? '';
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if ($post_id > 0) {
        $draft = $handler->restore_draft_content($post_id);
    } else {
        $draft = $handler->restore_draft_content($session_id);
    }
    
    if ($draft) {
        wp_send_json_success(array(
            'title' => $draft->title,
            'content' => $draft->content,
            'updated_at' => $draft->updated_at
        ));
    } else {
        wp_send_json_error('下書きが見つかりませんでした');
    }
});

register_activation_hook(__FILE__, function() {
    $handler = new AutoSaveHandler();
    $handler->create_drafts_table();
});

add_action('init', function() {
    if (wp_doing_cron()) {
        $handler = new AutoSaveHandler();
        $handler->clean_old_drafts(7);
    }
});
?>