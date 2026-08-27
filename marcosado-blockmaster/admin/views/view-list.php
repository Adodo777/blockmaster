<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
namespace Marcosado\BlockBuilder;

if (!defined('ABSPATH')) exit;

global $wpdb;

require_once __DIR__ . '/../class-block-list-table.php';

// Handle folder actions
if (isset($_POST['create_folder']) && check_admin_referer('bm_create_folder')) {
    $folder_name = isset($_POST['folder_name']) ? sanitize_text_field(wp_unslash($_POST['folder_name'])) : '';
    $folder_slug = sanitize_title($folder_name);
    if (!empty($folder_name)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert($wpdb->prefix . 'marcosado_block_folders', [
            'name' => $folder_name,
            'slug' => $folder_slug,
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="updated"><p>Dossier créé.</p></div>';
    }
}

// Handle folder delete
if (isset($_GET['delete_folder'])) {
    $del_id = (int)$_GET['delete_folder'];
    if (check_admin_referer('bm_delete_folder_' . $del_id)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete($wpdb->prefix . 'marcosado_block_folders', ['id' => $del_id]);
        // Update blocks in this folder to folder_id 0
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update($wpdb->prefix . 'marcosado_blocks', ['folder_id' => 0], ['folder_id' => $del_id]);
        echo '<div class="updated"><p>Dossier supprimé. Les blocs ont été déplacés vers "Tous les blocs".</p></div>';
    }
}

$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Initialize Table
$table = new Marcosado_Block_List_Table($current_folder_id);
$table->prepare_items();

// Fetch folders
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$folders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}marcosado_block_folders ORDER BY name ASC");

// Current folder label
$current_label = 'Tous les blocs';
if ($current_folder_id === 0) $current_label = 'Non classés';
foreach ($folders as $f) {
    if ($current_folder_id === (int)$f->id) { $current_label = $f->name; break; }
}
?>
<div class="wrap">
    <h1 style="display:none;"></h1>
    <div id="bm-notices-catcher"></div>
    <!-- Header -->
    <div class="tw-flex tw-flex-col sm:tw-flex-row tw-justify-between sm:tw-items-center tw-mb-0 tw-gap-3">
        <h1 class="tw-m-0 tw-text-[23px] tw-font-normal tw-text-[#1d2327] tw-leading-tight">
            BlockMaster
        </h1>
        <div class="tw-flex tw-gap-2 tw-items-center">
            <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster&view=edit')); ?>" class="button button-primary">
                + Nouveau Bloc
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster&bm_lock=1')); ?>" class="button" title="Verrouiller la session">
                <span class="dashicons dashicons-lock" style="vertical-align: middle; margin-top: -2px;"></span>
            </a>
        </div>
    </div>

    <?php Marcosado_Admin::render_tabs('list'); ?>

    <!-- Mobile: Folder filter as select -->
    <div class="md:tw-hidden tw-mb-4">
        <select class="tw-w-full tw-p-2 tw-rounded tw-text-sm tw-border tw-border-solid tw-border-[#8c8f94]" onchange="window.location.href=this.value;">
            <option value="<?php echo esc_url(admin_url('admin.php?page=blockmaster')); ?>" <?php selected($current_folder_id, null); ?>>📁 Tous les blocs</option>
            <option value="<?php echo esc_url(admin_url('admin.php?page=blockmaster&folder=0')); ?>" <?php selected($current_folder_id, 0); ?>>📂 Non classés</option>
            <?php foreach($folders as $f): ?>
                <option value="<?php echo esc_url(admin_url('admin.php?page=blockmaster&folder=' . $f->id)); ?>" <?php selected($current_folder_id, (int)$f->id); ?>>📂 <?php echo esc_html($f->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Main layout -->
    <div class="tw-flex tw-gap-5">
        <!-- Desktop Sidebar -->
        <div class="tw-hidden md:tw-block tw-w-[220px] tw-flex-none">
            <nav class="tw-mb-4">
                <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster')); ?>" 
                   class="tw-flex tw-items-center tw-gap-2 tw-px-3 tw-py-[6px] tw-rounded tw-text-[13px] tw-no-underline tw-mb-[2px] <?php echo $current_folder_id === null ? 'tw-bg-[#2271b1] tw-text-white' : 'tw-text-[#50575e] hover:tw-bg-[#f0f0f1]'; ?>">
                    <span class="dashicons dashicons-screenoptions" style="font-size:16px; width:16px; height:16px;"></span> Tous les blocs
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster&folder=0')); ?>" 
                   class="tw-flex tw-items-center tw-gap-2 tw-px-3 tw-py-[6px] tw-rounded tw-text-[13px] tw-no-underline tw-mb-[2px] <?php echo $current_folder_id === 0 ? 'tw-bg-[#2271b1] tw-text-white' : 'tw-text-[#50575e] hover:tw-bg-[#f0f0f1]'; ?>">
                    <span class="dashicons dashicons-portfolio" style="font-size:16px; width:16px; height:16px;"></span> Non classés
                </a>
            </nav>

            <?php if (!empty($folders)): ?>
            <div class="tw-mb-4">
                <p class="tw-text-[11px] tw-uppercase tw-text-[#646970] tw-font-semibold tw-tracking-wide tw-px-3 tw-mb-1 tw-mt-0">Dossiers</p>
                <?php foreach($folders as $f): ?>
                    <div class="tw-flex tw-items-center tw-group">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster&folder=' . $f->id)); ?>" 
                           class="tw-flex-1 tw-flex tw-items-center tw-gap-2 tw-px-3 tw-py-[6px] tw-rounded tw-text-[13px] tw-no-underline tw-mb-[2px] <?php echo $current_folder_id === (int)$f->id ? 'tw-bg-[#2271b1] tw-text-white' : 'tw-text-[#50575e] hover:tw-bg-[#f0f0f1]'; ?>">
                            <span class="dashicons dashicons-open-folder" style="font-size:16px; width:16px; height:16px;"></span>
                            <?php echo esc_html($f->name); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=blockmaster&delete_folder=' . $f->id), 'bm_delete_folder_' . $f->id)); ?>" 
                           onclick="return confirm('Supprimer ce dossier ? Les blocs seront conservés.');" 
                           class="tw-text-[#d63638] tw-no-underline tw-opacity-0 group-hover:tw-opacity-100 tw-transition-opacity tw-p-1" title="Supprimer">
                            <span class="dashicons dashicons-no-alt" style="font-size:16px; width:16px; height:16px;"></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" class="tw-px-1">
                <?php wp_nonce_field('bm_create_folder'); ?>
                <div class="tw-flex tw-gap-1">
                    <input type="text" name="folder_name" placeholder="Nouveau dossier…" required class="tw-flex-1 tw-text-xs tw-px-2 tw-py-1 tw-rounded tw-border tw-border-solid tw-border-[#8c8f94] tw-min-w-0">
                    <button type="submit" name="create_folder" class="button button-small">+</button>
                </div>
            </form>
        </div>

        <!-- Table Content -->
        <div class="tw-flex-1 tw-min-w-0">
            <form id="blocks-filter" method="get">
                <input type="hidden" name="page" value="blockmaster" />
                <?php if ($current_folder_id !== null): ?>
                    <input type="hidden" name="folder" value="<?php echo esc_attr($current_folder_id); ?>" />
                <?php endif; ?>
                <?php $table->display(); ?>
            </form>
        </div>
    </div>
</div>
