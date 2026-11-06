<?php
/**
 * Service de gestion centralisée des paramètres de l'application et des conditions d'éligibilité
 */

declare(strict_types=1);

require_once __DIR__ . '/../Models/Parametre.php';

class ParametreService
{
    private Parametre $parametreModel;

    public function __construct(?PDO $pdo = null)
    {
        $this->parametreModel = new Parametre($pdo);
    }

    /**
     * Récupère tous les paramètres bruts (clé => valeur)
     */
    public function getAll(): array
    {
        return $this->parametreModel->getAll();
    }

    /**
     * Récupère les conditions configurées pour l'accès à la liste électorale (Membres / Électeurs)
     */
    public function getElectorConditions(): array
    {
        $all = $this->parametreModel->getAll();

        $ageActif = ($all['age_min_actif'] ?? '1') === '1';
        $ageMin = isset($all['age_min_valeur']) ? max(0, (int)$all['age_min_valeur']) : 18;

        $ancActif = ($all['anciennete_min_actif'] ?? '1') === '1';
        $ancMin = isset($all['anciennete_min_valeur']) ? max(0, (int)$all['anciennete_min_valeur']) : 2;

        return [
            'age_min_actif'         => $ageActif,
            'age_min_valeur'        => $ageMin,
            'anciennete_min_actif'  => $ancActif,
            'anciennete_min_valeur' => $ancMin,
        ];
    }

    /**
     * Récupère les conditions configurées pour le dépôt de candidature
     * Si les conditions communes sont activées, réutilise les règles d'âge et d'ancienneté
     * de la liste électorale pour éviter toute contradiction.
     */
    public function getCandidatureConditions(): array
    {
        $all = $this->parametreModel->getAll();
        $elector = $this->getElectorConditions();

        $communes = ($all['candidature_conditions_communes'] ?? '1') === '1';
        $paiementRequis = ($all['candidature_paiement_requis'] ?? '1') === '1';

        if ($communes) {
            return [
                'conditions_communes'   => true,
                'age_min_actif'         => $elector['age_min_actif'],
                'age_min_valeur'        => $elector['age_min_valeur'],
                'anciennete_min_actif'  => $elector['anciennete_min_actif'],
                'anciennete_min_valeur' => $elector['anciennete_min_valeur'],
                'paiement_requis'       => $paiementRequis,
            ];
        }

        $candAgeActif = ($all['candidature_age_min_actif'] ?? '1') === '1';
        $candAgeMin = isset($all['candidature_age_min_valeur']) ? max(0, (int)$all['candidature_age_min_valeur']) : $elector['age_min_valeur'];

        $candAncActif = ($all['candidature_anciennete_min_actif'] ?? '1') === '1';
        $candAncMin = isset($all['candidature_anciennete_min_valeur']) ? max(0, (int)$all['candidature_anciennete_min_valeur']) : $elector['anciennete_min_valeur'];

        return [
            'conditions_communes'   => false,
            'age_min_actif'         => $candAgeActif,
            'age_min_valeur'        => $candAgeMin,
            'anciennete_min_actif'  => $candAncActif,
            'anciennete_min_valeur' => $candAncMin,
            'paiement_requis'       => $paiementRequis,
        ];
    }

    /**
     * Enregistre les paramètres généraux de l'application
     */
    public function saveGeneralSettings(array $data): array
    {
        $appName = trim((string)($data['app_name'] ?? 'SAS Élection'));
        $appSubtitle = trim((string)($data['app_subtitle'] ?? 'Tous unis au service du Christ'));
        $contactEmail = trim((string)($data['contact_email'] ?? ''));
        $contactPhone = trim((string)($data['contact_phone'] ?? ''));

        if ($appName === '') {
            return [false, "Le nom de l'application est obligatoire."];
        }

        $this->parametreModel->set('app_name', $appName, "Nom de l'application");
        $this->parametreModel->set('app_subtitle', $appSubtitle, "Devise du groupe");
        $this->parametreModel->set('contact_email', $contactEmail, "Email de contact du bureau");
        $this->parametreModel->set('contact_phone', $contactPhone, "Téléphone d'assistance du bureau");

        return [true, "Les paramètres généraux de l'application ont été enregistrés."];
    }

