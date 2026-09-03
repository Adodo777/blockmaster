=== BlockMaster ===
Contributors: marcosado
Tags: gutenberg, elementor, blocks, custom-blocks, tailwind
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage custom Gutenberg blocks using PHP, Tailwind CSS, and dynamic attributes, with Elementor support.

== Description ==

BlockMaster is a powerful WordPress plugin designed for developers. It allows you to create custom Gutenberg blocks autonomously using only PHP, Tailwind CSS, and dynamic attributes (sidebar options). Write your logic in PHP and see the results instantly in the editor.

== Installation ==

1. Download the plugin and upload it to your `/wp-content/plugins/` folder.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to "BlockMaster" to create your first block.

== Changelog ==

= 1.0.1 =
* Fix: Correction du parseur AST déterministe pour l'enregistrement des attributs $bm_attributes.
* Fix: Prise en charge des valeurs booléennes (true/false) et null dans les attributs.
* Fix: Support complet des repeaters avec sub_fields déclarés sous forme de tableau ou de chaîne JSON.
* Fix: Tolérance pour json_encode(...) et les nombres négatifs.
* Amélioration: Mise à jour du prompt système IA.

= 1.0.0 =
* Initial public release.
