<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
namespace Marcosado\BlockBuilder;

if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 style="display:none;"></h1>
    <div id="bm-notices-catcher"></div>
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

    <?php Marcosado_Admin::render_tabs('doc'); ?>

    <div class="tw-max-w-[900px]">
        <!-- Single Card -->
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-8">
            
            <div class="tw-flex tw-justify-between tw-items-center tw-mb-8">
                <h1 class="tw-m-0 tw-text-2xl tw-font-bold tw-text-[#1d2327]">Documentation</h1>
                <button type="button" id="bm-download-doc" class="button button-secondary tw-flex tw-items-center tw-gap-1">
                    <span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    Télécharger (.md)
                </button>
            </div>

            <!-- SECTION: Attributs Dynamiques -->
            <div class="tw-mb-10">
                <h2 class="tw-m-0 tw-mb-4 tw-text-xl tw-font-semibold tw-text-[#1d2327] tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-pb-2">Attributs Dynamiques <code class="tw-text-sm tw-bg-[#f0f0f1] tw-px-2 tw-py-[2px] tw-rounded tw-font-normal">$bm_attributes</code></h2>
                <p class="tw-text-[14px] tw-text-[#646970] tw-mt-0 tw-mb-4">Déclarez le tableau <code>$bm_attributes</code> dans le premier bloc <code>&lt;?php ?&gt;</code> de votre code pour créer des champs éditables dans Gutenberg.</p>
                
                <pre class="tw-bg-[#1d2327] tw-text-[#e4e6e8] tw-p-4 tw-rounded-lg tw-text-[12px] tw-leading-relaxed tw-m-0 tw-overflow-x-auto tw-whitespace-pre tw-break-normal tw-mb-5">&lt;?php
