<?php
namespace Marcosado\BlockBuilder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Marcosado_Parser
{
    public static function sync_attributes_from_code(string $slug, string $code): bool
    {
        global $wpdb;

        if (!function_exists('token_get_all')) {
            return false;
        }

        // Si le code ne contient pas de balise PHP ouvrante, on l'ajoute pour que PHP tokenise correctement
        $code_to_tokenize = (strpos($code, '<?php') !== false || strpos($code, '<?') !== false)
            ? $code
            : "<?php\n" . $code;

        // Reconstruction statique déterministe (sans eval ni include)
        $tokens = token_get_all($code_to_tokenize);
        $attr_tokens = [];
        $in_attr = false;
        $bracket_level = 0;
        $has_array = false;

        foreach ($tokens as $token) {
            $is_array = is_array($token);
            $id = $is_array ? $token[0] : null;
            $text = $is_array ? $token[1] : $token;

            if (!$in_attr) {
                if ($id === T_VARIABLE && $text === '$bm_attributes') {
                    $in_attr = true;
                }
                continue;
            }

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT || $text === '=') {
                continue;
            }

            if ($text === '[' || $id === T_ARRAY || $text === '(') {
                $bracket_level++;
                $has_array = true;
            } elseif ($text === ']' || $text === ')') {
                $bracket_level--;
            } elseif ($text === ';') {
                if ($bracket_level === 0) break;
            }

            $attr_tokens[] = $token;

            if ($has_array && $bracket_level === 0) {
                break;
            }
        }

        if (empty($attr_tokens)) {
            return false;
        }

        // Parseur AST déterministe pour tableau littéral
        $bm_attributes = self::parse_literal_array($attr_tokens);

        if (!is_array($bm_attributes) || empty($bm_attributes)) {
            return false;
        }

        $extracted = $bm_attributes;

        $attr_table = $wpdb->prefix . 'marcosado_block_attributes';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($attr_table, ['block_slug' => $slug]);

        $sort_order = 10;
        foreach ($extracted as $key => $options) {
            $key = sanitize_key((string) $key);
            if (empty($key) || !is_array($options)) continue;

            $raw_sub = $options['sub_fields'] ?? $options['fields'] ?? null;
            if (is_array($raw_sub)) {
                $sub_fields = wp_json_encode($raw_sub);
            } elseif (is_string($raw_sub) && !empty($raw_sub)) {
                $decoded = json_decode($raw_sub, true);
                $sub_fields = is_array($decoded) ? $raw_sub : null;
            } else {
                $sub_fields = null;
            }

            $raw_default = $options['default'] ?? '';
            if (is_bool($raw_default)) {
                $field_default = $raw_default ? '1' : '0';
            } else {
                $field_default = sanitize_text_field((string) $raw_default);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($attr_table, [
                'block_slug'       => $slug,
                'field_key'        => $key,
                'field_label'      => sanitize_text_field($options['label'] ?? ucfirst($key)),
                'field_type'       => sanitize_text_field($options['type']  ?? 'text'),
                'field_default'    => $field_default,
                'field_section'    => sanitize_text_field($options['section'] ?? 'Général'),
                'field_sub_fields' => $sub_fields,
                'sort_order'       => $sort_order,
            ]);
            $sort_order += 10;
        }

        return true;
    }

