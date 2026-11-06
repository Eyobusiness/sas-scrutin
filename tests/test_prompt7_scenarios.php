<?php
/**
 * Suite de tests automatisés — PROMPT 7
 * Gestion complète du cycle électoral, des verrouillages automatiques et du dernier scrutin SAS
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Models/Membre.php';
require_once __DIR__ . '/../app/Models/Candidat.php';
require_once __DIR__ . '/../app/Models/Vote.php';
require_once __DIR__ . '/../app/Models/Participation.php';
require_once __DIR__ . '/../app/Services/ElectionService.php';
require_once __DIR__ . '/../app/Services/ParametreService.php';
require_once __DIR__ . '/../app/Services/EligibilityService.php';
require_once __DIR__ . '/../app/Services/CandidatureService.php';
require_once __DIR__ . '/../app/Services/VoteService.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';

$pdo = Database::getConnection();
$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
}

echo "=== DÉBUT DES TESTS DU CYCLE ÉLECTORAL & DERNIER SCRUTIN (PROMPT 7) ===\n\n";

$electionModel = new Election($pdo);
$electionService = new ElectionService($pdo);
$candidatureService = new CandidatureService($pdo);
$candidatModel = new Candidat($pdo);
$membreModel = new Membre($pdo);
$voteModel = new Vote($pdo);
$voteService = new VoteService($pdo);
$resultatService = new ResultatService($pdo);

$tz = new DateTimeZone('Africa/Abidjan');

// 0. Vérification du fuseau horaire Africa/Abidjan
assertTest(
    date_default_timezone_get() === 'Africa/Abidjan',
    'Fuseau horaire PHP configuré sur Africa/Abidjan'
);

// Nettoyage d'éventuelles élections de test précédentes
$pdo->exec("DELETE FROM votes WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
$pdo->exec("DELETE FROM participations WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
$pdo->exec("DELETE FROM candidats WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
$pdo->exec("DELETE FROM elections WHERE titre LIKE 'TEST_P7_%'");
$pdo->exec("DELETE FROM membres WHERE telephone IN ('0799000701', '0799000702')");

try {
    // 1. Validation de cohérence des dates de phases électorales
    $invalidDates = $electionService->validateElectionDates([
        'date_ouverture_inscriptions' => '2026-05-01 08:00:00',
        'date_fermeture_inscriptions' => '2026-05-15 18:00:00', // Finit après le début du vote (10 mai)
        'date_debut_vote'             => '2026-05-10 08:00:00',
        'date_fin_vote'               => '2026-05-10 18:00:00',
    ]);
    assertTest(
        $invalidDates['valid'] === false && !empty($invalidDates['errors']),
        'Validation des dates : rejet d\'une période d\'inscriptions se terminant après le début du vote'
    );

    $validDates = $electionService->validateElectionDates([
        'date_ouverture_inscriptions' => '2026-05-01 08:00:00',
        'date_fermeture_inscriptions' => '2026-05-05 18:00:00',
        'date_ouverture_candidatures' => '2026-05-02 08:00:00',
        'date_fermeture_candidatures' => '2026-05-06 18:00:00',
        'date_debut_campagne'         => '2026-05-07 08:00:00',
        'date_fin_campagne'           => '2026-05-09 23:59:59',
        'date_debut_vote'             => '2026-05-10 08:00:00',
        'date_fin_vote'               => '2026-05-10 18:00:00',
    ]);
    assertTest(
        $validDates['valid'] === true && empty($validDates['errors']),
        'Validation des dates : acceptation d\'un calendrier électoral cohérent complet'
    );

    // Création de 2 élections pour tester que la dernière élection créée devient automatiquement la référence
    $oldElectionId = $electionModel->create([
        'titre'       => 'TEST_P7_ANCIEN_SCRUTIN_2024',
        'description' => 'Ancien scrutin clôturé',
        'date_debut_vote' => '2024-06-01 08:00:00',
        'date_fin_vote'   => '2024-06-01 18:00:00',
        'statut'          => 'cloturee'
    ]);

    $refElectionId = $electionModel->create(array_merge($validDates['dates'], [
        'titre'       => 'TEST_P7_SCRUTIN_REFERENCE_2026',
        'description' => 'Scrutin de référence',
        'statut'      => 'preparation'
    ]));

    // Scénario 12 : Création d'une nouvelle élection qui devient automatiquement l'élection de référence
    $derniere = $electionService->getDerniereElection(new DateTimeImmutable('2026-04-25 12:00:00', $tz));
    assertTest(
        $derniere !== null && (int)$derniere['id'] === $refElectionId && $derniere['titre'] === 'TEST_P7_SCRUTIN_REFERENCE_2026',
        'Scénario 12 : La dernière élection créée est automatiquement déterminée comme scrutin de référence'
    );

    // Vérifier Phase 1 (Préparation) avant le 1er mai 2026
    $tPrep = new DateTimeImmutable('2026-04-25 12:00:00', $tz);
    $phasePrep = $electionService->getCurrentPhase($derniere, $tPrep);
    assertTest(
        $phasePrep['code'] === ElectionService::PHASE_PREPARATION && $phasePrep['public_case'] === 1,
        'Phase 1 (Préparation) détectée automatiquement avant l\'ouverture des inscriptions (Cas public 1)'
    );

    // Scénario 1 : Inscription pendant la période autorisée + exactitude à la seconde d'ouverture (2026-05-01 08:00:00)
    $tOpenExactIns = new DateTimeImmutable('2026-05-01 08:00:00', $tz);
    $regOpenExact = $electionService->canRegisterMember($derniere, $tOpenExactIns);
    assertTest(
        $regOpenExact['allowed'] === true,
        'Scénario 1 : Inscription autorisée dès l\'heure exacte d\'ouverture des inscriptions (2026-05-01 08:00:00)'
    );

    // Scénario 2 : Inscription refusée dès l'heure exacte de fermeture (2026-05-05 18:00:00) et après le début du vote
    $tCloseExactIns = new DateTimeImmutable('2026-05-05 18:00:00', $tz);
    $regCloseExact = $electionService->canRegisterMember($derniere, $tCloseExactIns);
    assertTest(
        $regCloseExact['allowed'] === false
        && $regCloseExact['message'] === 'Les inscriptions pour cette élection sont désormais clôturées.',
        'Scénario 2a : Inscription bloquée dès l\'heure exacte de fermeture des inscriptions avec le message exact'
    );

    $tDuringVote = new DateTimeImmutable('2026-05-10 08:00:00', $tz);
    $regDuringVote = $electionService->canRegisterMember($derniere, $tDuringVote);
    $modEligDuringVote = $electionService->canModifyMemberEligibility($derniere, $tDuringVote);
    assertTest(
        $regDuringVote['allowed'] === false
        && $modEligDuringVote['allowed'] === false
        && $regDuringVote['message'] === 'Les inscriptions pour cette élection sont désormais clôturées.',
        'Scénario 2b : Inscription et modification d\'éligibilité verrouillées dès la date de début du vote'
    );

    // Scénario 3 : Dépôt de candidature sans sélection manuelle d'élection pendant la période d'ouverture
    $tOpenExactCand = new DateTimeImmutable('2026-05-02 08:00:00', $tz);
    $candOpenCheck = $candidatureService->checkCanSubmitForElection(
        $derniere,
        'KONAN',
        'Serge',
        '0788000701',
        $tOpenExactCand
    );
    assertTest(
        $candOpenCheck['allowed'] === true && (int)$candOpenCheck['election']['id'] === $refElectionId,
        'Scénario 3a : Dépôt de candidature autorisé dès l\'heure exacte d\'ouverture sur le dernier scrutin'
    );

    // Enregistrer un candidat et vérifier l'anti-doublon + le statut initial 'en_attente'
    $candId = $candidatModel->create([
        'election_id'            => $refElectionId,
        'nom'                    => 'KONAN',
        'prenoms'                => 'Serge',
        'telephone'              => '0788000701',
        'date_naissance'         => '1990-03-15',
        'anciennete'             => 4,
        'droit_candidature_paye' => 1,
        'motif_candidature'      => 'Servir le SAS dans l\'unité',
        'statut'                 => 'en_attente'
    ]);
    $dupCheck = $candidatureService->checkCanSubmitForElection(
        $derniere,
        'KONAN',
        'Serge',
        '0788000701',
        $tOpenExactCand
    );
    assertTest(
        $dupCheck['allowed'] === false && $dupCheck['reason'] === 'duplicate',
        'Scénario 3b : Blocage d\'une candidature doublon pour le même candidat sur le même scrutin'
    );

    // Scénario 4 : Dépôt de candidature refusé après la fermeture des candidatures (heure exacte 2026-05-06 18:00:00)
    $tCloseExactCand = new DateTimeImmutable('2026-05-06 18:00:00', $tz);
    $candClosedCheck = $electionService->canSubmitCandidature($derniere, $tCloseExactCand);
    assertTest(
        $candClosedCheck['allowed'] === false
        && $candClosedCheck['message'] === 'La période de dépôt des candidatures pour cette élection est clôturée. Aucune nouvelle candidature ne peut être enregistrée.',
        'Scénario 4 : Dépôt de candidature refusé dès l\'heure exacte de fermeture avec le message exact'
    );

    // Vérifier Phase Campagne électorale (Cas public 3)
    $tCampagne = new DateTimeImmutable('2026-05-08 10:00:00', $tz);
    $phaseCampagne = $electionService->getCurrentPhase($derniere, $tCampagne);
    assertTest(
        $phaseCampagne['code'] === ElectionService::PHASE_CAMPAGNE && $phaseCampagne['public_case'] === 3,
        'Phase 4 (Campagne électorale) détectée automatiquement entre début et fin de campagne (Cas public 3)'
    );

    // Valider le candidat par le bureau pour les tests de vote
    $candidatModel->updateStatut($candId, 'validee', null, 1, 1);

    // Créer un membre éligible et un membre non éligible
    $membreEligibleId = $membreModel->create([
        'nom'                 => 'ASSI',
        'prenoms'             => 'Jean',
        'telephone'           => '0799000701',
        'date_naissance'      => '1993-07-10',
        'anciennete'          => 3,
        'fonction_pro'        => 'Ingénieur',
        'responsabilite_sas'  => 'Membre',
        'statut'              => 'actif',
        'eligibilite_a_voter' => 'oui'
    ]);

    $membreNonEligibleId = $membreModel->create([
        'nom'                   => 'YAPO',
        'prenoms'               => 'Junior',
        'telephone'             => '0799000702',
        'date_naissance'        => '2010-07-10',
        'anciennete'            => 1,
        'fonction_pro'          => 'Élève',
        'responsabilite_sas'    => 'Membre',
        'statut'                => 'actif',
        'eligibilite_a_voter'   => 'non',
        'motif_non_eligibilite' => 'Âge insuffisant'
    ]);

    // Ouvrir administrativement le scrutin pour tester les bornes horaires du vote
    $electionModel->setStatut($refElectionId, 'ouverte');
    $derniereOuverte = $electionModel->findById($refElectionId);

    // Scénario 5 : Vote refusé avant la date de début du vote (2026-05-10 07:59:59)
    $tBeforeVote = new DateTimeImmutable('2026-05-10 07:59:59', $tz);
    $canVoteBefore = $electionService->canVote($derniereOuverte, $tBeforeVote);
    assertTest(
        $canVoteBefore['can_vote'] === false && str_contains($canVoteBefore['message'], 'n\'a pas encore démarré'),
        'Scénario 5 : Vote refusé 1 seconde avant la date de début du vote (07:59:59)'
    );

    // Scénario 6a : Vote autorisé dès la seconde exacte d'ouverture du vote (2026-05-10 08:00:00)
    $tVoteStartExact = new DateTimeImmutable('2026-05-10 08:00:00', $tz);
    $canVoteStartExact = $electionService->canVote($derniereOuverte, $tVoteStartExact);
    assertTest(
        $canVoteStartExact['can_vote'] === true,
        'Scénario 6a : Vote autorisé dès la seconde exacte de début du vote (08:00:00)'
    );

    // Mettre les dates du scrutin autour de maintenant pour tester l'authentification réelle et le vote en base
    $nowMinus1h = date('Y-m-d H:i:s', time() - 3600);
    $nowPlus1h  = date('Y-m-d H:i:s', time() + 3600);
    $electionModel->update($refElectionId, [
        'titre'                       => 'TEST_P7_SCRUTIN_REFERENCE_2026',
        'description'                 => 'Scrutin de référence actif',
        'date_ouverture_inscriptions' => date('Y-m-d H:i:s', time() - 86400 * 5),
        'date_fermeture_inscriptions' => date('Y-m-d H:i:s', time() - 7200),
        'date_ouverture_candidatures' => date('Y-m-d H:i:s', time() - 86400 * 4),
        'date_fermeture_candidatures' => date('Y-m-d H:i:s', time() - 7200),
        'date_debut_campagne'         => date('Y-m-d H:i:s', time() - 86400 * 2),
        'date_fin_campagne'           => $nowMinus1h,
        'date_debut_vote'             => $nowMinus1h,
        'date_fin_vote'               => $nowPlus1h,
        'statut'                      => 'ouverte'
    ]);
    $derniereActive = $electionService->getDerniereElection();

    // Scénario 8 : Vote refusé pour un membre non éligible
    $step1NonElig = $voteService->verifyPhoneStep($derniereActive, '0799000702');
    assertTest(
        $step1NonElig['success'] === false && str_contains($step1NonElig['message'], 'n\'êtes pas autorisé'),
        'Scénario 8 : Vote refusé pour un membre non éligible'
    );

    // Scénario 6b : Vote complet d'un membre éligible pendant la période de vote
    $voteService->resetVoterSession();
    $voteService->resetRateLimit();
    $s1 = $voteService->verifyPhoneStep($derniereActive, '0799000701');
    $s2 = $voteService->verifyBirthDateStep($derniereActive, '1993-07-10');
    $s3 = $voteService->castAuthenticatedVote($derniereActive, $candId);
    assertTest(
        $s1['success'] === true && $s2['success'] === true && $s3['success'] === true,
        'Scénario 6b : Vote autorisé et enregistré pour un membre éligible pendant la période de vote'
    );

    // Scénario 9 : Vote refusé pour un membre ayant déjà voté
    $s1Dup = $voteService->verifyPhoneStep($derniereActive, '0799000701');
    assertTest(
        $s1Dup['success'] === false && str_contains($s1Dup['message'], 'déjà participé'),
        'Scénario 9 : Vote refusé pour un membre ayant déjà voté'
    );

    // Scénario 10 : Masquage des résultats au public pendant le vote + Absence totale de liste déroulante d'élection
    $currentElection = $derniereActive;
    $phaseInfo = $derniereActive['phase_info'];
    $isCloturee = $phaseInfo['is_closed'];
    $donnees = null;
    ob_start();
    include __DIR__ . '/../views/public/resultats.php';
    $htmlDuringVote = ob_get_clean();

    assertTest(
        $isCloturee === false
        && str_contains($htmlDuringVote, 'Le vote est ouvert')
        && !str_contains($htmlDuringVote, '<select name="election_id"')
        && !str_contains($htmlDuringVote, 'winner-proclamation-banner'),
        'Scénario 10 & 13 : Résultats masqués au public pendant le vote et aucun sélecteur d\'ancienne élection présent'
    );

    // Scénario 7 : Vote refusé dès que date_fin_vote est atteinte (heure exacte) + Clôture automatique sans admin connecté
    $pastEnd = date('Y-m-d H:i:s', time() - 10);
    $electionModel->update($refElectionId, [
        'titre'           => 'TEST_P7_SCRUTIN_REFERENCE_2026',
        'description'     => 'Scrutin expiré automatiquement',
        'date_debut_vote' => date('Y-m-d H:i:s', time() - 7200),
        'date_fin_vote'   => $pastEnd,
        'statut'          => 'ouverte' // L'admin ne l'a pas encore clôturé manuellement
    ]);

    // Tenter un vote direct via Vote::castVote après date_fin_vote
    [$lateVoteSuccess, $lateVoteMsg] = $voteModel->castVote($refElectionId, $candId, $membreEligibleId);
    $reloadedAfterLateVote = $electionModel->findById($refElectionId);
    assertTest(
        $lateVoteSuccess === false
        && $reloadedAfterLateVote['statut'] === 'cloturee'
        && !empty($reloadedAfterLateVote['date_cloture_effective']),
        'Scénario 7 : Vote immédiatement refusé après date_fin_vote et clôture effective enregistrée automatiquement en base'
    );

    // Scénario 11 : Affichage automatique des résultats du dernier scrutin après la clôture
    $derniereCloturee = $electionService->getDerniereElection();
    $currentElection = $derniereCloturee;
    $phaseInfo = $derniereCloturee['phase_info'];
    $isCloturee = $phaseInfo['is_closed'];
    $donnees = $resultatService->getResultats((int)$derniereCloturee['id']);
    ob_start();
    include __DIR__ . '/../views/public/resultats.php';
    $htmlAfterClose = ob_get_clean();

    assertTest(
        $isCloturee === true
        && str_contains($htmlAfterClose, 'TEST_P7_SCRUTIN_REFERENCE_2026')
        && str_contains($htmlAfterClose, 'winner-proclamation-banner')
        && str_contains($htmlAfterClose, 'Serge KONAN')
        && !str_contains($htmlAfterClose, 'TEST_P7_ANCIEN_SCRUTIN_2024')
        && !str_contains($htmlAfterClose, '<select name="election_id"'),
        'Scénario 11 & 13 : Affichage automatique des résultats du dernier scrutin clôturé sans accès aux anciennes élections'
    );

    // Vérifier aussi que la vue publique de candidature n'affiche aucun <select name="election_id">
    $derniereElection = $derniereCloturee;
    $candCheck = $electionService->canSubmitCandidature($derniereElection);
    $candidaturesOuvertes = $candCheck['allowed'];
    $candidatureBlockReason = $candCheck['reason'];
    $messageVerrouillageCandidature = $candCheck['message'];
    ob_start();
    include __DIR__ . '/../views/public/candidater.php';
    $htmlCandidater = ob_get_clean();

    assertTest(
        !str_contains($htmlCandidater, '<select name="election_id"')
        && str_contains($htmlCandidater, 'Candidature pour : TEST_P7_SCRUTIN_REFERENCE_2026')
        && str_contains($htmlCandidater, ElectionService::MSG_CANDIDATURES_CLOTUREES),
        'Vue publique candidater.php : absence de <select name="election_id">, affichage en lecture seule du dernier scrutin et du message de clôture'
    );

    // Vérifier le rendu sans erreur des vues d'administration (elections/index.php et elections/details.php)
    require_once __DIR__ . '/../app/Controllers/ElectionController.php';
    $_SESSION['admin_user'] = [
        'id'        => 1,
        'nom'       => 'KOUADIO',
        'prenoms'   => 'Ferdinand',
        'telephone' => '0700000001',
        'role_id'   => 1,
        'role_code' => 'super_admin',
        'role_nom'  => 'Super Administrateur',
        'statut'    => 'actif'
    ];
    ob_start();
    (new ElectionController())->index();
    $htmlAdminIndex = ob_get_clean();

    $_GET['id'] = (string)$refElectionId;
    ob_start();
    (new ElectionController())->details();
    $htmlAdminDetails = ob_get_clean();

    assertTest(
        strlen($htmlAdminIndex) > 5000
        && strlen($htmlAdminDetails) > 5000
        && str_contains($htmlAdminDetails, 'Chronologie visuelle du cycle électoral')
        && str_contains($htmlAdminDetails, 'État des verrouillages automatiques'),
        'Vues admin elections/index.php et elections/details.php : rendu complet avec chronologie visuelle et verrouillages'
    );

} finally {
    // Nettoyage des données de test pour laisser la base de démonstration propre
    $pdo->exec("DELETE FROM votes WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
    $pdo->exec("DELETE FROM participations WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
    $pdo->exec("DELETE FROM candidats WHERE election_id IN (SELECT id FROM elections WHERE titre LIKE 'TEST_P7_%')");
    $pdo->exec("DELETE FROM elections WHERE titre LIKE 'TEST_P7_%'");
    $pdo->exec("DELETE FROM membres WHERE telephone IN ('0799000701', '0799000702')");
}

echo "\n=== RÉSULTAT GLOBAL PROMPT 7 : {$passed} réussi(s), {$failed} échoué(s) ===\n";
exit($failed > 0 ? 1 : 0);
