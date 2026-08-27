<?php
namespace Marcosado\BlockBuilder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Marcosado_DB
{
    public static function init(): void
    {
        add_action('plugins_loaded', [self::class, 'maybe_setup_tables']);
    }

    public static function activate(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marcosado_block_folders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marcosado_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            code LONGTEXT NOT NULL,
            description TEXT NULL,
            folder_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY folder_id (folder_id)
        ) $charset");
        // phpcs:enable

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marcosado_blocks_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            code LONGTEXT NOT NULL,
            saved_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY slug_idx (slug)
        ) $charset");
        // phpcs:enable

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}marcosado_block_attributes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            block_slug VARCHAR(191) NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_label VARCHAR(255) NOT NULL DEFAULT '',
            field_type VARCHAR(50) NOT NULL DEFAULT 'text',
            field_default TEXT NOT NULL,
            field_section VARCHAR(255) NOT NULL DEFAULT 'Général',
            field_sub_fields LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY block_slug (block_slug)
        ) $charset");
        // phpcs:enable

        // phpcs:enable

        // Rétrocompatibilité : Ajout des colonnes si la table existait déjà
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}marcosado_blocks");
        $has_folder = false;
        $has_description = false;
        foreach ($columns as $col) {
            if ($col->Field === 'folder_id') $has_folder = true;
            if ($col->Field === 'description') $has_description = true;
        }

        if (!$has_folder) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}marcosado_blocks ADD COLUMN folder_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER code, ADD INDEX folder_id (folder_id)");
        }
        if (!$has_description) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}marcosado_blocks ADD COLUMN description TEXT NULL AFTER code");
        }

        self::regenerate_all_blocks();
    }

    /**
     * Regénère les fichiers physiques des blocs existants lors de l'activation
     */
    private static function regenerate_all_blocks(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $blocks = $wpdb->get_results("SELECT name, slug, code FROM {$wpdb->prefix}marcosado_blocks");
        
        if (!empty($blocks)) {
            $blocks_dir = MARCOSADO_PLUGIN_DIR . 'blocks/';
            if (!file_exists($blocks_dir)) {
                wp_mkdir_p($blocks_dir);
            }
            foreach ($blocks as $block) {
                $file_path = $blocks_dir . sanitize_key($block->slug) . '.php';
                $header = "<?php\n// phpcs:disable\nif ( ! defined( 'ABSPATH' ) ) exit;\n/**\n * Block Name: " . $block->name . "\n * Auto-generated by BlockMaster.\n */\n?>\n";
                file_put_contents($file_path, $header . $block->code);
            }
        }
    }

    public static function maybe_setup_tables(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}marcosado_blocks'");
        if (!$exists) {
            self::activate();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $bm_attr_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}marcosado_block_attributes'");
        if (!$bm_attr_exists) {
            self::activate();
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $col_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}marcosado_block_attributes LIKE 'field_section'");
            if (empty($col_exists)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$wpdb->prefix}marcosado_block_attributes ADD COLUMN field_section VARCHAR(255) NOT NULL DEFAULT 'Général' AFTER field_default");
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $col_sub = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}marcosado_block_attributes LIKE 'field_sub_fields'");
            if (empty($col_sub)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$wpdb->prefix}marcosado_block_attributes ADD COLUMN field_sub_fields LONGTEXT NULL AFTER field_section");
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $folders_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}marcosado_block_folders'");
        if (!$folders_exist) {
            self::activate();
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $block_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}marcosado_blocks LIKE 'folder_id'");
            if (empty($block_cols)) {
                self::activate();
            }
        }
    }

    public static function get_attrs_map(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $all = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}marcosado_block_attributes ORDER BY sort_order ASC"
        );
        $cache = [];
        foreach ($all as $attr) {
            $cache[$attr->block_slug][] = $attr;
        }
        return $cache;
    }
}
