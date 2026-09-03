jQuery(document).ready(function($) {
    var $textarea = $('#block-code-editor');

    if ($textarea.length && typeof marcosado_bb_settings !== 'undefined') {
        var settings = $.extend(true, {}, marcosado_bb_settings);
        if (settings.codemirror) {
            settings.codemirror.lineWrapping = true;
        }

        var editor = wp.codeEditor.initialize($textarea, settings);

        // Sync CodeMirror to textarea before form submit
        $(document).on('submit', 'form', function() {
            if (editor && editor.codemirror) {
                editor.codemirror.save();
            }
        });

        // Store editor reference globally for AI prompt
        window.bmCodeMirror = editor.codemirror;
    }
});

document.addEventListener('DOMContentLoaded', function() {

    // ─── Accordions ──────────────────────────────────────────────────
    document.querySelectorAll('.bm-accordion-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var content = this.nextElementSibling;
            var icon = this.querySelector('.bm-accordion-icon');
            if (content.classList.contains('tw-hidden')) {
                content.classList.remove('tw-hidden');
                if (icon) icon.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('tw-hidden');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        });
    });

    // ─── Folder select: create new folder inline ─────────────────────
    var folderSelect = document.getElementById('bm-folder-select');
    var newFolderInput = document.getElementById('bm-new-folder-input');
    if (folderSelect && newFolderInput) {
        folderSelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newFolderInput.classList.remove('tw-hidden');
                newFolderInput.required = true;
                newFolderInput.focus();
            } else {
                newFolderInput.classList.add('tw-hidden');
                newFolderInput.required = false;
            }
        });
    }

    // ─── AI Modal ────────────────────────────────────────────────────
    var aiBtn = document.getElementById('bm-ai-btn');
    var aiModal = document.getElementById('bm-ai-modal');
    var aiModalClose = document.getElementById('bm-ai-modal-close');
    var aiModalOverlay = document.getElementById('bm-ai-modal-overlay');
    var aiCopyBtn = document.getElementById('bm-ai-copy-prompt');
    var aiUserInput = document.getElementById('bm-ai-user-input');

    if (!aiBtn || !aiModal) return;

    function openModal() { aiModal.classList.remove('tw-hidden'); }
    function closeModal() { aiModal.classList.add('tw-hidden'); }

    aiBtn.addEventListener('click', openModal);
    if (aiModalClose) aiModalClose.addEventListener('click', closeModal);
    if (aiModalOverlay) aiModalOverlay.addEventListener('click', closeModal);

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !aiModal.classList.contains('tw-hidden')) {
            closeModal();
        }
    });

    // ─── AI Prompt System ────────────────────────────────────────────
    var systemPrompt = 'Tu es un assistant IA expert en développement WordPress et tu aides l\'utilisateur à créer ou modifier des blocs pour le plugin "BlockMaster".\n\n' +
        '### CONTEXTE DU PLUGIN BLOCKMASTER\n' +
        'BlockMaster permet de créer des blocs Gutenberg natifs directement en PHP/HTML. Le code généré est lu par le plugin qui se charge de créer le bloc.\n' +
        'Tu dois fournir UNIQUEMENT le code complet (PHP/HTML) du bloc, sans markdown autour si possible, ou alors dans un seul bloc de code.\n\n' +
        '### RÈGLES DE DÉCLARATION DES ATTRIBUTS\n' +
        'Si le bloc nécessite des champs dynamiques (éditables dans l\'inspecteur Gutenberg), tu DOIS déclarer la variable `$bm_attributes` au tout début du fichier, dans une balise PHP.\n' +
        'Voici la liste exacte des types d\'attributs supportés et leur configuration :\n' +
        '- `text` : champ texte court.\n' +
        '- `textarea` : champ texte long multi-lignes.\n' +
        '- `number` : champ numérique.\n' +
        '- `boolean` : case à cocher (true/false).\n' +
        '- `color` : sélecteur de couleur (renvoie un code hexadécimal, ex: #ffffff).\n' +
        '- `image` : sélecteur d\'image (renvoie l\'URL de l\'image).\n' +
        '- `select` : menu déroulant. Le `default` doit suivre le format `valeur1:Label 1,valeur2:Label 2`.\n' +
        '- `repeater` : liste d\'éléments répétables. Tu DOIS fournir la clé `sub_fields` avec un tableau contenant la définition des sous-champs.\n\n' +
        'Exemple de déclaration complète :\n' +
        '```php\n' +
        '<?php\n' +
        '$bm_attributes = [\n' +
        '    \'titre\' => [\'type\' => \'text\', \'label\' => \'Titre principal\', \'default\' => \'Mon Super Titre\', \'section\' => \'Contenu\'],\n' +
        '    \'afficher_bouton\' => [\'type\' => \'boolean\', \'label\' => \'Afficher le bouton\', \'default\' => true, \'section\' => \'Contenu\'],\n' +
        '    \'couleur_fond\' => [\'type\' => \'color\', \'label\' => \'Couleur de fond\', \'default\' => \'#f0f0f0\', \'section\' => \'Style\'],\n' +
        '    \'alignement\' => [\'type\' => \'select\', \'label\' => \'Alignement\', \'default\' => \'left:Gauche,center:Centré,right:Droite\', \'section\' => \'Style\'],\n' +
        '    \'elements\' => [\n' +
        '        \'type\' => \'repeater\',\n' +
        '        \'label\' => \'Liste d\\\'éléments\',\n' +
        '        \'sub_fields\' => [\n' +
        '            \'nom\' => [\'type\' => \'text\', \'label\' => \'Nom\', \'default\' => \'\'],\n' +
        '            \'photo\' => [\'type\' => \'image\', \'label\' => \'Photo\', \'default\' => \'\']\n' +
        '        ]\n' +
        '    ]\n' +
        '];\n' +
        '?>\n' +
        '```\n\n' +
        '### RÈGLES DE DÉVELOPPEMENT ET D\'INTÉGRATION\n' +
        '1. **Variables automatiques** : Les clés définies dans `$bm_attributes` deviennent instantanément des variables PHP utilisables dans le HTML (ex: `$titre`, `$couleur_fond`).\n' +
        '2. **Sécurité** : Utilise TOUJOURS `esc_html()` pour le texte, `esc_url()` pour les liens/images, et `esc_attr()` pour les attributs HTML.\n' +
        '3. **Tailwind CSS** : Le projet utilise Tailwind CSS. **OBLIGATOIRE** : toutes les classes Tailwind DOIVENT être préfixées par `tw-` (ex: `tw-flex`, `tw-bg-blue-500`, `tw-text-white`). N\'utilise aucune classe non préfixée.\n' +
        '4. **Couleurs dynamiques** : Tailwind avec le préfixe `tw-` ne compile pas toujours les valeurs arbitraires dynamiques. Pour les attributs de type `color`, utilise TOUJOURS le style inline : `style="background-color: <?php echo esc_attr($couleur_fond); ?>;"`.\n' +
        '5. **Icônes Lucide** : Pour ajouter des icônes, utilise UNIQUEMENT les Lucide Icons via la balise `<i data-lucide="nom-icone" class="tw-w-5 tw-h-5"></i>`.\n' +
        '6. **Commentaire d\'en-tête** : Ne génère AUCUN commentaire d\'en-tête (style `/* Block Name: ... */`), BlockMaster s\'en occupe en arrière-plan.\n\n';

    if (aiCopyBtn) {
        aiCopyBtn.addEventListener('click', function() {
            var userMessage = aiUserInput ? aiUserInput.value.trim() : '';
            if (!userMessage) {
                aiUserInput.focus();
                return;
            }

            var prompt = systemPrompt + '\n';

            // Get current code from CodeMirror
            var currentCode = '';
            if (window.bmCodeMirror) {
                currentCode = window.bmCodeMirror.getValue();
            }

            // Get description and name
            var descField = document.querySelector('textarea[name="description"]');
            var description = descField ? descField.value.trim() : '';
            var nameField = document.querySelector('input[name="block_name"]');
            var blockName = nameField ? nameField.value.trim() : '';

            if (currentCode) {
                // Modification mode
                prompt += '───────────────────────────────\n';
                if (blockName) {
                    prompt += 'NOM DU BLOC : ' + blockName + '\n';
                }
                if (description) {
                    prompt += 'DESCRIPTION DU BLOC : ' + description + '\n';
                }
                prompt += 'CODE ACTUEL DU BLOC :\n';
                prompt += '```php\n' + currentCode + '\n```\n\n';
                prompt += 'MODIFICATION DEMANDÉE :\n' + userMessage + '\n\n';
                prompt += 'Renvoie le code complet modifié.\n';
            } else {
                // Creation mode
                prompt += '───────────────────────────────\n';
                prompt += 'BLOC À CRÉER :\n' + userMessage + '\n\n';
                if (blockName) {
                    prompt += 'NOM DU BLOC : ' + blockName + '\n';
                }
                if (description) {
                    prompt += 'CONTEXTE ADDITIONNEL : ' + description + '\n\n';
                }
                prompt += 'Génère le code complet du bloc.\n';
            }

            navigator.clipboard.writeText(prompt).then(function() {
                var oldText = aiCopyBtn.innerHTML;
                aiCopyBtn.innerHTML = '✅ Copié !';
                setTimeout(function() {
                    aiCopyBtn.innerHTML = oldText;
                    closeModal();
                }, 1500);
            }).catch(function() {
                // Fallback: select text in textarea
                aiUserInput.value = prompt;
                aiUserInput.select();
                alert('Copie automatique impossible. Le prompt a été placé dans le champ, sélectionnez-le manuellement (Ctrl+C).');
            });
        });
    }
});
