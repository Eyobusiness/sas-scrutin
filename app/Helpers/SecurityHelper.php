<?php
/**
 * SecurityHelper - Fonctions de sécurisation, assainissement et téléversement sécurisé
 */

declare(strict_types=1);

class SecurityHelper
{
    /**
     * Échappe une chaîne pour affichage HTML sécurisé (contre les failles XSS)
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Nettoie et normalise un numéro de téléphone
     */
    public static function cleanPhone(string $phone): string
    {
        // Enlève espaces, tirets, points, parenthèses
        $cleaned = preg_replace('/[\s\-\.\(\)]+/', '', trim($phone));
        return $cleaned ?? '';
    }

    /**
     * Vérifie la validité basique d'un numéro de téléphone
     */
    public static function isValidPhone(string $phone): bool
    {
        $clean = self::cleanPhone($phone);
        // Au moins 8 chiffres, peut commencer par un +
        return (bool)preg_match('/^\+?[0-9]{8,18}$/', $clean);
    }

    /**
     * Téléversement sécurisé d'une photo de candidat
     *
     * @param array $file Élément de $_FILES
     * @param string $destinationDir Dossier cible
     * @return array [bool $success, ?string $filenameOrError]
     */
    public static function uploadPhoto(array $file, string $destinationDir): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return [false, "Paramètre de fichier invalide."];
        }

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return [true, null]; // Aucun fichier fourni (facultatif)
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [false, "Erreur lors du transfert du fichier (code: {$file['error']})."];
        }

        // Limite à 3 Mo
        $maxSize = 3 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return [false, "Le fichier dépasse la taille maximale autorisée (3 Mo)."];
        }

        // Vérification de l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return [false, "Format d'image non autorisé. Formats acceptés : JPG, JPEG, PNG, WEBP."];
        }

        // Vérification MIME réelle avec finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/x-png',
            'image/webp'
        ];

        if (!in_array($mime, $allowedMimes, true)) {
            return [false, "Le type réel du fichier ({$mime}) n'est pas une image valide."];
        }

        // Création du dossier si inexistant
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        // Nom de fichier aléatoire et sécurisé
        $safeName = 'candidat_' . bin2hex(random_bytes(10)) . '.' . $extension;
        $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [false, "Échec de l'enregistrement de l'image sur le serveur."];
        }

        return [true, $safeName];
    }

    /**
     * Détermine l'URL de base relative de l'application (ex: /sas-scrutin)
     */
    public static function getBaseUrl(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strpos($scriptName, '/views/');
        if ($pos !== false) {
            return rtrim(substr($scriptName, 0, $pos), '/');
        }
        $dir = dirname($scriptName);
        return ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : rtrim(str_replace('\\', '/', $dir), '/');
    }

    /**
     * Redirection HTTP sécurisée
     */
    public static function redirect(string $url): void
    {
        // Normaliser les chemins relatifs comme 'index.php?page=...'
        if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) {
            $base = self::getBaseUrl();
            $url = ($base !== '' ? $base . '/' : '') . ltrim($url, '/');
        }

        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        }

        echo "<script>window.location.href=" . json_encode($url) . ";</script><noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></noscript>";
        exit;
    }

    /**
     * Chiffre un ID numérique sous forme de chaîne opaque et sécurisée pour les URL
     */
    public static function encryptId(int|string|null $id): string
    {
        if ($id === null || $id === '') {
            return '';
        }

        $num = (int)$id;
        if ($num <= 0) {
            return '';
        }

        $secret = 'SAS_SCRUTIN_SECRET_SALT_V1_2026';
        $key = substr(hash('sha256', $secret, true), 0, 16);
        $iv = substr(hash('sha256', 'iv_' . $secret, true), 0, 16);

        $encrypted = openssl_encrypt((string)$num, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return (string)$num;
        }

        // HMAC 4 octets pour garantir l'intégrité et empêcher toute altération
        $hmac = substr(hash_hmac('sha256', $encrypted, $secret, true), 0, 4);
        return rtrim(strtr(base64_encode($encrypted . $hmac), '+/', '-_'), '=');
    }

    /**
     * Déchiffre une chaîne chiffrée pour restituer l'ID numérique d'origine
     */
    public static function decryptId(?string $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        // Si l'ID est déjà un entier pur (compatibilité interne / formulaires)
        if (ctype_digit((string)$hash)) {
            $val = (int)$hash;
            return $val > 0 ? $val : null;
        }

        $secret = 'SAS_SCRUTIN_SECRET_SALT_V1_2026';
        $key = substr(hash('sha256', $secret, true), 0, 16);
        $iv = substr(hash('sha256', 'iv_' . $secret, true), 0, 16);

        // Restaurer le base64 standard
        $b64 = strtr($hash, '-_', '+/');
        $mod = strlen($b64) % 4;
        if ($mod > 0) {
            $b64 .= str_repeat('=', 4 - $mod);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false || strlen($decoded) <= 4) {
            return null;
        }

        $ciphertext = substr($decoded, 0, -4);
        $hmac = substr($decoded, -4);
        $expectedHmac = substr(hash_hmac('sha256', $ciphertext, $secret, true), 0, 4);

        if (!hash_equals($expectedHmac, $hmac)) {
            return null;
        }

        $decrypted = openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false || !ctype_digit($decrypted)) {
            return null;
        }

        $id = (int)$decrypted;
        return $id > 0 ? $id : null;
    }
}

