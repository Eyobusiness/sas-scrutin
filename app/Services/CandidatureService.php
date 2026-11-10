<?php
/**
 * Service de vérification et traitement des conditions de candidature
 */

declare(strict_types=1);

require_once __DIR__ . '/ParametreService.php';
require_once __DIR__ . '/EligibilityService.php';
require_once __DIR__ . '/ElectionService.php';
require_once __DIR__ . '/../Models/Candidat.php';

class CandidatureService
{
    private ParametreService $parametreService;
    private EligibilityService $eligibilityService;
    private ElectionService $electionService;
    private Candidat $candidatModel;
    private ?array $candidatureConditionsCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->parametreService = new ParametreService($pdo);
        $this->eligibilityService = new EligibilityService($pdo, $this->parametreService);
        $this->electionService = new ElectionService($pdo);
        $this->candidatModel = new Candidat($pdo);
    }

    public function getEligibilityService(): EligibilityService
    {
        return $this->eligibilityService;
    }

    public function getParametreService(): ParametreService
    {
        return $this->parametreService;
    }

    public function getElectionService(): ElectionService
    {
        return $this->electionService;
    }

    /**
     * Vérifie si le dépôt d'une candidature est autorisé sur le scrutin de référence (ou l'élection fournie)
     * et qu'aucun doublon n'existe déjà pour ce candidat sur cette élection.
     */
    public function checkCanSubmitForElection(?array $election, string $nom, string $prenoms, ?string $telephone = null, ?DateTimeInterface $ref = null): array
    {
        $periodCheck = $this->electionService->canSubmitCandidature($election, $ref);
        if (!$periodCheck['allowed']) {
            return $periodCheck;
        }

        $targetElection = $election ?? $this->electionService->getDerniereElection($ref);
        if (!$targetElection) {
            return [
                'allowed' => false,
                'reason'  => 'no_election',
                'message' => ElectionService::MSG_AUCUNE_ELECTION_CANDIDATURE
            ];
        }

        if ($this->candidatModel->hasExistingCandidature((int)$targetElection['id'], $nom, $prenoms, $telephone)) {
            return [
                'allowed' => false,
                'reason'  => 'duplicate',
                'message' => 'Une candidature a déjà été déposée avec ce numéro de téléphone ou cette identité pour cette élection.'
            ];
        }

        return [
            'allowed'  => true,
            'reason'   => 'open',
            'election' => $targetElection,
            'message'  => 'Dépôt de candidature autorisé.'
        ];
    }

    /**
     * Normalise la valeur du paiement du droit de candidature (OUI/1/true -> true, NON/0/false -> false)
     */
    public function normalizeDroitPaye(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        $str = strtolower(trim((string)($value ?? '')));
        return in_array($str, ['1', 'oui', 'true', 'on', 'yes'], true);
    }

    /**
     * Évalue la conformité d'un dossier de candidature par rapport aux conditions configurées :
     * 1. Âge minimum configuré
     * 2. Ancienneté minimum configurée (saisie manuellement)
     * 3. Paiement du droit de candidature
     *
     * Règle impérative : Même si le dossier est conforme, le statut initial reste TOUJOURS 'en_attente'
     * pour validation définitive par le bureau.
     */
    public function evaluateCandidature(?string $dateNaissance, ?int $anciennete, bool $droitPaye): array
    {
        $conditions = $this->candidatureConditionsCache
            ??= $this->parametreService->getCandidatureConditions();
        $age = $this->eligibilityService->calculateAge($dateNaissance);

        $motifs = [];
        $details = [];

        // 1. Vérification de l'âge minimum
        if ($conditions['age_min_actif']) {
            $ageMin = $conditions['age_min_valeur'];
            if ($age === null) {
                $motifs[] = "Votre âge est inférieur au minimum autorisé (date de naissance non renseignée, {$ageMin} ans requis).";
                $details['age'] = false;
            } elseif ($age < $ageMin) {
                $unitAge = ($age <= 1) ? 'an' : 'ans';
                $unitMin = ($ageMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Votre âge est inférieur au minimum autorisé ({$age} {$unitAge} alors que le minimum requis est de {$ageMin} {$unitMin}).";
                $details['age'] = false;
            } else {
                $details['age'] = true;
            }
        } else {
            $details['age'] = true;
        }

        // 2. Vérification de l'ancienneté minimum (saisie manuellement)
        if ($conditions['anciennete_min_actif']) {
            $ancMin = $conditions['anciennete_min_valeur'];
            if ($anciennete === null) {
                $unitMin = ($ancMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Votre ancienneté déclarée est insuffisante (non renseignée, {$ancMin} {$unitMin} requis).";
                $details['anciennete'] = false;
            } elseif ($anciennete < $ancMin) {
                $unitAnc = ($anciennete <= 1) ? 'an' : 'ans';
                $unitMin = ($ancMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Votre ancienneté déclarée est insuffisante ({$anciennete} {$unitAnc} alors que le minimum requis est de {$ancMin} {$unitMin}).";
                $details['anciennete'] = false;
            } else {
                $details['anciennete'] = true;
            }
        } else {
            $details['anciennete'] = true;
        }

        // 3. Vérification du paiement du droit de candidature
        if ($conditions['paiement_requis']) {
            if (!$droitPaye) {
                $motifs[] = "Le droit de candidature n'a pas encore été payé.";
                $details['paiement'] = false;
            } else {
                $details['paiement'] = true;
            }
        } else {
            $details['paiement'] = true;
        }

        $conforme = empty($motifs);

        return [
            'conforme'       => $conforme,
            'statut_initial' => 'en_attente', // Toujours en attente de la décision du bureau
            'age'            => $age,
            'anciennete'     => $anciennete,
            'droit_paye'     => $droitPaye,
            'motifs'         => $motifs,
            'details'        => $details,
            'conditions'     => $conditions,
        ];
    }

    /**
     * Enrichit un enregistrement candidat provenant de la base de données avec son âge calculé
     * et l'état de conformité de son dossier pour affichage dans l'administration.
     */
    public function enrichCandidat(array $candidat): array
    {
        $dateNaissance = !empty($candidat['date_naissance']) ? (string)$candidat['date_naissance'] : null;
        $anciennete = ($candidat['anciennete'] !== null && $candidat['anciennete'] !== '') ? (int)$candidat['anciennete'] : null;
        $droitPaye = !empty($candidat['droit_candidature_paye']);

        $eval = $this->evaluateCandidature($dateNaissance, $anciennete, $droitPaye);

        $candidat['age_calcule'] = $eval['age'];
        $candidat['dossier_conforme'] = $eval['conforme'];
        $candidat['motifs_non_conformite'] = $eval['motifs'];
        $candidat['details_conformite'] = $eval['details'];

        return $candidat;
    }
}
