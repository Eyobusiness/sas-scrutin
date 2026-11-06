<?php
/**
 * Service de vérification des conditions d'éligibilité (âge calculé et ancienneté déclarée)
 */

declare(strict_types=1);

require_once __DIR__ . '/ParametreService.php';

class EligibilityService
{
    private ParametreService $parametreService;

    public function __construct(?PDO $pdo = null, ?ParametreService $parametreService = null)
    {
        $this->parametreService = $parametreService ?? new ParametreService($pdo);
    }

    /**
     * Calcule l'âge exact en années révolues à partir de la date de naissance (en tenant compte du mois et du jour)
     * Retourne null si la date est absente, invalide ou dans le futur.
     */
    public function calculateAge(?string $dateNaissance, ?DateTimeInterface $referenceDate = null): ?int
    {
        if (empty($dateNaissance)) {
            return null;
        }

        $dateStr = trim($dateNaissance);
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
        if (!$birthDate || $birthDate->format('Y-m-d') !== $dateStr) {
            return null;
        }

        $today = $referenceDate
            ? DateTimeImmutable::createFromInterface($referenceDate)->setTime(0, 0, 0)
            : new DateTimeImmutable('today');

        // Refuser les dates futures ou antérieures à 1900
        if ($birthDate > $today || (int)$birthDate->format('Y') < 1900) {
            return null;
        }

        return $birthDate->diff($today)->y;
    }

    /**
     * Valide la date de naissance côté serveur (format Y-m-d, date réelle, non future)
     */
    public function validateBirthDate(?string $dateNaissance): array
    {
        if (empty($dateNaissance) || trim($dateNaissance) === '') {
            return [
                'valid' => false,
                'error' => "Veuillez renseigner votre date de naissance.",
                'age'   => null,
                'date'  => null
            ];
        }

        $dateStr = trim($dateNaissance);
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
        if (!$birthDate || $birthDate->format('Y-m-d') !== $dateStr) {
            return [
                'valid' => false,
                'error' => "La date de naissance saisie n'est pas valide.",
                'age'   => null,
                'date'  => null
            ];
        }

        $today = new DateTimeImmutable('today');
        if ($birthDate > $today) {
            return [
                'valid' => false,
                'error' => "La date de naissance ne peut pas être une date future.",
                'age'   => null,
                'date'  => null
            ];
        }

        if ((int)$birthDate->format('Y') < 1900) {
            return [
                'valid' => false,
                'error' => "L'année de naissance saisie est invalide.",
                'age'   => null,
                'date'  => null
            ];
        }

        $age = $birthDate->diff($today)->y;

        return [
            'valid' => true,
            'error' => null,
            'age'   => $age,
            'date'  => $dateStr
        ];
    }

    /**
     * Valide l'ancienneté déclarée manuellement (entier >= 0, jamais calculée automatiquement)
     */
    public function validateAnciennete(mixed $rawAnciennete): array
    {
        if ($rawAnciennete === null || trim((string)$rawAnciennete) === '') {
            return [
                'valid'      => false,
                'error'      => "Veuillez saisir votre ancienneté (en années).",
                'anciennete' => null
            ];
        }

        $strVal = trim((string)$rawAnciennete);
        if (!preg_match('/^-?\d+$/', $strVal)) {
            return [
                'valid'      => false,
                'error'      => "L'ancienneté doit être un nombre entier d'années.",
                'anciennete' => null
            ];
        }

        $intVal = (int)$strVal;
        if ($intVal < 0) {
            return [
                'valid'      => false,
                'error'      => "L'ancienneté ne peut pas être négative.",
                'anciennete' => null
            ];
        }

        if ($intVal > 100) {
            return [
                'valid'      => false,
                'error'      => "La valeur d'ancienneté saisie est invalide.",
                'anciennete' => null
            ];
        }

        return [
            'valid'      => true,
            'error'      => null,
            'anciennete' => $intVal
        ];
    }

    /**
     * Évalue automatiquement l'éligibilité d'un membre à la liste électorale selon les paramètres configurés
     */
    public function evaluateMemberEligibility(?string $dateNaissance, ?int $anciennete): array
    {
        $conditions = $this->parametreService->getElectorConditions();
        $age = $this->calculateAge($dateNaissance);

        $motifs = [];
        $explications = [];

        // A. Vérification de l'âge minimum (si activée)
        if ($conditions['age_min_actif']) {
            $ageMin = $conditions['age_min_valeur'];
            if ($age === null) {
                $motifs[] = "Âge non renseigné. Âge minimum requis : {$ageMin} ans.";
                $explications[] = "Votre inscription ne peut pas être validée comme électeur, car votre date de naissance est manquante alors que l'âge minimum requis est de {$ageMin} ans.";
            } elseif ($age < $ageMin) {
                $unitAge = ($age <= 1) ? 'an' : 'ans';
                $unitMin = ($ageMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Âge déclaré : {$age} {$unitAge}. Âge minimum requis : {$ageMin} {$unitMin}.";
                $explications[] = "Votre inscription ne peut pas être validée comme électeur, car vous avez {$age} {$unitAge} alors que l'âge minimum requis est de {$ageMin} {$unitMin}.";
            }
        }

        // B. Vérification de l'ancienneté minimum (si activée - saisie manuellement, jamais calculée)
        if ($conditions['anciennete_min_actif']) {
            $ancMin = $conditions['anciennete_min_valeur'];
            if ($anciennete === null) {
                $unitMin = ($ancMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Ancienneté non déclarée. Ancienneté minimum requise : {$ancMin} {$unitMin}.";
                $explications[] = "Votre inscription ne peut pas être validée comme électeur, car votre ancienneté n'est pas renseignée alors que le minimum requis est de {$ancMin} {$unitMin}.";
            } elseif ($anciennete < $ancMin) {
                $unitAnc = ($anciennete <= 1) ? 'an' : 'ans';
                $unitMin = ($ancMin <= 1) ? 'an' : 'ans';
                $motifs[] = "Ancienneté déclarée : {$anciennete} {$unitAnc}. Ancienneté minimum requise : {$ancMin} {$unitMin}.";
                $explications[] = "Votre inscription ne peut pas être validée comme électeur, car votre ancienneté déclarée est de {$anciennete} {$unitAnc} alors que le minimum requis est de {$ancMin} {$unitMin}.";
            }
        }

        $eligible = empty($motifs);

        return [
            'eligible'            => $eligible,
            'eligibilite_a_voter' => $eligible ? 'oui' : 'non',
            'age'                 => $age,
            'anciennete'          => $anciennete,
            'motifs'              => $motifs,
            'explications'        => $explications,
            'motif_db'            => $eligible ? null : implode(' | ', $motifs),
            'conditions'          => $conditions,
        ];
    }
}

