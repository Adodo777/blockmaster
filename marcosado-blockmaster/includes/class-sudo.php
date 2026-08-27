<?php
namespace Marcosado\BlockBuilder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Marcosado_Sudo {

    private static string $cookie_name = 'bm_sudo_token';
    private static int $timeout        = 3600; // 1 heure

    /**
     * Vérifie si l'utilisateur actuel a une session Sudo valide.
     */
    public static function is_unlocked(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id       = get_current_user_id();
        $saved_token   = get_transient( 'bm_sudo_' . $user_id );
        $cookie_token  = isset( $_COOKIE[ self::$cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::$cookie_name ] ) ) : '';

        if ( empty( $saved_token ) || empty( $cookie_token ) ) {
            return false;
        }

        return hash_equals( $saved_token, $cookie_token );
    }

    /**
     * Déverrouille la session en vérifiant le mot de passe WP de l'utilisateur actuel.
     * Protection Brute Force (5 échecs = 15 min de blocage).
     */
    public static function unlock_session( string $password ): array {
        if ( ! is_user_logged_in() ) {
            return [ 'success' => false, 'message' => __( 'Vous devez être connecté.', 'marcosado-blockmaster' ) ];
        }

        $user        = wp_get_current_user();
        $user_id     = $user->ID;
        $attempt_key = 'bm_sudo_attempts_' . $user_id;
        $attempts    = (int) get_transient( $attempt_key );

        if ( $attempts >= 5 ) {
            return [ 'success' => false, 'message' => __( 'Trop de tentatives échouées. Réessayez dans 15 minutes.', 'marcosado-blockmaster' ) ];
        }

        // Vérification sécurisée du mot de passe
        $auth = wp_authenticate( $user->user_login, $password );

        if ( is_wp_error( $auth ) ) {
            set_transient( $attempt_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
            $remaining = 5 - ( $attempts + 1 );
            return [
                'success' => false,
                /* translators: %d: number of remaining attempts */
                'message' => sprintf( __( 'Mot de passe incorrect. Tentatives restantes : %d', 'marcosado-blockmaster' ), max( 0, $remaining ) ),
            ];
        }

        // Succès : réinitialisation des tentatives
        delete_transient( $attempt_key );

        // Token sécurisé (64 chars)
        $token = wp_generate_password( 64, false );

        // Sauvegarde Transient
        set_transient( 'bm_sudo_' . $user_id, $token, self::$timeout );

        // Définition du Cookie sur COOKIEPATH
        $cookie_path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
        $domain      = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

        if ( ! headers_sent() ) {
            setcookie(
                self::$cookie_name,
                $token,
                time() + self::$timeout,
                $cookie_path,
                $domain,
                is_ssl(),
                true // HttpOnly
            );
        }

        // Synchronise la variable superglobale pour la requête courante
        $_COOKIE[ self::$cookie_name ] = $token;

        return [ 'success' => true ];
    }

    /**
     * Verrouille la session manuellement.
     */
    public static function lock_session(): void {
        if ( is_user_logged_in() ) {
            delete_transient( 'bm_sudo_' . get_current_user_id() );
        }

        $cookie_path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
        $domain      = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

        if ( ! headers_sent() ) {
            setcookie(
                self::$cookie_name,
                '',
                time() - 3600,
                $cookie_path,
                $domain,
                is_ssl(),
                true
            );
        }

        unset( $_COOKIE[ self::$cookie_name ] );
    }
}
