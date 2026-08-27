<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
namespace Marcosado\BlockBuilder;

if (!defined('ABSPATH')) exit;

global $wpdb;

$edit_slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
$edit_code = '';
$edit_name = '';
$edit_desc = '';
$edit_folder = 0;
$is_new = empty($edit_slug);

if ($edit_slug) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}marcosado_blocks WHERE slug = %s", $edit_slug));
    if ($row) {
        $edit_code = $row->code;
        $edit_name = $row->name;
        $edit_desc = $row->description;
        $edit_folder = (int)$row->folder_id;
    }

    if (isset($_GET['restore'])) {
        $history_id = (int) $_GET['restore'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $hist_row = $wpdb->get_row($wpdb->prepare("SELECT code FROM {$wpdb->prefix}marcosado_blocks_history WHERE id = %d AND slug = %s", $history_id, $edit_slug));
        if ($hist_row) {
            $edit_code = $hist_row->code;
        }
    }
}

// Preserve unsaved code if validation fails
if (isset($_POST['preserve_edit_code'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $edit_code = wp_unslash($_POST['preserve_edit_code']);
    $edit_name = isset($_POST['preserve_edit_name']) ? sanitize_text_field(wp_unslash($_POST['preserve_edit_name'])) : '';
    $edit_desc = isset($_POST['preserve_edit_desc']) ? sanitize_textarea_field(wp_unslash($_POST['preserve_edit_desc'])) : '';
    $edit_folder = isset($_POST['preserve_folder_id']) ? (int)$_POST['preserve_folder_id'] : 0;
}

// History
$history = [];
if ($edit_slug) {
    $edit_code = Marcosado_Parser::inject_bm_attributes_from_db($edit_slug, $edit_code);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $history = $wpdb->get_results($wpdb->prepare("SELECT id, saved_at, code FROM {$wpdb->prefix}marcosado_blocks_history WHERE slug = %s ORDER BY saved_at DESC LIMIT 5", $edit_slug));
}

// Folders
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$folders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}marcosado_block_folders ORDER BY name ASC");
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
    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=blockmaster&view=edit' . ($edit_slug ? '&slug='.$edit_slug : ''))); ?>">
        <?php wp_nonce_field('bm_save_block'); ?>
        <input type="hidden" name="old_slug" value="<?php echo esc_attr($edit_slug); ?>">

        <!-- Single card -->
        <div class="tw-bg-white tw-max-w-[900px] tw-rounded-lg tw-shadow-sm tw-overflow-hidden">

            <!-- Top bar: Back + Name + Save -->
            <div class="tw-flex tw-items-center tw-gap-3 tw-px-5 tw-py-3 tw-bg-white tw-border-b tw-border-solid tw-border-[#f0f0f1]">
                <a href="<?php echo esc_url(admin_url('admin.php?page=blockmaster')); ?>" class="tw-text-[#50575e] hover:tw-text-[#2271b1] tw-no-underline tw-text-lg tw-flex-none" title="Retour">&larr;</a>
                <input type="text" name="block_name" value="<?php echo esc_attr($edit_name); ?>" required placeholder="Nom du bloc…" class="tw-flex-1 tw-text-[15px] tw-font-medium tw-border-0 tw-outline-none tw-bg-transparent tw-text-[#1d2327] tw-py-1 tw-px-2 tw-rounded hover:tw-bg-[#f6f7f7] focus:tw-bg-[#f6f7f7] tw-transition-colors tw-min-w-0">
                <button type="submit" name="save_block" class="button button-primary tw-flex-none">Enregistrer</button>
            </div>

            <!-- Code editor header -->
            <div class="tw-flex tw-justify-between tw-items-center tw-px-5 tw-py-2 tw-bg-[#1d2327]">
                <span class="tw-text-[#8c8f94] tw-text-xs tw-font-mono">PHP / HTML / Tailwind</span>
                <button type="button" id="bm-ai-btn" class="tw-bg-transparent tw-border tw-border-solid tw-border-[#50575e] tw-text-[#c3c4c7] tw-rounded tw-px-3 tw-py-1 tw-text-xs tw-cursor-pointer hover:tw-border-white hover:tw-text-white tw-transition-colors tw-flex tw-items-center tw-gap-1">
                    <span class="dashicons dashicons-superhero" style="font-size:14px; width:14px; height:14px;"></span>
                    <?php echo $is_new ? 'Créer avec IA' : 'Modifier avec IA'; ?>
                </button>
            </div>

            <!-- CodeMirror -->
            <textarea name="block_code" id="block-code-editor" class="tw-hidden"><?php echo esc_textarea($edit_code); ?></textarea>

            <!-- Accordions -->
            <div class="tw-border-t tw-border-solid tw-border-[#f0f0f1]">
                <!-- Accordion: Dossier & Description -->
                <div class="bm-accordion">
                    <button type="button" class="bm-accordion-toggle tw-w-full tw-flex tw-items-center tw-justify-between tw-px-5 tw-py-3 tw-bg-white tw-border-0 tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-cursor-pointer tw-text-[13px] tw-font-semibold tw-text-[#1d2327] hover:tw-bg-[#f6f7f7] tw-transition-colors">
                        <span>Dossier & Description</span>
                        <span class="dashicons dashicons-arrow-down-alt2 tw-text-[#8c8f94] bm-accordion-icon" style="font-size:16px; width:16px; height:16px; transition: transform .2s;"></span>
                    </button>
                    <div class="bm-accordion-content tw-hidden tw-px-5 tw-py-4 tw-bg-[#f6f7f7] tw-border-b tw-border-solid tw-border-[#f0f0f1]">
                        <div class="tw-flex tw-flex-col sm:tw-flex-row tw-gap-4 tw-mb-4">
                            <div class="tw-flex-1">
                                <label class="tw-block tw-text-[13px] tw-font-semibold tw-text-[#1d2327] tw-mb-1">Dossier</label>
                                <select name="folder_id" id="bm-folder-select" class="widefat">
                                    <option value="0">— Non classé —</option>
                                    <?php foreach($folders as $f): ?>
                                        <option value="<?php echo esc_attr($f->id); ?>" <?php selected($edit_folder, $f->id); ?>><?php echo esc_html($f->name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="new">+ Créer un dossier…</option>
                                </select>
                                <input type="text" name="new_folder_name" id="bm-new-folder-input" placeholder="Nom du dossier…" class="widefat tw-hidden tw-mt-2">
                            </div>
                        </div>
                        <div>
                            <label class="tw-block tw-text-[13px] tw-font-semibold tw-text-[#1d2327] tw-mb-1">Description <span class="tw-text-[#646970] tw-font-normal">(optionnel)</span></label>
                            <textarea name="description" rows="3" class="widefat" placeholder="Description du bloc, notes internes, contexte pour l'IA…"><?php echo esc_textarea($edit_desc); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Accordion: Historique -->
                <?php if (!$is_new): ?>
                <div class="bm-accordion">
                    <button type="button" class="bm-accordion-toggle tw-w-full tw-flex tw-items-center tw-justify-between tw-px-5 tw-py-3 tw-bg-white tw-border-0 tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-cursor-pointer tw-text-[13px] tw-font-semibold tw-text-[#1d2327] hover:tw-bg-[#f6f7f7] tw-transition-colors">
                        <span>Historique <span class="tw-text-[#8c8f94] tw-font-normal">(<?php echo count($history); ?> version<?php echo count($history) > 1 ? 's' : ''; ?>)</span></span>
                        <span class="dashicons dashicons-arrow-down-alt2 tw-text-[#8c8f94] bm-accordion-icon" style="font-size:16px; width:16px; height:16px; transition: transform .2s;"></span>
                    </button>
                    <div class="bm-accordion-content tw-hidden tw-px-5 tw-py-3 tw-bg-[#f6f7f7]">
                        <?php if (empty($history)): ?>
                            <p class="tw-text-[#646970] tw-text-[13px] tw-m-0">Aucune version précédente.</p>
                        <?php else: ?>
                            <?php foreach($history as $i => $v): 
                                $restore_url = admin_url('admin.php?page=blockmaster&view=edit&slug=' . $edit_slug . '&restore=' . $v->id);
                            ?>
                                <div class="tw-flex tw-justify-between tw-items-center tw-py-2 <?php echo $i < count($history) - 1 ? 'tw-border-b tw-border-solid tw-border-[#e0e0e0]' : ''; ?>">
                                    <div>
                                        <span class="tw-text-[13px] tw-font-medium tw-text-[#2271b1]">v<?php echo count($history) - $i; ?></span>
                                        <span class="tw-text-[11px] tw-text-[#646970] tw-ml-1"><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($v->saved_at))); ?></span>
                                    </div>
                                    <a href="<?php echo esc_url($restore_url); ?>" class="button button-small" onclick="return confirm('Restaurer cette version ?');">Restaurer</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- AI Modal -->