    public static function inject_bm_attributes_from_db(string $slug, string $code): string
    {
        global $wpdb;

        if (strpos($code, '$bm_attributes') !== false) {
            return $code;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attrs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}marcosado_block_attributes WHERE block_slug = %s ORDER BY sort_order ASC",
            $slug
        ));

        if (empty($attrs)) {
            return $code;
        }

        $lines = [];
        foreach ($attrs as $attr) {
            $type    = addslashes($attr->field_type);
            $label   = addslashes($attr->field_label);
            $section = addslashes($attr->field_section);

            if ($attr->field_type === 'boolean') {
                $default_repr = ($attr->field_default === '1' || $attr->field_default === 'true') ? 'true' : 'false';
            } elseif ($attr->field_type === 'number' && is_numeric($attr->field_default)) {
                $default_repr = $attr->field_default;
            } else {
                $default_repr = "'" . addslashes($attr->field_default) . "'";
            }

            $sub_fields = '';
            if (!empty($attr->field_sub_fields)) {
                $decoded = json_decode($attr->field_sub_fields, true);
                if (is_array($decoded)) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
                    $sub_fields = ", 'sub_fields' => " . var_export($decoded, true);
                }
            }
            $lines[] = "    '{$attr->field_key}' => ['type' => '{$type}', 'label' => '{$label}', 'default' => {$default_repr}, 'section' => '{$section}'{$sub_fields}],";
        }

        $declaration = "<?php\n"
            . "/**\n"
            . " * Attributs du bloc — migrés automatiquement depuis la base de données.\n"
            . " * Modifiez ce tableau pour changer la configuration des champs.\n"
            . " */\n"
            . "\$bm_attributes = [\n"
            . implode("\n", $lines) . "\n"
            . "];\n"
            . "?>\n";

        return $declaration . $code;
    }

    public static function parse_literal_array(array $tokens, int &$index = 0)
    {
        return self::parse_array($tokens, $index);
    }

    private static function skip_ignorable(array $tokens, int &$index): void
    {
        while ($index < count($tokens)) {
            $token = $tokens[$index];
            $id = is_array($token) ? $token[0] : null;
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                $index++;
                continue;
            }
            break;
        }
    }

    private static function parse_array(array $tokens, int &$index = 0)
    {
        self::skip_ignorable($tokens, $index);

        if ($index >= count($tokens)) {
            return null;
        }

        $token = $tokens[$index];
        $is_arr = is_array($token);
        $id = $is_arr ? $token[0] : null;
        $text = $is_arr ? $token[1] : $token;

        if ($id === T_ARRAY) {
            $index++;
            self::skip_ignorable($tokens, $index);
            if ($index >= count($tokens)) return null;
            $open = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
            if ($open !== '(') return null;
            $index++;
            $close = ')';
        } elseif ($text === '[') {
            $index++;
            $close = ']';
        } elseif ($text === '(') {
            $index++;
            $close = ')';
        } else {
            return null;
        }

        $result = [];

        while ($index < count($tokens)) {
            self::skip_ignorable($tokens, $index);
            if ($index >= count($tokens)) break;

            $token = $tokens[$index];
            $is_arr = is_array($token);
            $id = $is_arr ? $token[0] : null;
            $text = $is_arr ? $token[1] : $token;

            if ($text === $close) {
                $index++;
                return $result;
            }

            if ($text === ',') {
                $index++;
                continue;
            }

            // Parse key or value
            $val = self::parse_value($tokens, $index);

            // Check if next token is '=>'
            self::skip_ignorable($tokens, $index);
            if ($index < count($tokens)) {
                $next_token = $tokens[$index];
                $next_id = is_array($next_token) ? $next_token[0] : null;
                if ($next_id === T_DOUBLE_ARROW) {
                    $index++; // consume '=>'
                    $key = (string) $val;

                    self::skip_ignorable($tokens, $index);
                    $item_value = self::parse_value($tokens, $index);
                    $result[$key] = $item_value;
                    continue;
                }
            }

            $result[] = $val;
        }

        return $result;
    }

    private static function parse_value(array $tokens, int &$index)
    {
        self::skip_ignorable($tokens, $index);
        if ($index >= count($tokens)) return null;

        $token = $tokens[$index];
        $is_arr = is_array($token);
        $id = $is_arr ? $token[0] : null;
        $text = $is_arr ? $token[1] : $token;

        // Array
        if ($text === '[' || $id === T_ARRAY || $text === '(') {
            return self::parse_array($tokens, $index);
        }

        // String
        if ($id === T_CONSTANT_ENCAPSED_STRING) {
            $index++;
            $first_char = $text[0];
            $inner = substr($text, 1, -1);
            if ($first_char === "'") {
                return str_replace(["\\'", "\\\\"], ["'", "\\"], $inner);
            } else {
                return stripcslashes($inner);
            }
        }

        // Signed number (-5, +10)
        if ($text === '-' || $text === '+') {
            $sign = $text === '-' ? -1 : 1;
            $index++;
            self::skip_ignorable($tokens, $index);
            if ($index < count($tokens)) {
                $n_token = $tokens[$index];
                $n_id = is_array($n_token) ? $n_token[0] : null;
                $n_text = is_array($n_token) ? $n_token[1] : $n_token;
                if ($n_id === T_LNUMBER || $n_id === T_DNUMBER) {
                    $index++;
                    return $sign * ($n_text + 0);
                }
            }
            return null;
        }

        // Number
        if ($id === T_LNUMBER || $id === T_DNUMBER) {
            $index++;
            return $text + 0;
        }

        // Bareword / Identifier / Keyword (true, false, null, json_encode)
        if ($id === T_STRING) {
            $index++;
            $lower = strtolower($text);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
            if ($lower === 'null') return null;

            // Support json_encode(...) wrapper
            if ($lower === 'json_encode') {
                self::skip_ignorable($tokens, $index);
                if ($index < count($tokens)) {
                    $paren = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
                    if ($paren === '(') {
                        $index++; // consume '('
                        $inner_val = self::parse_value($tokens, $index);
                        self::skip_ignorable($tokens, $index);
                        if ($index < count($tokens)) {
                            $close_paren = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
                            if ($close_paren === ')') {
                                $index++; // consume ')'
                            }
                        }
                        return $inner_val;
                    }
                }
            }

            return $text;
        }

        return null;
    }
}
