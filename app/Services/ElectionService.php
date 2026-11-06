<?php
/**
 * Service de gestion du cycle électoral, des phases, des verrouillages automatiques
 * et de détermination du dernier scrutin de référence.
 * Fuseau horaire de référence : Africa/Abidjan (UTC+00:00)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/Election.php';

class ElectionService
{
    public const TIMEZONE = 'Africa/Abidjan';

    public const PHASE_PREPARATION           = 'preparation';
    public const PHASE_INSCRIPTIONS          = 'inscriptions';
    public const PHASE_CANDIDATURES          = 'candidatures';
    public const PHASE_CAMPAGNE              = 'campagne';
    public const PHASE_VOTE                  = 'vote';
    public const PHASE_CLOTURE               = 'cloture';
    public const PHASE_PUBLICATION_RESULTATS = 'publication_resultats';

    public const MSG_INSCRIPTIONS_CLOTUREES = 'Les inscriptions pour cette élection sont désormais clôturées.';
    public const MSG_AUCUNE_ELECTION_CANDIDATURE = 'Aucune élection n\'est actuellement disponible pour recevoir des candidatures.';
    public const MSG_CANDIDATURES_CLOTUREES = 'La période de dépôt des candidatures pour cette élection est clôturée. Aucune nouvelle candidature ne peut être enregistrée.';

    private PDO $pdo;
    private Election $electionModel;

    public function __construct(?PDO $pdo = null)
    {
        date_default_timezone_set(self::TIMEZONE);
        $this->pdo = $pdo ?? Database::getConnection();
        $this->electionModel = new Election($this->pdo);
    }

    public function getElectionModel(): Election
    {
        return $this->electionModel;
    }

    /**
     * Retourne la date/heure courante au format Y-m-d H:i:s dans le fuseau Africa/Abidjan
     */
    public function getNow(?DateTimeInterface $ref = null): string
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        if ($ref !== null) {
            $dt = DateTimeImmutable::createFromInterface($ref)->setTimezone($tz);
            return $dt->format('Y-m-d H:i:s');
        }
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    }

    /**
     * Vérifie et applique automatiquement la clôture du scrutin si date_fin_vote est atteinte
     * Fonctionne même si aucun administrateur n'est connecté.
     */
    public function syncAutoCloture(?array $election, ?DateTimeInterface $ref = null): ?array
    {
        if (!$election) {
            return null;
        }

        $election = $this->electionModel->normalizeRow($election);
        $now = $this->getNow($ref);
        $dateFinVote = $election['date_fin_vote'] ?? ($election['date_fin'] ?? null);

        if (
            !empty($dateFinVote)
            && $now >= $dateFinVote
            && ($election['statut'] ?? '') !== 'cloturee'
        ) {
            if (!empty($election['id']) && !$this->pdo->inTransaction()) {
                $this->electionModel->markAutoClosed((int)$election['id'], $dateFinVote);
            }
            $election['statut'] = 'cloturee';
            if (empty($election['date_cloture_effective'])) {
                $election['date_cloture_effective'] = $dateFinVote;
            }
        }

        return $election;
    }

    /**
     * Récupère automatiquement le dernier scrutin enregistré comme scrutin de référence
     * (ORDER BY id DESC, cree_le DESC LIMIT 1) et synchronise son état réel.
     */
    public function getDerniereElection(?DateTimeInterface $ref = null): ?array
    {
        $election = $this->electionModel->getDerniereElection();
        if (!$election) {
            return null;
        }

        $election = $this->syncAutoCloture($election, $ref);
        $election['phase_info'] = $this->getCurrentPhase($election, $ref);

        return $election;
    }

    /**
     * Récupère une élection par ID avec synchronisation automatique de clôture et informations de phase
     */
    public function getElectionById(int $id, ?DateTimeInterface $ref = null): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $election = $this->electionModel->findById($id);
        if (!$election) {
            return null;
        }

        $election = $this->syncAutoCloture($election, $ref);
        $election['phase_info'] = $this->getCurrentPhase($election, $ref);

        return $election;
    }

    /**
     * Récupère toutes les élections enrichies avec leurs informations de phase et de verrouillage
     */
    public function getAllElectionsEnriched(?DateTimeInterface $ref = null): array
    {
        $rows = $this->electionModel->getAll();
        $result = [];
        foreach ($rows as $row) {
            $row = $this->syncAutoCloture($row, $ref);
            $row['phase_info'] = $this->getCurrentPhase($row, $ref);
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Normalise une chaîne de date/heure issue d'un formulaire HTML datetime-local ou SQL
     */
    public function normalizeDateTime(?string $raw): ?string
    {
        $str = trim((string)($raw ?? ''));
        if ($str === '') {
            return null;
        }
        $str = str_replace('T', ' ', $str);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $str)) {
            $str .= ':00';
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $str, $tz);
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $str) {
            return null;
        }
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Valide la cohérence de toutes les dates des phases électorales d'une élection
     */
    public function validateElectionDates(array $input): array
    {
        $errors = [];

        $dateDebutVote = $this->normalizeDateTime($input['date_debut_vote'] ?? ($input['date_debut'] ?? null));
        $dateFinVote   = $this->normalizeDateTime($input['date_fin_vote'] ?? ($input['date_fin'] ?? null));

        $dateOuvIns  = $this->normalizeDateTime($input['date_ouverture_inscriptions'] ?? null);
        $dateFermIns = $this->normalizeDateTime($input['date_fermeture_inscriptions'] ?? null);

        $dateOuvCand  = $this->normalizeDateTime($input['date_ouverture_candidatures'] ?? null);
        $dateFermCand = $this->normalizeDateTime($input['date_fermeture_candidatures'] ?? null);

        $dateDebCamp = $this->normalizeDateTime($input['date_debut_campagne'] ?? null);
        $dateFinCamp = $this->normalizeDateTime($input['date_fin_campagne'] ?? null);

        if ($dateDebutVote === null || $dateFinVote === null) {
            $errors[] = 'Les dates de début et de fin du vote sont obligatoires et doivent être valides.';
        } elseif ($dateFinVote <= $dateDebutVote) {
            $errors[] = 'La date de fin du vote doit être strictement postérieure à la date de début du vote.';
        }

        // Contrôle de la période d'inscriptions des membres
        $hasAnyIns = ($input['date_ouverture_inscriptions'] ?? '') !== '' || ($input['date_fermeture_inscriptions'] ?? '') !== '';
        if ($hasAnyIns) {
            if ($dateOuvIns === null || $dateFermIns === null) {
                $errors[] = 'Veuillez renseigner à la fois la date d\'ouverture et la date de fermeture des inscriptions.';
            } else {
                if ($dateFermIns <= $dateOuvIns) {
                    $errors[] = 'La date de fermeture des inscriptions doit être postérieure à la date d\'ouverture des inscriptions.';
                }
                if ($dateDebutVote !== null && $dateFermIns > $dateDebutVote) {
                    $errors[] = 'Incohérence des dates : la période d\'inscriptions ne peut pas se terminer après le début du vote.';
                }
            }
        }

        // Contrôle de la période de dépôt des candidatures
        $hasAnyCand = ($input['date_ouverture_candidatures'] ?? '') !== '' || ($input['date_fermeture_candidatures'] ?? '') !== '';
        if ($hasAnyCand) {
            if ($dateOuvCand === null || $dateFermCand === null) {
                $errors[] = 'Veuillez renseigner à la fois la date d\'ouverture et la date de fermeture des candidatures.';
            } else {
                if ($dateFermCand <= $dateOuvCand) {
                    $errors[] = 'La date de fermeture des candidatures doit être postérieure à la date d\'ouverture des candidatures.';
                }
                if ($dateDebutVote !== null && $dateFermCand > $dateDebutVote) {
                    $errors[] = 'Incohérence des dates : le dépôt des candidatures doit se terminer au plus tard au début du vote.';
                }
            }
        }

        // Contrôle de la période de campagne électorale
        $hasAnyCamp = ($input['date_debut_campagne'] ?? '') !== '' || ($input['date_fin_campagne'] ?? '') !== '';
        if ($hasAnyCamp) {
            if ($dateDebCamp === null || $dateFinCamp === null) {
                $errors[] = 'Veuillez renseigner à la fois la date de début et la date de fin de la campagne électorale.';
            } else {
                if ($dateFinCamp <= $dateDebCamp) {
                    $errors[] = 'La date de fin de la campagne électorale doit être postérieure à sa date de début.';
                }
                if ($dateDebutVote !== null && $dateFinCamp > $dateDebutVote) {
                    $errors[] = 'Incohérence des dates : la campagne électorale ne doit pas se poursuivre après le début du vote.';
                }
                if ($dateOuvCand !== null && $dateDebCamp < $dateOuvCand) {
                    $errors[] = 'Incohérence des dates : la campagne électorale ne peut pas commencer avant l\'ouverture des candidatures.';
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'dates'  => [
                'date_ouverture_inscriptions' => $dateOuvIns,
                'date_fermeture_inscriptions' => $dateFermIns,
                'date_ouverture_candidatures' => $dateOuvCand,
                'date_fermeture_candidatures' => $dateFermCand,
                'date_debut_campagne'         => $dateDebCamp,
                'date_fin_campagne'           => $dateFinCamp,
                'date_debut_vote'             => $dateDebutVote,
                'date_fin_vote'               => $dateFinVote,
                'date_debut'                  => $dateDebutVote,
                'date_fin'                    => $dateFinVote,
            ]
        ];
    }

    /**
     * Vérifie si un intervalle [start, end) est actuellement ouvert à l'instant $now
     * Règle d'exactitude (Section 15) : ouvert dès l'heure exacte de début (now >= start),
     * fermé dès l'heure exacte de fin (now < end).
     */
    public function isIntervalOpen(?string $start, ?string $end, string $now): bool
    {
        if (empty($start) || empty($end)) {
            return false;
        }
        return ($now >= $start && $now < $end);
    }

    /**
     * Détermine l'état d'une phase bornée ('a_venir', 'en_cours', 'terminee', 'non_definie')
     */
    private function computeWindowState(?string $start, ?string $end, string $now, bool $forceEnded = false): string
    {
        if ($forceEnded) {
            return 'terminee';
        }
        if (empty($start) || empty($end)) {
            return 'non_definie';
        }
        if ($now < $start) {
            return 'a_venir';
        }
        if ($now >= $start && $now < $end) {
            return 'en_cours';
        }
        return 'terminee';
    }

    /**
     * Vérifie si les inscriptions de nouveaux membres sont autorisées pour le scrutin de référence
     */
    public function canRegisterMember(?array $election = null, ?DateTimeInterface $ref = null): array
    {
        $election = $election ? $this->syncAutoCloture($election, $ref) : $this->getDerniereElection($ref);
        if (!$election) {
            return [
                'allowed' => false,
                'message' => self::MSG_INSCRIPTIONS_CLOTUREES
            ];
        }

        $now = $this->getNow($ref);
        $dateDebutVote = $election['date_debut_vote'] ?? ($election['date_debut'] ?? null);
        $dateOuvIns    = $election['date_ouverture_inscriptions'] ?? null;
        $dateFermIns   = $election['date_fermeture_inscriptions'] ?? null;

        // Dès que l'élection est clôturée ou que la date de début du vote est atteinte -> verrouillage immédiat
        if (($election['statut'] ?? '') === 'cloturee' || (!empty($dateDebutVote) && $now >= $dateDebutVote)) {
            return [
                'allowed' => false,
                'message' => self::MSG_INSCRIPTIONS_CLOTUREES
            ];
        }

        // Si des dates d'inscriptions sont configurées
        if (!empty($dateOuvIns) && !empty($dateFermIns)) {
            if ($now >= $dateFermIns) {
                return [
                    'allowed' => false,
                    'message' => self::MSG_INSCRIPTIONS_CLOTUREES
                ];
            }
            if ($now < $dateOuvIns) {
                return [
                    'allowed' => false,
                    'message' => self::MSG_INSCRIPTIONS_CLOTUREES
                ];
            }
            return [
                'allowed' => true,
                'message' => 'Les inscriptions sont ouvertes.'
            ];
        }

        // Rétrocompatibilité si aucune fenêtre d'inscription explicite n'a été renseignée : autorisé uniquement avant date_debut_vote
        return [
            'allowed' => true,
            'message' => 'Les inscriptions sont ouvertes.'
        ];
    }

    /**
     * Vérifie si l'administration peut ajouter un membre à la liste électorale ou modifier son éligibilité
     * Règle Section 4 : Dès que la date de début du vote est atteinte (ou que les inscriptions sont clôturées),
     * empêcher l'ajout de nouveaux membres et la modification de l'éligibilité pour le scrutin en cours.
     */
    public function canModifyMemberEligibility(?array $election = null, ?DateTimeInterface $ref = null): array
    {
        $election = $election ? $this->syncAutoCloture($election, $ref) : $this->getDerniereElection($ref);
        if (!$election) {
            return [
                'allowed' => true,
                'message' => 'Modification autorisée.'
            ];
        }

        $now = $this->getNow($ref);
        $dateDebutVote = $election['date_debut_vote'] ?? ($election['date_debut'] ?? null);
        $dateFermIns   = $election['date_fermeture_inscriptions'] ?? null;

        // Dès que la date de début du vote est atteinte (pendant le vote ou après) ou si la période d'inscriptions est fermée
        if (
            ($election['statut'] ?? '') === 'cloturee'
            || (!empty($dateDebutVote) && $now >= $dateDebutVote)
            || (!empty($dateFermIns) && $now >= $dateFermIns)
        ) {
            return [
                'allowed' => false,
                'message' => self::MSG_INSCRIPTIONS_CLOTUREES
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Modification autorisée.'
        ];
    }

    /**
     * Vérifie si le dépôt ou l'ajout de candidatures est autorisé pour le scrutin
     */
    public function canSubmitCandidature(?array $election = null, ?DateTimeInterface $ref = null): array
    {
        if ($election === null) {
            $election = $this->getDerniereElection($ref);
        } else {
            $election = $this->syncAutoCloture($election, $ref);
        }

        // Cas 1 : Aucune élection n'existe
        if (!$election) {
            return [
                'allowed' => false,
                'reason'  => 'no_election',
                'message' => self::MSG_AUCUNE_ELECTION_CANDIDATURE
            ];
        }

        $now = $this->getNow($ref);
        $dateDebutVote = $election['date_debut_vote'] ?? ($election['date_debut'] ?? null);
        $dateOuvCand   = $election['date_ouverture_candidatures'] ?? null;
        $dateFermCand  = $election['date_fermeture_candidatures'] ?? null;

        // Si l'élection est clôturée ou que le vote a démarré -> verrouillage strict
        if (($election['statut'] ?? '') === 'cloturee' || (!empty($dateDebutVote) && $now >= $dateDebutVote)) {
            return [
                'allowed' => false,
                'reason'  => 'closed',
                'message' => self::MSG_CANDIDATURES_CLOTUREES
            ];
        }

        // Si une période de candidature est définie, vérifier que now est dans [date_ouverture_candidatures, date_fermeture_candidatures)
        if (!empty($dateOuvCand) && !empty($dateFermCand)) {
            if ($now < $dateOuvCand || $now >= $dateFermCand) {
                return [
                    'allowed' => false,
                    'reason'  => 'closed',
                    'message' => self::MSG_CANDIDATURES_CLOTUREES
                ];
            }
            return [
                'allowed' => true,
                'reason'  => 'open',
                'message' => 'La période de dépôt des candidatures est ouverte.'
            ];
        }

        // Rétrocompatibilité si les dates de candidatures ne sont pas définies : autorisé avant date_debut_vote
        return [
            'allowed' => true,
            'reason'  => 'open',
            'message' => 'La période de dépôt des candidatures est ouverte.'
        ];
    }

    /**
     * Vérifie si le vote est actuellement ouvert pour l'élection
     */
    public function canVote(?array $election = null, ?DateTimeInterface $ref = null): array
    {
        if ($election === null) {
            $election = $this->getDerniereElection($ref);
        } else {
            $election = $this->syncAutoCloture($election, $ref);
        }

        if (!$election) {
            return [
                'can_vote' => false,
                'message'  => 'Aucune élection n\'est actuellement ouverte au vote.'
            ];
        }

        $now = $this->getNow($ref);
        return $this->electionModel->checkCanVote($election, $now);
    }

    /**
     * Détermine la phase électorale courante, la phase suivante, la chronologie complète des 7 phases
     * et l'état de tous les verrouillages automatiques.
     */
    public function getCurrentPhase(array $election, ?DateTimeInterface $ref = null): array
    {
        $election = $this->electionModel->normalizeRow($election);
        $now = $this->getNow($ref);

        $dateOuvIns    = $election['date_ouverture_inscriptions'] ?? null;
        $dateFermIns   = $election['date_fermeture_inscriptions'] ?? null;
        $dateOuvCand   = $election['date_ouverture_candidatures'] ?? null;
        $dateFermCand  = $election['date_fermeture_candidatures'] ?? null;
        $dateDebCamp   = $election['date_debut_campagne'] ?? null;
        $dateFinCamp   = $election['date_fin_campagne'] ?? null;
        $dateDebutVote = $election['date_debut_vote'] ?? ($election['date_debut'] ?? null);
        $dateFinVote   = $election['date_fin_vote'] ?? ($election['date_fin'] ?? null);
        $statut        = $election['statut'] ?? 'preparation';
        $certifies     = !empty($election['resultats_certifies']);

        $isClosed = ($statut === 'cloturee') || (!empty($dateFinVote) && $now >= $dateFinVote);
        $voteStarted = $isClosed || (!empty($dateDebutVote) && $now >= $dateDebutVote && $statut === 'ouverte');

        $insOpen  = !$isClosed && (empty($dateDebutVote) || $now < $dateDebutVote)
            && ((!empty($dateOuvIns) && !empty($dateFermIns)) ? $this->isIntervalOpen($dateOuvIns, $dateFermIns, $now) : true);
        $candOpen = !$isClosed && (empty($dateDebutVote) || $now < $dateDebutVote)
            && ((!empty($dateOuvCand) && !empty($dateFermCand)) ? $this->isIntervalOpen($dateOuvCand, $dateFermCand, $now) : true);
        $campOpen = !$isClosed && (empty($dateDebutVote) || $now < $dateDebutVote)
            && $this->isIntervalOpen($dateDebCamp, $dateFinCamp, $now);
        $voteOpen = !$isClosed && $statut === 'ouverte'
            && !empty($dateDebutVote) && !empty($dateFinVote)
            && $this->isIntervalOpen($dateDebutVote, $dateFinVote, $now);

        // Détermination du code de phase principale et du cas d'affichage public (Cas 1 à 5 de la Section 8)
        if ($isClosed) {
            $currentCode = $certifies ? self::PHASE_PUBLICATION_RESULTATS : self::PHASE_CLOTURE;
            $publicCase  = 5;
        } elseif ($voteOpen) {
            $currentCode = self::PHASE_VOTE;
            $publicCase  = 4;
        } elseif ($campOpen) {
            $currentCode = self::PHASE_CAMPAGNE;
            $publicCase  = 3;
        } elseif (!empty($dateOuvCand) && !empty($dateFermCand) && $this->isIntervalOpen($dateOuvCand, $dateFermCand, $now)) {
            $currentCode = self::PHASE_CANDIDATURES;
            $publicCase  = 2;
        } elseif (!empty($dateOuvIns) && !empty($dateFermIns) && $this->isIntervalOpen($dateOuvIns, $dateFermIns, $now)) {
            $currentCode = self::PHASE_INSCRIPTIONS;
            $publicCase  = 2;
        } else {
            $currentCode = self::PHASE_PREPARATION;
            $publicCase  = 1;
        }

        $phaseLabels = [
            self::PHASE_PREPARATION           => 'Préparation',
            self::PHASE_INSCRIPTIONS          => 'Inscriptions des membres',
            self::PHASE_CANDIDATURES          => 'Dépôt des candidatures',
            self::PHASE_CAMPAGNE              => 'Campagne électorale',
            self::PHASE_VOTE                  => 'Vote en cours',
            self::PHASE_CLOTURE               => 'Scrutin clôturé',
            self::PHASE_PUBLICATION_RESULTATS => 'Publication des résultats',
        ];

        $phaseBadges = [
            self::PHASE_PREPARATION           => 'secondary',
            self::PHASE_INSCRIPTIONS          => 'info',
            self::PHASE_CANDIDATURES          => 'primary',
            self::PHASE_CAMPAGNE              => 'warning',
            self::PHASE_VOTE                  => 'success',
            self::PHASE_CLOTURE               => 'dark',
            self::PHASE_PUBLICATION_RESULTATS => 'success',
        ];

        // Construction de la chronologie visuelle des 7 phases
        $prepDone = $currentCode !== self::PHASE_PREPARATION;
        $timeline = [
            self::PHASE_PREPARATION => [
                'numero'     => 1,
                'code'       => self::PHASE_PREPARATION,
                'libelle'    => 'Préparation',
                'icon'       => 'bi-gear',
                'date_debut' => $election['cree_le'] ?? null,
                'date_fin'   => $dateOuvIns ?? ($dateOuvCand ?? $dateDebutVote),
                'etat'       => ($currentCode === self::PHASE_PREPARATION) ? 'en_cours' : 'terminee',
            ],
            self::PHASE_INSCRIPTIONS => [
                'numero'     => 2,
                'code'       => self::PHASE_INSCRIPTIONS,
                'libelle'    => 'Inscriptions des membres',
                'icon'       => 'bi-person-plus',
                'date_debut' => $dateOuvIns,
                'date_fin'   => $dateFermIns,
                'etat'       => $this->computeWindowState($dateOuvIns, $dateFermIns, $now, $isClosed || $voteStarted),
            ],
            self::PHASE_CANDIDATURES => [
                'numero'     => 3,
                'code'       => self::PHASE_CANDIDATURES,
                'libelle'    => 'Dépôt des candidatures',
                'icon'       => 'bi-person-badge',
                'date_debut' => $dateOuvCand,
                'date_fin'   => $dateFermCand,
                'etat'       => $this->computeWindowState($dateOuvCand, $dateFermCand, $now, $isClosed || $voteStarted),
            ],
            self::PHASE_CAMPAGNE => [
                'numero'     => 4,
                'code'       => self::PHASE_CAMPAGNE,
                'libelle'    => 'Campagne électorale',
                'icon'       => 'bi-megaphone',
                'date_debut' => $dateDebCamp,
                'date_fin'   => $dateFinCamp,
                'etat'       => $this->computeWindowState($dateDebCamp, $dateFinCamp, $now, $isClosed || $voteStarted),
            ],
            self::PHASE_VOTE => [
                'numero'     => 5,
                'code'       => self::PHASE_VOTE,
                'libelle'    => 'Vote',
                'icon'       => 'bi-fingerprint',
                'date_debut' => $dateDebutVote,
                'date_fin'   => $dateFinVote,
                'etat'       => $isClosed ? 'terminee' : ($voteOpen ? 'en_cours' : 'a_venir'),
            ],
            self::PHASE_CLOTURE => [
                'numero'     => 6,
                'code'       => self::PHASE_CLOTURE,
                'libelle'    => 'Clôture du scrutin',
                'icon'       => 'bi-lock',
                'date_debut' => $election['date_cloture_effective'] ?? $dateFinVote,
                'date_fin'   => null,
                'etat'       => $isClosed ? ($certifies ? 'terminee' : 'en_cours') : 'a_venir',
            ],
            self::PHASE_PUBLICATION_RESULTATS => [
                'numero'     => 7,
                'code'       => self::PHASE_PUBLICATION_RESULTATS,
                'libelle'    => 'Publication des résultats',
                'icon'       => 'bi-trophy',
                'date_debut' => $election['date_certification'] ?? ($election['date_cloture_effective'] ?? $dateFinVote),
                'date_fin'   => null,
                'etat'       => $isClosed ? 'en_cours' : 'a_venir',
            ],
        ];

        // Détermination de la phase suivante
        $order = array_keys($timeline);
        $currentIndex = array_search($currentCode, $order, true);
        $nextPhase = null;
        if ($currentIndex !== false) {
            for ($i = $currentIndex + 1; $i < count($order); $i++) {
                $candidateKey = $order[$i];
                if ($timeline[$candidateKey]['etat'] === 'a_venir') {
                    $nextPhase = $timeline[$candidateKey];
                    break;
                }
            }
        }

        // État des verrouillages actifs (Section 13)
        $inscriptionsLocked = !$insOpen;
        $candidaturesLocked = !$candOpen;
        $campagneTerminee   = $isClosed || $voteStarted || (!empty($dateFinCamp) && $now >= $dateFinCamp);

        $locks = [
            'inscriptions_cloturees'   => $inscriptionsLocked,
            'candidatures_cloturees'   => $candidaturesLocked,
            'campagne_terminee'        => $campagneTerminee,
            'scrutin_ouvert'           => $voteOpen,
            'vote_cloture'             => $isClosed,
            'resultats_publics'        => $isClosed,
            'resultats_certifies'      => $certifies,
        ];

        return [
            'code'               => $currentCode,
            'label'              => $phaseLabels[$currentCode] ?? 'Préparation',
            'badge'              => $phaseBadges[$currentCode] ?? 'secondary',
            'public_case'        => $publicCase,
            'now'                => $now,
            'is_closed'          => $isClosed,
            'ins_open'           => $insOpen,
            'cand_open'          => $candOpen,
            'camp_open'          => $campOpen,
            'vote_open'          => $voteOpen,
            'certifies'          => $certifies,
            'next_phase'         => $nextPhase,
            'timeline'           => $timeline,
            'locks'              => $locks,
        ];
    }
}

