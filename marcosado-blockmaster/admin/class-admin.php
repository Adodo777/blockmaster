<?php
namespace Marcosado\BlockBuilder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Marcosado_Admin
{
    private static string $sudo_error = '';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'create_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_init', [self::class, 'handle_sudo_actions']);
        
        // Retirer le footer WordPress sur l'administration (Blocks Lab)
        if (is_admin()) {
            add_filter('admin_footer_text', '__return_empty_string', 999);
            add_filter('update_footer', '__return_empty_string', 999);
        }

        // Masquer les notices tierces sur les pages BlockMaster
        add_action('in_admin_header', [self::class, 'suppress_admin_notices']);
    }

    /**
     * Supprime toutes les notices admin tierces sur les pages BlockMaster.
     */
    public static function suppress_admin_notices(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'blockmaster') === false) {
            return;
        }

        // Retirer tous les hooks de notices sauf les nôtres
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('user_admin_notices');
    }

    public static function handle_sudo_actions(): void
    {
        if (isset($_GET['page']) && $_GET['page'] === 'blockmaster') {
            // Handle Sudo Lock Action
            if (isset($_GET['bm_lock']) && $_GET['bm_lock'] == '1') {
                Marcosado_Sudo::lock_session();
                wp_safe_redirect(admin_url('admin.php?page=blockmaster'));
                exit;
            }

            // Handle Sudo Unlock Request
            if (isset($_POST['bm_sudo_password']) && check_admin_referer('bm_sudo_unlock')) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $password = wp_unslash($_POST['bm_sudo_password']);
                $result = Marcosado_Sudo::unlock_session($password);
                if (!$result['success']) {
                    self::$sudo_error = $result['message'];
                } else {
                    wp_safe_redirect(admin_url('admin.php?page=blockmaster'));
                    exit;
                }
            }
        }
    }

    public static function create_menu(): void
    {
        add_menu_page(
            'BlockMaster',
            'BlockMaster',
            'install_plugins',
            'blockmaster',
            [self::class, 'admin_page'],
            'dashicons-editor-code'
        );

        add_submenu_page(
            'blockmaster',
            'BlockMaster',
            'Blocks Lab',
            'install_plugins',
            'blockmaster',
            [self::class, 'admin_page']
        );
    }

    /**
     * Render navigation tabs for the main BlockMaster views.
     */
    public static function render_tabs(string $current_tab = 'list'): void
    {
        $tabs = [
            'list' => ['label' => 'Mes Blocs', 'url' => admin_url('admin.php?page=blockmaster')],
            'doc'  => ['label' => 'Documentation', 'url' => admin_url('admin.php?page=blockmaster&view=doc')],
        ];
        echo '<nav class="nav-tab-wrapper tw-mb-5">';
        foreach ($tabs as $key => $tab) {
            $active = ($key === $current_tab) ? ' nav-tab-active' : '';
            echo '<a href="' . esc_url($tab['url']) . '" class="nav-tab' . esc_attr($active) . '">' . esc_html($tab['label']) . '</a>';
        }
        echo '</nav>';
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if ($hook === 'toplevel_page_blockmaster') {
            $settings = wp_enqueue_code_editor(['type' => 'text/x-php']);

            wp_enqueue_script(
                'marcosado-block-builder-admin-js',
                MARCOSADO_PLUGIN_URL . 'assets/js/admin-lab.js',
                ['jquery'], '2.0', true
            );
            wp_localize_script('marcosado-block-builder-admin-js', 'marcosado_bb_settings', $settings);

            // Load Tailwind for admin UI
            Marcosado_Gutenberg::load_tailwind();

            wp_add_inline_style('common', '
                #wpfooter { display: none !important; }
                .CodeMirror { height: 600px; border-radius: 0 0 4px 4px; }
            ');
        }
    }

    private static function save_block(string $name, string $code, string $old_slug = '', int $folder_id = 0, string $description = ''): void
    {
        global $wpdb;
        // Si c'est une modification, on garde l'ancien slug de façon immuable. Sinon, on génère un nouveau slug depuis le nom.
        $slug        = !empty($old_slug) ? sanitize_title($old_slug) : sanitize_title($name);
        $table       = $wpdb->prefix . 'marcosado_blocks';
        $table_hist  = $wpdb->prefix . 'marcosado_blocks_history';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $current = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table} WHERE slug = %s", $slug));
        if ($current !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($table_hist, [
                'slug'     => $slug,
                'code'     => $current,
                'saved_at' => current_time('mysql'),
            ]);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_hist} WHERE slug = %s", $slug));
            if ($count > 5) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare("DELETE FROM {$table_hist} WHERE slug = %s ORDER BY saved_at ASC LIMIT %d", $slug, $count - 5));
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query($wpdb->prepare("INSERT INTO {$table} (name, slug, code, description, folder_id, updated_at) VALUES (%s, %s, %s, %s, %d, %s) ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), description = VALUES(description), folder_id = VALUES(folder_id), updated_at = VALUES(updated_at)", $name, $slug, $code, $description, $folder_id, current_time('mysql')));

        // --- ÉCRITURE DU FICHIER PHYSIQUE ---
        $blocks_dir = MARCOSADO_PLUGIN_DIR . 'blocks/';
        if (!file_exists($blocks_dir)) {
            wp_mkdir_p($blocks_dir);
        }
        $file_path = $blocks_dir . sanitize_key($slug) . '.php';
        $header = "<?php\n// phpcs:disable\nif ( ! defined( 'ABSPATH' ) ) exit;\n/**\n * Block Name: " . $name . "\n * Auto-generated by BlockMaster.\n */\n?>\n";
        $written = file_put_contents($file_path, $header . $code);
        
        if ($written !== false && function_exists('opcache_invalidate')) {
            opcache_invalidate($file_path, true);
        }
        // ------------------------------------

        // Analyse de sécurité
        $security = Marcosado_Security::analyze_code($code);
        $errors = get_option('marcosado_block_errors', []);
        if (!$security['valid']) {
            $errors[$slug] = $security;
        } else {
            unset($errors[$slug]);
        }
        update_option('marcosado_block_errors', $errors);

        // Vider le cache
        wp_cache_delete('bmcode_' . $slug, 'marcosado_blocks');

        Marcosado_Parser::sync_attributes_from_code($slug, $code);
    }

    private static function load_block(string $slug): string
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT code FROM {$wpdb->prefix}marcosado_blocks WHERE slug = %s", $slug
        ));
        return $row ? $row->code : '';
    }

    private static function load_block_name(string $slug): string
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}marcosado_blocks WHERE slug = %s", $slug
        ));
        return $row ? $row->name : ucwords(str_replace('-', ' ', $slug));
    }

    private static function delete_block(string $slug): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->prefix . 'marcosado_blocks_history', ['slug' => $slug]);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->prefix . 'marcosado_blocks', ['slug' => $slug]);

        $errors = get_option('marcosado_block_errors', []);
        if (isset($errors[$slug])) {
            unset($errors[$slug]);
            update_option('marcosado_block_errors', $errors);
        }

        $file_path = MARCOSADO_PLUGIN_DIR . 'blocks/' . sanitize_key($slug) . '.php';
        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }

        wp_cache_delete('bmcode_' . $slug, 'marcosado_blocks');
    }

    public static function admin_page(): void
    {
        global $wpdb;

        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            wp_die('L\'édition de code est désactivée sur ce serveur.');
        }

        // Sudo Check
        if (!Marcosado_Sudo::is_unlocked()) {
            ?>
            <div class="wrap">
                <h1>Blocks Lab - Mode Sécurisé</h1>
                <div style="max-width: 400px; margin: 50px auto; background: #fff; padding: 30px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
                    <span class="dashicons dashicons-lock" style="font-size: 50px; width: 50px; height: 50px; color: #2271b1; margin-bottom: 15px;"></span>
                    <h2>Zone Sensible</h2>
                    <p style="margin-bottom: 20px;">Pour protéger votre site, veuillez confirmer votre mot de passe WordPress (Sudo Mode).</p>
                    <?php if (self::$sudo_error): ?>
                        <div class="error" style="margin-bottom: 15px; text-align: left;"><p><?php echo esc_html(self::$sudo_error); ?></p></div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('bm_sudo_unlock'); ?>
                        <input type="password" name="bm_sudo_password" placeholder="Mot de passe actuel" required style="width: 100%; padding: 10px; margin-bottom: 15px; font-size: 16px;">
                        <button type="submit" class="button button-primary button-large" style="width: 100%;">Déverrouiller</button>
                    </form>
                </div>
            </div>
            <?php
            return;
        }

        if (isset($_GET['delete'])) {
            $delete_slug = sanitize_title(wp_unslash($_GET['delete']));
            if (check_admin_referer('bm_delete_' . $delete_slug)) {
                self::delete_block($delete_slug);
                echo '<div class="updated"><p>Bloc supprimé.</p></div>';
            }
        }

        if (isset($_POST['save_block']) && check_admin_referer('bm_save_block')) {
            $name = isset($_POST['block_name']) ? sanitize_text_field(wp_unslash($_POST['block_name'])) : '';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_code = isset($_POST['block_code']) ? wp_unslash($_POST['block_code']) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
            if (isset($_POST['folder_id']) && $_POST['folder_id'] === 'new' && !empty($_POST['new_folder_name'])) {
                $new_folder_name = sanitize_text_field(wp_unslash($_POST['new_folder_name']));
                $new_folder_slug = sanitize_title($new_folder_name);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->insert($wpdb->prefix . 'marcosado_block_folders', [
                    'name' => $new_folder_name,
                    'slug' => $new_folder_slug,
                    'created_at' => current_time('mysql')
                ]);
                $folder_id = $wpdb->insert_id;
            } else {
                $folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
            }

            // Mitigation: Strict capability check - explicit denial for unauthorized execution payloads
            if ( ! current_user_can('install_plugins') ) {
                wp_die( esc_html__( 'Vous n\'êtes pas autorisé à enregistrer du code PHP.', 'marcosado-blockmaster' ) );
            }

            // Valid payload from authorized user - sanitize encoding
            $code = wp_check_invalid_utf8( $raw_code );
            $old_slug = isset($_POST['old_slug']) ? sanitize_title(wp_unslash($_POST['old_slug'])) : '';
            
            // SAST Analysis: Pre-save validation to prevent storing dangerous payloads
            $security = Marcosado_Security::analyze_code($code);
            if (!$security['valid'] && $security['severity'] === 'critical') {
                $error_msg = isset($security['error_type']) ? $security['error_type'] : 'Erreur de sécurité critique';
                if (isset($security['line'])) {
                    $error_msg .= ' (Ligne ' . $security['line'] . ')';
                }
                echo '<div class="error"><p><strong>Sauvegarde refusée (Critical) :</strong> ' . esc_html($error_msg) . '. Veuillez corriger le code.</p></div>';
                
                // Preserve user input to prevent data loss
                $_POST['preserve_edit_name'] = $name;
                $_POST['preserve_edit_code'] = $code;
                $_POST['preserve_edit_desc'] = $description;
                $_POST['preserve_folder_id'] = $folder_id;
            } else {
                self::save_block($name, $code, $old_slug, $folder_id, $description);
                $msg = 'Bloc "' . esc_html($name) . '" enregistré avec succès !';
                if ($security['severity'] === 'warning') {
                    $msg .= ' <strong>Attention (Warning) :</strong> ' . esc_html($security['error_type']);
                }
                echo '<div class="updated"><p>' . wp_kses($msg, ['strong' => []]) . '</p></div>';
            }
        }

        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'list';

        if ($view === 'edit') {
            require_once __DIR__ . '/views/view-edit.php';
        } elseif ($view === 'doc') {
            require_once __DIR__ . '/views/view-doc.php';
        } else {
            require_once __DIR__ . '/views/view-list.php';
        }
    }
}