<div id="bm-ai-modal" class="tw-fixed tw-inset-0 tw-z-[100000] tw-hidden">
    <div class="tw-absolute tw-inset-0 tw-bg-black/50" id="bm-ai-modal-overlay"></div>
    <div class="tw-absolute tw-inset-0 tw-flex tw-items-center tw-justify-center tw-p-4">
        <div class="tw-bg-white tw-rounded-xl tw-shadow-2xl tw-w-full tw-max-w-[600px] tw-max-h-[80vh] tw-overflow-auto">
            <div class="tw-flex tw-justify-between tw-items-center tw-px-6 tw-py-4 tw-border-b tw-border-solid tw-border-[#f0f0f1]">
                <h2 class="tw-m-0 tw-text-base tw-font-semibold tw-text-[#1d2327]" id="bm-ai-modal-title">
                    <?php echo $is_new ? '🤖 Créer un bloc avec l\'IA' : '🤖 Modifier avec l\'IA'; ?>
                </h2>
                <button type="button" id="bm-ai-modal-close" class="tw-bg-transparent tw-border-0 tw-cursor-pointer tw-text-[#646970] hover:tw-text-[#d63638] tw-text-xl tw-p-1">&times;</button>
            </div>
            <div class="tw-p-6">
                <label class="tw-block tw-text-[13px] tw-font-semibold tw-text-[#1d2327] tw-mb-2">
                    <?php echo $is_new ? 'Décrivez le bloc que vous voulez créer :' : 'Décrivez la modification souhaitée :'; ?>
                </label>
                <textarea id="bm-ai-user-input" rows="4" class="widefat tw-mb-4" placeholder="<?php echo $is_new ? 'Ex: Une hero section avec une image de fond, un titre, un sous-titre et un bouton CTA…' : 'Ex: Ajoute un attribut pour changer la couleur du fond, rendre le titre éditable…'; ?>"></textarea>
                <div class="tw-flex tw-justify-end tw-gap-2">
                    <button type="button" id="bm-ai-copy-prompt" class="button button-primary tw-flex tw-items-center tw-gap-1">
                        📋 Copier le Prompt
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