$bm_attributes = [
    'titre' => ['type' => 'text', 'label' => 'Titre', 'default' => 'Mon titre', 'section' => 'Général'],
    'sous_titre' => ['type' => 'textarea', 'label' => 'Sous-titre', 'default' => ''],
    'nombre' => ['type' => 'number', 'label' => 'Nombre', 'default' => 3],
    'actif' => ['type' => 'boolean', 'label' => 'Activer ?', 'default' => true],
    'couleur_fond' => ['type' => 'color', 'label' => 'Couleur de fond', 'default' => '#ffffff'],
    'photo' => ['type' => 'image', 'label' => 'Photo', 'default' => ''],
    'style' => ['type' => 'select', 'label' => 'Style', 'default' => 'moderne:Moderne,classique:Classique,minimal:Minimal'],
    'items' => ['type' => 'repeater', 'label' => 'Éléments', 'sub_fields' => '{"nom":{"type":"text","default":""},"photo":{"type":"image","default":""}}'],
];
?&gt;</pre>

                <h3 class="tw-text-[15px] tw-font-semibold tw-text-[#1d2327] tw-mb-3 tw-mt-6">Types supportés</h3>
                <div class="tw-overflow-x-auto">
                    <table class="widefat striped tw-text-[13px] tw-mb-0">
                        <thead>
                            <tr>
                                <th class="tw-w-[120px]">Type</th>
                                <th>Description</th>
                                <th class="tw-w-[200px]">Default</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>text</code></td><td>Champ texte simple</td><td>Chaîne de caractères</td></tr>
                            <tr><td><code>textarea</code></td><td>Zone de texte multi-lignes</td><td>Chaîne de caractères</td></tr>
                            <tr><td><code>number</code></td><td>Champ numérique</td><td>Nombre</td></tr>
                            <tr><td><code>boolean</code></td><td>Case à cocher (true/false)</td><td><code>true</code> ou <code>false</code></td></tr>
                            <tr><td><code>color</code></td><td>Sélecteur de couleur</td><td>Code hex (<code>#ffffff</code>)</td></tr>
                            <tr><td><code>image</code></td><td>Sélecteur d'image (Media Library)</td><td>URL ou vide</td></tr>
                            <tr><td><code>select</code></td><td>Menu déroulant</td><td><code>val:Label,val2:Label2</code></td></tr>
                            <tr><td><code>repeater</code></td><td>Champs répétables</td><td>JSON encodé dans <code>sub_fields</code></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION: Utilisation des variables -->
            <div class="tw-mb-10">
                <h2 class="tw-m-0 tw-mb-4 tw-text-xl tw-font-semibold tw-text-[#1d2327] tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-pb-2">Utilisation dans le HTML</h2>
                <p class="tw-text-[14px] tw-text-[#646970] tw-mt-0 tw-mb-4">Les attributs déclarés sont automatiquement disponibles comme variables PHP portant le nom de la clé :</p>
                
                <pre class="tw-bg-[#1d2327] tw-text-[#e4e6e8] tw-p-4 tw-rounded-lg tw-text-[12px] tw-leading-relaxed tw-m-0 tw-overflow-x-auto tw-whitespace-pre tw-break-normal">&lt;div class="tw-bg-slate-900 tw-p-8 tw-rounded-xl"&gt;
    &lt;h2 class="tw-text-2xl tw-font-bold tw-text-white"&gt;
        &lt;?php echo esc_html($titre); ?&gt;
    &lt;/h2&gt;
    &lt;p class="tw-text-slate-300"&gt;
        &lt;?php echo esc_html($sous_titre); ?&gt;
    &lt;/p&gt;
    &lt;?php if ($photo): ?&gt;
        &lt;img src="&lt;?php echo esc_url($photo); ?&gt;" class="tw-rounded-lg tw-mt-4" /&gt;
    &lt;?php endif; ?&gt;
&lt;/div&gt;</pre>
            </div>

            <!-- SECTION: Tailwind & Icônes -->
            <div class="tw-mb-10">
                <h2 class="tw-m-0 tw-mb-4 tw-text-xl tw-font-semibold tw-text-[#1d2327] tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-pb-2">Tailwind CSS & Lucide Icons</h2>
                
                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-8">
                    <div class="tw-min-w-0">
                        <h3 class="tw-text-[15px] tw-font-semibold tw-text-[#1d2327] tw-mb-2 tw-mt-0">Tailwind CSS</h3>
                        <p class="tw-text-[13px] tw-text-[#646970] tw-mt-0 tw-mb-2">Toutes les classes utilisent le préfixe <code>tw-</code> pour éviter les conflits :</p>
                        <pre class="tw-bg-[#1d2327] tw-text-[#e4e6e8] tw-p-4 tw-rounded-lg tw-text-[11px] tw-leading-relaxed tw-m-0 tw-overflow-x-auto tw-whitespace-pre tw-break-normal">&lt;!-- ✅ Correct --&gt;
&lt;div class="tw-bg-blue-500 tw-text-white tw-p-4"&gt;

&lt;!-- ❌ Incorrect --&gt;
&lt;div class="bg-blue-500 text-white p-4"&gt;</pre>
                        <p class="tw-text-[13px] tw-text-[#646970] tw-mt-3 tw-mb-2">⚠️ Pour les couleurs dynamiques (attributs <code>color</code>), utilisez le CSS inline :</p>
                        <pre class="tw-bg-[#1d2327] tw-text-[#e4e6e8] tw-p-4 tw-rounded-lg tw-text-[11px] tw-leading-relaxed tw-m-0 tw-overflow-x-auto tw-whitespace-pre tw-break-normal">&lt;div style="background-color: &lt;?php echo esc_attr($couleur_fond); ?&gt;;"&gt;</pre>
                    </div>
                    <div class="tw-min-w-0">
                        <h3 class="tw-text-[15px] tw-font-semibold tw-text-[#1d2327] tw-mb-2 tw-mt-0">Lucide Icons</h3>
                        <p class="tw-text-[13px] tw-text-[#646970] tw-mt-0 tw-mb-2">Utilisez la balise <code>&lt;i data-lucide&gt;</code> pour inclure des icônes :</p>
                        <pre class="tw-bg-[#1d2327] tw-text-[#e4e6e8] tw-p-4 tw-rounded-lg tw-text-[11px] tw-leading-relaxed tw-m-0 tw-overflow-x-auto tw-whitespace-pre tw-break-normal">&lt;i data-lucide="star" class="tw-w-5 tw-h-5"&gt;&lt;/i&gt;
&lt;i data-lucide="heart" class="tw-w-6 tw-h-6 tw-text-red-500"&gt;&lt;/i&gt;
&lt;i data-lucide="arrow-right" class="tw-w-4 tw-h-4"&gt;&lt;/i&gt;</pre>
                        <p class="tw-text-[13px] tw-text-[#646970] tw-mt-3 tw-mb-0">📖 Catalogue complet : <a href="https://lucide.dev/icons/" target="_blank" class="tw-text-[#2271b1]">lucide.dev/icons</a></p>
                    </div>
                </div>
            </div>

            <!-- SECTION: Bonnes Pratiques -->
            <div>
                <h2 class="tw-m-0 tw-mb-4 tw-text-xl tw-font-semibold tw-text-[#1d2327] tw-border-b tw-border-solid tw-border-[#f0f0f1] tw-pb-2">Bonnes Pratiques</h2>
                <ul class="tw-text-[14px] tw-text-[#3c434a] tw-space-y-2 tw-m-0 tw-pl-5">
                    <li>Utilisez <code>esc_html()</code> pour le texte et <code>esc_url()</code> pour les URLs</li>
                    <li>Utilisez <code>esc_attr()</code> pour les attributs HTML dynamiques</li>
                    <li>Le paramètre <code>section</code> permet de regrouper les champs dans l'inspecteur Gutenberg</li>
                    <li>N'écrivez pas de commentaire d'en-tête <code>Block Name</code>, il est généré automatiquement</li>
                    <li>La description du bloc (dans la page d'édition) aide l'IA à générer de meilleurs prompts</li>
                </ul>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('bm-download-doc');
    if (!btn) return;
    
    btn.addEventListener('click', function() {
        var mdContent = `# Documentation BlockMaster

## Attributs Dynamiques
Déclarez le tableau \`$bm_attributes\` dans le premier bloc \`<?php ?>\` de votre code pour créer des champs éditables dans Gutenberg.

\`\`\`php
<?php
$bm_attributes = [
    'titre' => ['type' => 'text', 'label' => 'Titre', 'default' => 'Mon titre', 'section' => 'Général'],
    'sous_titre' => ['type' => 'textarea', 'label' => 'Sous-titre', 'default' => ''],
    'nombre' => ['type' => 'number', 'label' => 'Nombre', 'default' => 3],
    'actif' => ['type' => 'boolean', 'label' => 'Activer ?', 'default' => true],
    'couleur_fond' => ['type' => 'color', 'label' => 'Couleur de fond', 'default' => '#ffffff'],
    'photo' => ['type' => 'image', 'label' => 'Photo', 'default' => ''],
    'style' => ['type' => 'select', 'label' => 'Style', 'default' => 'moderne:Moderne,classique:Classique,minimal:Minimal'],
    'items' => ['type' => 'repeater', 'label' => 'Éléments', 'sub_fields' => '{"nom":{"type":"text","default":""},"photo":{"type":"image","default":""}}'],
];
?>
\`\`\`

### Types supportés
- **text** : Champ texte simple
- **textarea** : Zone de texte multi-lignes
- **number** : Champ numérique
- **boolean** : Case à cocher (true/false)
- **color** : Sélecteur de couleur (ex: #ffffff)
- **image** : Sélecteur d'image (renvoie une URL)
- **select** : Menu déroulant (ex: \`val1:Label1,val2:Label2\`)
- **repeater** : Champs répétables (fournir \`sub_fields\`)

## Utilisation dans le HTML
Les variables déclarées dans les attributs sont automatiquement utilisables directement par leur nom de clé (ex: \`$cle_attribut\`).

\`\`\`php
<div class="tw-bg-slate-900 tw-p-8 tw-rounded-xl">
    <h2 class="tw-text-2xl tw-font-bold tw-text-white">
        <?php echo esc_html($titre); ?>
    </h2>
    <p class="tw-text-slate-300">
        <?php echo esc_html($sous_titre); ?>
    </p>
    <?php if ($photo): ?>
        <img src="<?php echo esc_url($photo); ?>" class="tw-rounded-lg tw-mt-4" />
    <?php endif; ?>
</div>
\`\`\`

## Tailwind CSS & Lucide Icons

**Tailwind CSS**
Toutes les classes utilisent le préfixe \`tw-\` pour éviter les conflits :
\`\`\`html
<!-- ✅ Correct -->
<div class="tw-bg-blue-500 tw-text-white tw-p-4">
\`\`\`
Pour les couleurs dynamiques (attributs \`color\`), utilisez le CSS inline :
\`\`\`html
<div style="background-color: <?php echo esc_attr($couleur_fond); ?>;">
\`\`\`

**Lucide Icons**
Utilisez la balise \`<i data-lucide>\` pour inclure des icônes (catalogue : https://lucide.dev/icons/) :
\`\`\`html
<i data-lucide="star" class="tw-w-5 tw-h-5"></i>
<i data-lucide="heart" class="tw-w-6 tw-h-6 tw-text-red-500"></i>
\`\`\`

## Bonnes Pratiques
- Utilisez \`esc_html()\` pour le texte et \`esc_url()\` pour les URLs
- Utilisez \`esc_attr()\` pour les attributs HTML dynamiques
- Le paramètre \`section\` permet de regrouper les champs dans l'inspecteur Gutenberg
- N'écrivez pas de commentaire d'en-tête \`Block Name\`, il est généré automatiquement
`;
        
        var blob = new Blob([mdContent], { type: 'text/markdown;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'BlockMaster-Documentation.md';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