    /**
     * Valide et enregistre les conditions d'éligibilité et de candidature
     */
    public function saveEligibilitySettings(array $data): array
    {
        // 1. Conditions d'accès à la liste électorale
        $ageMinActif = !empty($data['age_min_actif']) ? '1' : '0';
        $rawAgeMin = $data['age_min_valeur'] ?? '18';

        if (!is_numeric($rawAgeMin) || (int)$rawAgeMin < 0 || (int)$rawAgeMin > 120) {
            return [false, "L'âge minimum autorisé doit être un nombre entier valide compris entre 0 et 120 ans."];
        }
        $ageMinValeur = (int)$rawAgeMin;

        $ancMinActif = !empty($data['anciennete_min_actif']) ? '1' : '0';
        $rawAncMin = $data['anciennete_min_valeur'] ?? '2';

        if (!is_numeric($rawAncMin) || (int)$rawAncMin < 0 || (int)$rawAncMin > 100) {
            return [false, "L'ancienneté minimum requise doit être un nombre entier valide compris entre 0 et 100 ans."];
        }
        $ancMinValeur = (int)$rawAncMin;

        // 2. Conditions de candidature
        $conditionsCommunes = !empty($data['candidature_conditions_communes']) ? '1' : '0';
        $paiementRequis = !empty($data['candidature_paiement_requis']) ? '1' : '0';

        if ($conditionsCommunes === '1') {
            $candAgeActif = $ageMinActif;
            $candAgeValeur = $ageMinValeur;
            $candAncActif = $ancMinActif;
            $candAncValeur = $ancMinValeur;
        } else {
            $candAgeActif = !empty($data['candidature_age_min_actif']) ? '1' : '0';
            $rawCandAge = $data['candidature_age_min_valeur'] ?? (string)$ageMinValeur;
            if (!is_numeric($rawCandAge) || (int)$rawCandAge < 0 || (int)$rawCandAge > 120) {
                return [false, "L'âge minimum de candidature doit être un nombre entier valide compris entre 0 et 120 ans."];
            }
            $candAgeValeur = (int)$rawCandAge;

            $candAncActif = !empty($data['candidature_anciennete_min_actif']) ? '1' : '0';
            $rawCandAnc = $data['candidature_anciennete_min_valeur'] ?? (string)$ancMinValeur;
            if (!is_numeric($rawCandAnc) || (int)$rawCandAnc < 0 || (int)$rawCandAnc > 100) {
                return [false, "L'ancienneté minimum de candidature doit être un nombre entier valide compris entre 0 et 100 ans."];
            }
            $candAncValeur = (int)$rawCandAnc;
        }

        // Sauvegarde en base de données
        $this->parametreModel->set('age_min_actif', $ageMinActif, "Activer la condition d'âge minimum pour les électeurs (1=Oui, 0=Non)");
        $this->parametreModel->set('age_min_valeur', (string)$ageMinValeur, "Âge minimum requis pour être électeur (en années)");
        $this->parametreModel->set('anciennete_min_actif', $ancMinActif, "Activer la condition d'ancienneté minimum pour les électeurs (1=Oui, 0=Non)");
        $this->parametreModel->set('anciennete_min_valeur', (string)$ancMinValeur, "Ancienneté minimum requise pour être électeur (en années)");

        $this->parametreModel->set('candidature_conditions_communes', $conditionsCommunes, "Utiliser les mêmes conditions d'âge et d'ancienneté pour les candidats (1=Oui, 0=Non)");
        $this->parametreModel->set('candidature_age_min_actif', $candAgeActif, "Activer la condition d'âge minimum pour les candidats (1=Oui, 0=Non)");
        $this->parametreModel->set('candidature_age_min_valeur', (string)$candAgeValeur, "Âge minimum autorisé pour déposer une candidature (en années)");
        $this->parametreModel->set('candidature_anciennete_min_actif', $candAncActif, "Activer la condition d'ancienneté minimum pour les candidats (1=Oui, 0=Non)");
        $this->parametreModel->set('candidature_anciennete_min_valeur', (string)$candAncValeur, "Ancienneté minimum requise pour déposer une candidature (en années)");
        $this->parametreModel->set('candidature_paiement_requis', $paiementRequis, "Exiger le paiement du droit de candidature (1=Oui, 0=Non)");

        return [true, "Les conditions d'éligibilité et de candidature ont été enregistrées avec succès."];
    }
}

