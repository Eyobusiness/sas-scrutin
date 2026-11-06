<?php
/**
 * SessionHelper - Gestion sécurisée des sessions, messages flash et CSRF
 */

declare(strict_types=1);

class SessionHelper
{
    /**
     * Démarre la session PHP avec des paramètres de sécurité stricts
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            @session_start();
        }
    }

    /**
     * Régénère l'identifiant de session (à appeler lors du login)
     */
    public static function regenerate(): void
    {
        self::start();
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Génère ou récupère le jeton CSRF de la session
     */
    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Champ HTML caché contenant le jeton CSRF
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Vérifie la validité d'un jeton CSRF
     */
    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Définit un message flash
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        if (!isset($_SESSION['flashes'])) {
            $_SESSION['flashes'] = [];
        }
        $_SESSION['flashes'][$type][] = $message;
    }

    /**
     * Récupère et vide les messages flash
     */
    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);
        return $flashes;
    }

    /**
     * Affiche les alertes HTML des messages flash
     */
    public static function renderFlashes(): string
    {
        $flashes = self::getFlashes();
        if (empty($flashes)) {
            return '';
        }

        $html = '';
        foreach ($flashes as $type => $messages) {
            $alertClass = match ($type) {
                'success' => 'alert-success',
                'error', 'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info'
            };

            $iconClass = match ($type) {
                'success' => 'bi-check-circle-fill',
                'error', 'danger' => 'bi-exclamation-triangle-fill',
                'warning' => 'bi-exclamation-circle-fill',
                default => 'bi-info-circle-fill'
            };

            foreach ($messages as $msg) {
                $html .= sprintf(
                    '<div class="alert %s alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi %s me-2 fs-5"></i>
                        <div>%s</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                    </div>',
                    htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                );
            }
        }

        return $html;
    }
}
