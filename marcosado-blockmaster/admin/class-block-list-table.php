<?php
namespace Marcosado\BlockBuilder;

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Marcosado_Block_List_Table extends \WP_List_Table {
    private $folder_id;
    private $block_errors;

    public function __construct($folder_id = null) {
        parent::__construct([
            'singular' => 'bloc',
            'plural'   => 'blocs',
            'ajax'     => false
        ]);
        $this->folder_id = $folder_id;
        $this->block_errors = get_option('marcosado_block_errors', []);
    }

    public function get_columns() {
        return [
            'name'        => 'Nom du Bloc',
            'folder'      => 'Dossier',
            'description' => 'Description'
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'   => ['name', false],
            'folder' => ['folder_name', false]
        ];
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'name'];

        // Query setup
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby(wp_unslash($_GET['orderby'])) : 'b.name';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = isset($_GET['order']) && strtolower(sanitize_text_field(wp_unslash($_GET['order']))) === 'desc' ? 'DESC' : 'ASC';

        $query = "SELECT SQL_CALC_FOUND_ROWS b.slug, b.name, b.description, f.name as folder_name FROM {$wpdb->prefix}marcosado_blocks b LEFT JOIN {$wpdb->prefix}marcosado_block_folders f ON b.folder_id = f.id";
        
        if ($this->folder_id !== null && $this->folder_id > 0) {
            $query .= $wpdb->prepare(" WHERE b.folder_id = %d", $this->folder_id);
        } elseif ($this->folder_id === 0) {
            $query .= " WHERE b.folder_id = 0";
        }

        $query .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this->items = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_items = (int) $wpdb->get_var("SELECT FOUND_ROWS()");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function column_default($item, $column_name) {
        return $item[$column_name];
    }

    public function column_name($item) {
        $edit_url = admin_url('admin.php?page=blockmaster&view=edit&slug=' . $item['slug']);
        $del_url = wp_nonce_url(admin_url('admin.php?page=blockmaster&delete=' . $item['slug']), 'bm_delete_' . $item['slug']);
        $error = $this->block_errors[$item['slug']] ?? null;

        $actions = [
            'edit'   => sprintf('<a href="%s">Éditer</a>', esc_url($edit_url)),
            'delete' => sprintf('<a href="%s" class="submitdelete" onclick="return confirm(\'Supprimer ce bloc ?\');" style="color: #d63638;">Supprimer</a>', esc_url($del_url))
        ];

        $name_output = '<strong><a href="' . esc_url($edit_url) . '" class="row-title">';
        if ($error) {
            $name_output .= '<span title="Erreur de sécurité : ' . esc_attr($error['error_type']) . '" style="cursor:help; color: #d63638;">⚠️ </span>';
        }
        $name_output .= esc_html($item['name']) . '</a></strong>';
        // $name_output .= '<br><span style="font-size: 11px; color: #a0a5aa;">' . esc_html($item['slug']) . '</span>';

        return $name_output . $this->row_actions($actions);
    }

    public function column_folder($item) {
        if ($item['folder_name']) {
            return '<span class="bm-badge">' . esc_html($item['folder_name']) . '</span>';
        }
        return '<span style="color: #a0a5aa;">—</span>';
    }

    public function column_description($item) {
        if (empty($item['description'])) {
            return '<span style="color:#aaa; font-style:italic;">Aucune description</span>';
        }
        return esc_html($item['description']);
    }
}
