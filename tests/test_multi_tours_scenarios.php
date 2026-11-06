<?php
/**
 * Suite de tests automatisés complète pour l'évolution multi-tours et la finalisation
 * de la plateforme SAS Élection (Sections 3 à 15 du cahier des charges).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Models/Membre.php';
require_once __DIR__ . '/../app/Models/Candidat.php';
require_once __DIR__ . '/../app/Models/Participation.php';
require_once __DIR__ . '/../app/Models/Vote.php';
require_once __DIR__ . '/../app/Services/TourElectionService.php';
require_once __DIR__ . '/../app/Services/ElecteurTourService.php';
require_once __DIR__ . '/../app/Services/QualificationService.php';
require_once __DIR__ . '/../app/Services/VoteService.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';

SessionHelper::start();

$_SESSION['admin_user'] = [
    'id'        => 1,
    'nom'       => 'KOUADIO',
    'prenoms'   => 'Ferdinand',
    'telephone' => '0700000001',
    'role_id'   => 1,
    'role_code' => 'super_admin',
    'role_nom'  => 'Super Administrateur',
    'statut'    => 'actif',
];

$pdo = Database::getConnection();
$electionModel = new Election($pdo);
$membreModel = new Membre($pdo);
$candidatModel = new Candidat($pdo);
$tourService = new TourElectionService($pdo);
$electeurTourService = new ElecteurTourService($pdo);
$qualificationService = new QualificationService($pdo);
$voteService = new VoteService($pdo);
$resultatService = new ResultatService($pdo);

$passed = 0;
$failed = 0;

function assertMultiTour(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [OK] $label\n";
    } else {
        $failed++;
        echo " [ÉCHEC] $label\n";
    }
}

echo "=================================================================\n";
echo " TESTS AUTOMATISÉS : MULTI-TOURS, QUALIFICATION, ÉLECTEURS & UI\n";
echo "=================================================================\n\n";

$testElectionId = 0;
$testElectionPersistId = 0;
$createdMemberIds = [];

try {
    // Nettoyage préventif d'une éventuelle exécution interrompue
    $pdo->exec("DELETE FROM votes WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM participations WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM candidats WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026')");
    $pdo->exec("DELETE FROM membres WHERE telephone LIKE '078822%'");

    // 1. Créer une élection configurée avec nombre_max_tours = 3 (en préparation au départ)
    $now = time();
    $debutVote = date('Y-m-d H:i:s', $now - 1800);
    $finVote   = date('Y-m-d H:i:s', $now + 7200);

    $testElectionId = $electionModel->create([
        'titre'                       => 'SCRUTIN_MULTI_TOURS_SAS_2026',
        'description'                 => 'Test complet multi-tours SAS',
        'date_ouverture_inscriptions' => date('Y-m-d H:i:s', $now - 86400 * 5),
        'date_fermeture_inscriptions' => date('Y-m-d H:i:s', $now - 86400 * 3),
        'date_ouverture_candidatures' => date('Y-m-d H:i:s', $now - 86400 * 4),
        'date_fermeture_candidatures' => date('Y-m-d H:i:s', $now - 86400 * 2),
        'date_debut_campagne'         => date('Y-m-d H:i:s', $now - 86400),
        'date_fin_campagne'           => date('Y-m-d H:i:s', $now - 3600),
        'date_debut_vote'             => $debutVote,
        'date_fin_vote'               => $finVote,
        'statut'                      => 'preparation',
        'nombre_max_tours'            => 3,
    ]);

    $toursInit = $tourService->getToursByElection($testElectionId);
    assertMultiTour(
        $testElectionId > 0 && count($toursInit) === 1 && (int)$toursInit[0]['numero_tour'] === 1,
        'Section 3 : Création automatique du 1er Tour dans tours_election lors de la création du scrutin'
    );
    $tour1Id = (int)$toursInit[0]['id'];

    // 2. Créer 3 candidats validés (Candidat A, Candidat B, Candidat C)
    $candA = $candidatModel->create([
        'election_id'       => $testElectionId,
        'nom'               => 'KOFFI',
        'prenoms'           => 'Alpha',
        'telephone'         => '0788110001',
        'motif_candidature' => 'Vision A',
        'statut'            => 'validee',
    ]);
    $candB = $candidatModel->create([
        'election_id'       => $testElectionId,
        'nom'               => 'YAO',
        'prenoms'           => 'Bravo',
        'telephone'         => '0788110002',
        'motif_candidature' => 'Vision B',
        'statut'            => 'validee',
    ]);
    $candC = $candidatModel->create([
        'election_id'       => $testElectionId,
        'nom'               => 'KONAN',
        'prenoms'           => 'Charlie',
        'telephone'         => '0788110003',
        'motif_candidature' => 'Vision C',
        'statut'            => 'validee',
    ]);
    $tourService->syncTour1ValidatedCandidates($testElectionId, $tour1Id);

    $candsTour1 = $tourService->getCandidatesForTour($testElectionId, $tour1Id);
    assertMultiTour(
        count($candsTour1) === 3,
        'Section 3 : Association des 3 candidats validés au 1er Tour dans candidats_tour'
    );

    // 3. Créer 13 membres éligibles + 2 membres non éligibles pour tester les votes (5 + 5 + 3 = 13 voix) et les exceptions
    for ($i = 1; $i <= 13; $i++) {
        $tel = sprintf('078822%04d', $i);
        $mid = $membreModel->create([
            'nom'                   => "ELECTEUR_$i",
            'prenoms'               => "Membre_$i",
            'telephone'             => $tel,
            'date_naissance'        => '1992-05-15',
            'age_calcule'           => 33,
            'anciennete'            => 5,
            'responsabilite_sas'    => 'Membre',
            'statut'                => 'actif',
            'eligibilite_a_voter'   => 'oui',
            'motif_non_eligibilite' => null,
        ]);
        $createdMemberIds[$i] = $mid;
    }

    // Membre 14 : Non éligible (âge + ancienneté insuffisants)
    $mNonEligId = $membreModel->create([
        'nom'                   => 'NON_ELIGIBLE',
        'prenoms'               => 'Junior',
        'telephone'             => '0788220014',
        'date_naissance'        => '2010-08-20',
        'age_calcule'           => 15,
        'anciennete'            => 1,
        'responsabilite_sas'    => 'Membre',
        'statut'                => 'actif',
        'eligibilite_a_voter'   => 'non',
        'motif_non_eligibilite' => 'Âge insuffisant (15 ans < 18 ans requis) ; Ancienneté insuffisante (1 an < 2 ans requis)',
    ]);
    $createdMemberIds[14] = $mNonEligId;

    // Membre 15 : Non éligible au départ mais sera ajouté exceptionnellement par le bureau avant l'ouverture du tour
    $mAjoutExcepId = $membreModel->create([
        'nom'                   => 'EXCEPTION_BUREAU',
        'prenoms'               => 'Grace',
        'telephone'             => '0788220015',
        'date_naissance'        => '1995-03-10',
        'age_calcule'           => 31,
        'anciennete'            => 1,
        'responsabilite_sas'    => 'Membre',
        'statut'                => 'actif',
        'eligibilite_a_voter'   => 'non',
        'motif_non_eligibilite' => 'Ancienneté insuffisante (1 an < 2 ans requis)',
    ]);
    $createdMemberIds[15] = $mAjoutExcepId;

    // Synchroniser la liste des électeurs du Tour 1 (pendant que le Tour 1 est en préparation)
    $electeurTourService->syncElecteursForTour($testElectionId, $tour1Id);

    // 4. Tester la gestion exceptionnelle des électeurs par le bureau AVANT ouverture du tour (Section 5)
    assertMultiTour(
        $electeurTourService->isTourVoterListLocked($tour1Id) === false,
        'Section 5 : La liste électorale du tour est ouverte aux modifications du bureau avant l\'ouverture du tour'
    );

    // Sans motif -> refusé
    $resSansMotif = $electeurTourService->modifierElecteurTour($testElectionId, $tour1Id, $mAjoutExcepId, 'ajout', '', 1);
    assertMultiTour(
        $resSansMotif['success'] === false,
        'Section 5 : Toute modification d\'électeur sans motif est strictement refusée'
    );

    // Ajout exceptionnel de Membre 15 avec motif
    $resAjoutExcep = $electeurTourService->modifierElecteurTour(
        $testElectionId,
        $tour1Id,
        $mAjoutExcepId,
        'ajout',
        'Dérogation spéciale accordée par le bureau exécutif SAS',
        1
    );
    assertMultiTour(
        $resAjoutExcep['success'] === true,
        'Section 5 : Ajout exceptionnel d\'un électeur avant ouverture du tour avec motif obligatoire'
    );

    // Retrait exceptionnel de Membre 13 avec motif
    $resRetraitExcep = $electeurTourService->modifierElecteurTour(
        $testElectionId,
        $tour1Id,
        $createdMemberIds[13],
        'retrait',
        'Suspension disciplinaire prononcée par le bureau avant scrutin',
        1
    );
    assertMultiTour(
        $resRetraitExcep['success'] === true,
        'Section 5 : Retrait exceptionnel d\'un électeur avant ouverture du tour avec motif obligatoire'
    );

    // Vérifier le journal d'audit journal_modifications_electeurs
    $journal = $electeurTourService->getJournalModifications($testElectionId, $tour1Id);
    assertMultiTour(
        count($journal) >= 2
        && $journal[0]['type_modification'] === 'retrait'
        && (int)$journal[0]['admin_id'] === 1
        && !empty($journal[0]['motif']),
        'Section 5 : Traçabilité complète (admin_id, date_modification, type_modification, motif) dans journal_modifications_electeurs'
    );

    // 5. Ouvrir le 1er Tour au vote et vérifier le verrouillage de la liste électorale (Section 5)
    $electionModel->setStatut($testElectionId, 'ouverte');
    $openElection = $electionModel->findById($testElectionId);

    assertMultiTour(
        $electeurTourService->isTourVoterListLocked($tour1Id) === true,
        'Section 5 : Dès l\'ouverture du tour, la liste électorale du tour est verrouillée'
    );

    $resModAfterOpen = $electeurTourService->modifierElecteurTour(
        $testElectionId,
        $tour1Id,
        $mNonEligId,
        'ajout',
        'Tentative tardive après ouverture',
        1
    );
    assertMultiTour(
        $resModAfterOpen['success'] === false,
        'Section 5 : Aucune modification de la liste électorale n\'est autorisée une fois le tour ouvert'
    );

    // 6. Vérifier les messages détaillés de non-éligibilité (Section 7)
    // Cas A : Membre 14 non éligible (âge + ancienneté)
    $voteService->resetVoterSession();
    $voteService->resetRateLimit();
    $checkNonElig14 = $voteService->verifyPhoneStep($openElection, '0788220014');
    assertMultiTour(
        $checkNonElig14['success'] === false
        && $checkNonElig14['message'] === "Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que : Âge insuffisant (15 ans < 18 ans requis) ; Ancienneté insuffisante (1 an < 2 ans requis).",
        'Section 7 : Affichage du message détaillé exact avec tous les motifs enregistrés en base pour un membre non éligible'
    );

    // Cas B : Membre 13 retiré exceptionnellement par le bureau
    $voteService->resetVoterSession();
    $voteService->resetRateLimit();
    $checkRetire13 = $voteService->verifyPhoneStep($openElection, '0788220013');
    assertMultiTour(
        $checkRetire13['success'] === false
        && $checkRetire13['message'] === "Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que : Suspension disciplinaire prononcée par le bureau avant scrutin.",
        'Section 7 : Affichage du motif exact enregistré par le bureau lorsqu\'un électeur a été retiré de la liste du tour'
    );

    // 7. Enregistrer les 13 votes du 1er Tour (Membres 1..12 + Membre 15 ajouté exceptionnellement) :
    // - 5 voix pour Candidat A (Membres 1..5)
    // - 5 voix pour Candidat B (Membres 6..10)
    // - 3 voix pour Candidat C (Membres 11, 12 et 15)
    $votersT1 = [
        1 => $candA, 2 => $candA, 3 => $candA, 4 => $candA, 5 => $candA,
        6 => $candB, 7 => $candB, 8 => $candB, 9 => $candB, 10 => $candB,
        11 => $candC, 12 => $candC, 15 => $candC,
    ];

    $allVotesT1Ok = true;
    foreach ($votersT1 as $idx => $cid) {
        $tel = sprintf('078822%04d', $idx);
        $dob = ($idx === 15) ? '1995-03-10' : '1992-05-15';
        $voteService->resetVoterSession();
        $voteService->resetRateLimit();
        $s1 = $voteService->verifyPhoneStep($openElection, $tel);
        $s2 = $voteService->verifyBirthDateStep($openElection, $dob);
        $s3 = $voteService->castAuthenticatedVote($openElection, $cid);
        if (!$s1['success'] || !$s2['success'] || !$s3['success']) {
            $allVotesT1Ok = false;
            echo "Erreur vote Tour 1 pour membre $idx : " . ($s1['message'] ?? '') . ' / ' . ($s2['message'] ?? '') . ' / ' . ($s3['message'] ?? '') . "\n";
        }
    }
    assertMultiTour(
        $allVotesT1Ok === true,
        'Section 4 & 6 : Enregistrement des 13 votes du 1er Tour (5 voix Candidat A, 5 voix Candidat B, 3 voix Candidat C) y compris le membre ajouté exceptionnellement'
    );

    // Vérifier qu'un membre ayant voté au Tour 1 ne peut pas revoter au Tour 1
    $voteService->resetVoterSession();
    $voteService->resetRateLimit();
    $dupT1 = $voteService->verifyPhoneStep($openElection, '0788220001');
    assertMultiTour(
        $dupT1['success'] === false && str_contains($dupT1['message'], 'déjà participé'),
        'Section 6 : Un électeur ayant voté au 1er Tour ne peut pas voter une seconde fois au 1er Tour'
    );

    // 8. Clôture du 1er Tour et vérification de la qualification automatique (Section 4)
    $qualifRes = $qualificationService->cloturerTourEtQualifier(
        $testElectionId,
        $tour1Id,
        date('Y-m-d H:i:s', $now - 600),
        date('Y-m-d H:i:s', $now + 7200)
    );

    assertMultiTour(
        $qualifRes['success'] === true
        && $qualifRes['status'] === 'second_tour_prepare'
        && count($qualifRes['qualifies']) === 2
        && count($qualifRes['elimines']) === 1
        && (int)$qualifRes['elimines'][0]['id'] === $candC
        && !empty($qualifRes['next_tour_id']),
        'Section 4 : Clôture du Tour 1 -> Candidat A (5 voix) et Candidat B (5 voix) qualifiés automatiquement, Candidat C (3 voix) écarté, 2e Tour préparé'
    );

    $tour2Id = (int)$qualifRes['next_tour_id'];

    // 9. Vérifier les résultats et l'affichage du 1er Tour (Cas d'égalité - Section 8)
    $resTour1 = $resultatService->getResultats($testElectionId, $tour1Id);
    assertMultiTour(
        $resTour1['is_egalite'] === true
        && $resTour1['vainqueur'] === null
        && $resTour1['max_voix'] === 5
        && $resTour1['message_officiel'] === 'ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !',
        'Section 8 : Résultats du 1er Tour en égalité -> Aucun vainqueur, message officiel "ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"'
    );

    // Rendu HTML public du Tour 1 (Cas d'égalité)
    $currentElection = $electionModel->findById($testElectionId);
    $isCloturee = true;
    $donnees = $resTour1;
    ob_start();
    include __DIR__ . '/../views/public/resultats.php';
    $htmlPublicT1 = ob_get_clean();

    assertMultiTour(
        str_contains($htmlPublicT1, 'ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !')
        && str_contains($htmlPublicT1, 'podium-equality-orange')
        && str_contains($htmlPublicT1, 'bar-equality-orange')
        && !str_contains($htmlPublicT1, 'podium-crown-badge'),
        'Section 8 (Vue publique) : Couleur orange appliquée aux candidats ex æquo, aucune coupe attribuée, et message "ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !" affiché'
    );

    // Rendu HTML admin du Tour 1 (Cas d'égalité)
    $elections = $electionModel->getAll();
    $selectedElectionId = $testElectionId;
    $donnees = $resTour1;
    ob_start();
    include __DIR__ . '/../views/resultats/admin.php';
    $htmlAdminT1 = ob_get_clean();

    assertMultiTour(
        str_contains($htmlAdminT1, 'ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !')
        && str_contains($htmlAdminT1, 'admin-equality-card')
        && str_contains($htmlAdminT1, 'prog-equality-orange'),
        'Section 8 (Vue admin) : Bandeau orange d\'égalité, barres oranges de même dimension et message "ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"'
    );

    // 10. Ouvrir le 2e Tour et vérifier les candidats présents et le droit de vote au 2e Tour (Section 4 & 6)
    $tourService->updateTour($tour2Id, date('Y-m-d H:i:s', $now - 600), date('Y-m-d H:i:s', $now + 7200), 'ouverte');
    $openElectionT2 = $electionModel->findById($testElectionId);

    $candsTour2 = $tourService->getCandidatesForTour($testElectionId, $tour2Id);
    $candIdsTour2 = array_map(fn($c) => (int)$c['id'], $candsTour2);
    assertMultiTour(
        count($candsTour2) === 2
        && in_array($candA, $candIdsTour2, true)
        && in_array($candB, $candIdsTour2, true)
        && !in_array($candC, $candIdsTour2, true),
        'Section 4 : Seuls les candidats qualifiés (A et B) figurent sur le bulletin du 2e Tour (C est écarté)'
    );

    // Un électeur ayant déjà voté au 1er Tour (Membre 1) PEUT voter au 2e Tour !
    $voteService->resetVoterSession();
    $voteService->resetRateLimit();
    $s1T2 = $voteService->verifyPhoneStep($openElectionT2, '0788220001');
    $s2T2 = $voteService->verifyBirthDateStep($openElectionT2, '1992-05-15');
    // Tenter de voter pour le candidat éliminé C au Tour 2 -> doit être refusé côté serveur
    $s3Elim = $voteService->castAuthenticatedVote($openElectionT2, $candC);
    assertMultiTour(
        $s1T2['success'] === true && $s2T2['success'] === true && $s3Elim['success'] === false,
        'Section 4 & 6 : Un électeur ayant voté au 1er Tour est autorisé à participer au 2e Tour, mais un vote pour un candidat éliminé est rejeté'
    );

    // Enregistrer les votes du 2e Tour : 6 voix pour Candidat A (Membres 1..6), 4 voix pour Candidat B (Membres 7..10)
    $votersT2 = [
        1 => $candA, 2 => $candA, 3 => $candA, 4 => $candA, 5 => $candA, 6 => $candA,
        7 => $candB, 8 => $candB, 9 => $candB, 10 => $candB,
    ];
    $allVotesT2Ok = true;
    foreach ($votersT2 as $idx => $cid) {
        $tel = sprintf('078822%04d', $idx);
        $voteService->resetVoterSession();
        $voteService->resetRateLimit();
        $s1 = $voteService->verifyPhoneStep($openElectionT2, $tel);
        $s2 = $voteService->verifyBirthDateStep($openElectionT2, '1992-05-15');
        $s3 = $voteService->castAuthenticatedVote($openElectionT2, $cid);
        if (!$s1['success'] || !$s2['success'] || !$s3['success']) {
            $allVotesT2Ok = false;
        }
    }
    assertMultiTour(
        $allVotesT2Ok === true,
        'Section 6 : Vote au 2e Tour réussi pour les électeurs ayant déjà voté au 1er Tour (6 voix pour A, 4 voix pour B)'
    );

    // 11. Clôture du 2e Tour -> Victoire unique de Candidat A (Section 9 & 10)
    $qualifResT2 = $qualificationService->cloturerTourEtQualifier($testElectionId, $tour2Id);
    assertMultiTour(
        $qualifResT2['success'] === true
        && $qualifResT2['status'] === 'vainqueur_unique'
        && (int)$qualifResT2['vainqueur']['id'] === $candA
        && $qualifResT2['next_tour_id'] === null,
        'Section 4 & 9 : Clôture du 2e Tour avec un seul candidat en tête -> Victoire proclamée sans création d\'un 3e tour'
    );

    $resTour2 = $resultatService->getResultats($testElectionId, $tour2Id);
    $expectedWinnerMsg = 'Les votes du scrutin "SCRUTIN_MULTI_TOURS_SAS_2026" : vainqueur Alpha KOFFI avec 6 voix (60,0 %).';
    assertMultiTour(
        $resTour2['is_egalite'] === false
        && !empty($resTour2['vainqueur'])
        && $resTour2['message_officiel'] === $expectedWinnerMsg,
        'Section 9 : Phrase officielle exacte générée : « ' . $expectedWinnerMsg . ' »'
    );

    // Vérifier le rendu HTML public en cas de victoire (Section 9)
    $currentElection = $electionModel->findById($testElectionId);
    $isCloturee = true;
    $donnees = $resTour2;
    ob_start();
    include __DIR__ . '/../views/public/resultats.php';
    $htmlPublicT2 = ob_get_clean();

    assertMultiTour(
        str_contains($htmlPublicT2, $expectedWinnerMsg)
        && str_contains($htmlPublicT2, 'winner-green-banner')
        && str_contains($htmlPublicT2, 'podium-winner-green')
        && str_contains($htmlPublicT2, 'podium-crown-badge')
        && str_contains($htmlPublicT2, 'Historique Complet des Tours du Scrutin'),
        'Section 9 & 10 (Vue publique) : Couleur verte appliquée au vainqueur, coupe affichée, phrase exacte présente et historique complet des tours affiché'
    );

    // Vérifier la conservation intacte de l'historique des tours (Tour 1 = 13 bulletins, Tour 2 = 10 bulletins)
    $checkTour1History = $resultatService->getResultats($testElectionId, $tour1Id);
    assertMultiTour(
        $checkTour1History['total_bulletins'] === 13
        && $resTour2['total_bulletins'] === 10
        && count($resTour2['tours_history']) === 2,
        'Section 10 : Conservation intégrale de l\'historique de chaque tour sans écrasement des résultats précédents'
    );

    // 12. Tester l'égalité persistante après nombre_max_tours et la décision du bureau (Section 4 & 14)
    $testElectionPersistId = $electionModel->create([
        'titre'            => 'SCRUTIN_EGALITE_PERSISTANTE_2026',
        'description'      => 'Test égalité persistante après max tours',
        'date_debut_vote'  => $debutVote,
        'date_fin_vote'    => $finVote,
        'statut'           => 'ouverte',
        'nombre_max_tours' => 1, // Max 1 tour pour tester l'atteinte immédiate de la limite
    ]);
    $tPersist = $tourService->getActiveOrLatestTour($testElectionPersistId);
    $cP1 = $candidatModel->create([
        'election_id'       => $testElectionPersistId,
        'nom'               => 'EGAL_UN',
        'prenoms'           => 'Candidat',
        'telephone'         => '0788330001',
        'motif_candidature' => 'Projet 1',
        'statut'            => 'validee',
    ]);
    $cP2 = $candidatModel->create([
        'election_id'       => $testElectionPersistId,
        'nom'               => 'EGAL_DEUX',
        'prenoms'           => 'Candidat',
        'telephone'         => '0788330002',
        'motif_candidature' => 'Projet 2',
        'statut'            => 'validee',
    ]);
    $tourService->syncTour1ValidatedCandidates($testElectionPersistId, (int)$tPersist['id']);
    $pdo->exec("INSERT INTO votes (election_id, tour_id, candidat_id) VALUES ($testElectionPersistId, {$tPersist['id']}, $cP1)");
    $pdo->exec("INSERT INTO votes (election_id, tour_id, candidat_id) VALUES ($testElectionPersistId, {$tPersist['id']}, $cP2)");

    $resPersist = $qualificationService->cloturerTourEtQualifier($testElectionPersistId, (int)$tPersist['id']);
    $elPersistAfter = $electionModel->findById($testElectionPersistId);

    assertMultiTour(
        $resPersist['status'] === 'egalite_persistante'
        && (int)$elPersistAfter['etat_egalite_persistante'] === 1
        && empty($elPersistAfter['vainqueur_candidat_id']),
        'Section 4 : Égalité persistante après nombre_max_tours -> Aucun vainqueur automatique, état « Égalité persistante » activé'
    );

    $resDecision = $qualificationService->enregistrerDecisionBureau(
        $testElectionPersistId,
        (int)$tPersist['id'],
        'Départage statutaire au bénéfice du candidat ayant la plus grande ancienneté SAS',
        'Application des statuts du mouvement SAS après égalité persistante au terme du nombre maximal de tours',
        1,
        $cP1,
        'egalite_persistante'
    );
    $decisionsList = $qualificationService->getDecisionsByElection($testElectionPersistId);
    assertMultiTour(
        $resDecision['success'] === true && count($decisionsList) === 1,
        'Section 4 & 14 : Enregistrement et horodatage de la décision officielle du bureau sans effacer l\'historique des tours'
    );

} finally {
    // Nettoyage propre des données de test (en respectant les contraintes FK sur votes -> candidats)
    $pdo->exec("DELETE FROM votes WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM participations WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM candidats WHERE election_id IN (SELECT id FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026'))");
    $pdo->exec("DELETE FROM elections WHERE titre IN ('SCRUTIN_MULTI_TOURS_SAS_2026', 'SCRUTIN_EGALITE_PERSISTANTE_2026')");
    $pdo->exec("DELETE FROM membres WHERE telephone LIKE '078822%'");
}

echo "\n-----------------------------------------------------------------\n";
echo " RÉSULTAT GLOBAL : $passed / " . ($passed + $failed) . " TESTS VALIDÉS\n";
echo "-----------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
