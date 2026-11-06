<?php
/**
 * Suite de tests automatisés obligatoires — PROMPT 6
 * Éligibilité automatique des membres, conditions de candidature et double authentification des électeurs
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Membre.php';
require_once __DIR__ . '/../app/Models/Candidat.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Models/Participation.php';
require_once __DIR__ . '/../app/Models/Vote.php';
require_once __DIR__ . '/../app/Services/ParametreService.php';
require_once __DIR__ . '/../app/Services/EligibilityService.php';
require_once __DIR__ . '/../app/Services/CandidatureService.php';
require_once __DIR__ . '/../app/Services/VoteService.php';

SessionHelper::start();
$pdo = Database::getConnection();

$totalTests = 0;
$passedTests = 0;

function checkTest(bool $cond, string $label): void
{
    global $totalTests, $passedTests;
    $totalTests++;
    if ($cond) {
        $passedTests++;
        echo " [OK] {$label}\n";
    } else {
        echo " [ECHEC] {$label}\n";
    }
}

echo "=====================================================================\n";
echo " EXÉCUTION DES TESTS OBLIGATOIRES — PROMPT 6 (SAS ÉLECTION)\n";
echo "=====================================================================\n\n";

$paramService = new ParametreService($pdo);
$eligService = new EligibilityService($pdo, $paramService);
$candService = new CandidatureService($pdo);
$voteService = new VoteService($pdo);
$membreModel = new Membre($pdo);
$candidatModel = new Candidat($pdo);
$electionModel = new Election($pdo);
$participationModel = new Participation($pdo);

// Sauvegarder la configuration initiale des paramètres pour restauration finale
$initialParams = $paramService->getAll();

// Réinitialiser les paramètres de test : âge min = 18 ans (actif), ancienneté min = 2 ans (actif), paiement requis = 1
$paramService->saveEligibilitySettings([
    'age_min_actif'                   => '1',
    'age_min_valeur'                  => '18',
    'anciennete_min_actif'            => '1',
    'anciennete_min_valeur'           => '2',
    'candidature_conditions_communes' => '1',
    'candidature_paiement_requis'     => '1',
]);

// Nettoyage d'éventuelles données de test précédentes
$pdo->exec("DELETE FROM membres WHERE telephone LIKE '079999%'");
$pdo->exec("DELETE FROM candidats WHERE telephone LIKE '078888%'");

// Calcul de dates de naissance dynamiques par rapport à la date actuelle
$dobAdulte25 = (new DateTimeImmutable('today'))->modify('-25 years')->format('Y-m-d');
$dobJeune16 = (new DateTimeImmutable('today'))->modify('-16 years')->format('Y-m-d');
$dobPresque18 = (new DateTimeImmutable('today'))->modify('-18 years +1 day')->format('Y-m-d'); // 17 ans révolus à 1 jour près
$dobExact18 = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d'); // Exactement 18 ans aujourd'hui

echo "--- A. TESTS D'INSCRIPTION MEMBRE & CALCUL D'ÂGE ---\n";

// Précision du calcul d'âge au jour et au mois près
checkTest($eligService->calculateAge($dobPresque18) === 17, "Calcul précis de l'âge au jour/mois près : 17 ans à la veille des 18 ans");
checkTest($eligService->calculateAge($dobExact18) === 18, "Calcul précis de l'âge au jour/mois près : 18 ans le jour anniversaire");

// 1. Membre respectant toutes les conditions : éligibilité automatique
$eval1 = $eligService->evaluateMemberEligibility($dobAdulte25, 3);
$idM1 = $membreModel->create([
    'nom'                   => 'TEST_CONFORME',
    'prenoms'               => 'Jean',
    'telephone'             => '0799990001',
    'date_naissance'        => $dobAdulte25,
    'anciennete'            => 3,
    'fonction_pro'          => 'Ingénieur',
    'responsabilite_sas'    => 'Membre',
    'eligibilite_a_voter'   => $eval1['eligibilite_a_voter'],
    'motif_non_eligibilite' => $eval1['motif_db']
]);
$m1 = $membreModel->findById($idM1);
checkTest(
    $eval1['eligible'] === true && $m1['eligibilite_a_voter'] === 'oui' && $m1['motif_non_eligibilite'] === null,
    "Scénario Inscription 1 : Membre conforme (25 ans, 3 ans d'ancienneté) -> Éligibilité automatique (oui) sans motif inutile"
);

// 2. Membre trop jeune : inscription enregistrée, mais non éligible
$eval2 = $eligService->evaluateMemberEligibility($dobJeune16, 3);
$idM2 = $membreModel->create([
    'nom'                   => 'TEST_JEUNE',
    'prenoms'               => 'Paul',
    'telephone'             => '0799990002',
    'date_naissance'        => $dobJeune16,
    'anciennete'            => 3,
    'fonction_pro'          => 'Lycéen',
    'responsabilite_sas'    => 'Membre',
    'eligibilite_a_voter'   => $eval2['eligibilite_a_voter'],
    'motif_non_eligibilite' => $eval2['motif_db']
]);
$m2 = $membreModel->findById($idM2);
checkTest(
    $eval2['eligible'] === false
    && $m2['eligibilite_a_voter'] === 'non'
    && count($eval2['motifs']) === 1
    && str_contains($eval2['motifs'][0], 'Âge déclaré : 16 ans. Âge minimum requis : 18 ans.')
    && str_contains($eval2['explications'][0], 'car vous avez 16 ans alors que l\'âge minimum requis est de 18 ans.'),
    "Scénario Inscription 2 : Membre trop jeune (16 ans) -> Enregistré mais non éligible (non), seul motif d'âge affiché"
);

// 3. Ancienneté insuffisante : inscription enregistrée, mais non éligible
$eval3 = $eligService->evaluateMemberEligibility($dobAdulte25, 1);
$idM3 = $membreModel->create([
    'nom'                   => 'TEST_ANCIENNETE',
    'prenoms'               => 'Luc',
    'telephone'             => '0799990003',
    'date_naissance'        => $dobAdulte25,
    'anciennete'            => 1,
    'fonction_pro'          => 'Comptable',
    'responsabilite_sas'    => 'Membre',
    'eligibilite_a_voter'   => $eval3['eligibilite_a_voter'],
    'motif_non_eligibilite' => $eval3['motif_db']
]);
$m3 = $membreModel->findById($idM3);
checkTest(
    $eval3['eligible'] === false
    && $m3['eligibilite_a_voter'] === 'non'
    && count($eval3['motifs']) === 1
    && str_contains($eval3['motifs'][0], 'Ancienneté déclarée : 1 an. Ancienneté minimum requise : 2 ans.')
    && str_contains($eval3['explications'][0], 'car votre ancienneté déclarée est de 1 an alors que le minimum requis est de 2 ans.'),
    "Scénario Inscription 3 : Ancienneté insuffisante (1 an < 2 ans) -> Enregistré mais non éligible (non), seul motif d'ancienneté affiché"
);

// 4. Âge et ancienneté insuffisants : les deux motifs affichés
$eval4 = $eligService->evaluateMemberEligibility($dobJeune16, 1);
checkTest(
    $eval4['eligible'] === false
    && count($eval4['motifs']) === 2
    && str_contains($eval4['motifs'][0], 'Âge déclaré : 16 ans')
    && str_contains($eval4['motifs'][1], 'Ancienneté déclarée : 1 an'),
    "Scénario Inscription 4 : Âge (16 ans) et ancienneté (1 an) insuffisants -> Les deux motifs sont affichés"
);

// 5. Condition désactivée dans les paramètres : elle ne bloque plus l'inscription
$paramService->saveEligibilitySettings([
    'age_min_actif'                   => '1',
    'age_min_valeur'                  => '18',
    'anciennete_min_actif'            => '0', // Désactivée
    'anciennete_min_valeur'           => '2',
    'candidature_conditions_communes' => '1',
    'candidature_paiement_requis'     => '1',
]);
$eval5 = $eligService->evaluateMemberEligibility($dobAdulte25, 0);
checkTest(
    $eval5['eligible'] === true && $eval5['eligibilite_a_voter'] === 'oui',
    "Scénario Inscription 5 : Condition d'ancienneté désactivée -> Un membre avec 0 an d'ancienneté n'est plus bloqué"
);

// Réactiver la condition d'ancienneté pour la suite
$paramService->saveEligibilitySettings([
    'age_min_actif'                   => '1',
    'age_min_valeur'                  => '18',
    'anciennete_min_actif'            => '1',
    'anciennete_min_valeur'           => '2',
    'candidature_conditions_communes' => '1',
    'candidature_paiement_requis'     => '1',
]);

// 6. Affichage du numéro et de la date de naissance dans le message de confirmation (rendu vue inscription.php)
$inscriptionResult = [
    'eligible'       => true,
    'nom'            => $m1['nom'],
    'prenoms'        => $m1['prenoms'],
    'telephone'      => $m1['telephone'],
    'date_naissance' => $m1['date_naissance'],
    'anciennete'     => $m1['anciennete'],
    'age'            => 25,
    'motifs'         => [],
    'explications'   => []
];
ob_start();
include __DIR__ . '/../views/public/inscription.php';
$htmlInscription = ob_get_clean();
checkTest(
    str_contains($htmlInscription, 'Félicitations ! Votre inscription a été enregistrée avec succès. Vous êtes autorisé(e) à participer au vote du SAS.')
    && str_contains($htmlInscription, '0799990001')
    && str_contains($htmlInscription, $m1['date_naissance']),
    "Scénario Inscription 6 : Affichage exact du numéro de téléphone et de la date de naissance dans le message de confirmation"
);

echo "\n--- B. TESTS DE CANDIDATURE ---\n";

$election = $electionModel->findById(1);
$electionId = (int)$election['id'];

// 1. Candidat respectant les conditions et droit payé : candidature en attente de validation du bureau
$cEval1 = $candService->evaluateCandidature($dobAdulte25, 4, true);
$idC1 = $candidatModel->create([
    'election_id'            => $electionId,
    'nom'                    => 'CAND_CONFORME',
    'prenoms'                => 'Samuel',
    'telephone'              => '0788880001',
    'date_naissance'         => $dobAdulte25,
    'anciennete'             => 4,
    'droit_candidature_paye' => 1,
    'motif_candidature'      => 'Servir le SAS avec fidélité.',
    'statut'                 => $cEval1['statut_initial']
]);
$c1 = $candidatModel->findById($idC1);
checkTest(
    $cEval1['conforme'] === true && $c1['statut'] === 'en_attente' && (int)$c1['droit_candidature_paye'] === 1,
    "Scénario Candidature 1 : Candidat conforme et droit payé -> Enregistré avec statut 'en_attente' (soumis à validation du bureau)"
);

// 2. Âge insuffisant
$cEval2 = $candService->evaluateCandidature($dobJeune16, 4, true);
checkTest(
    $cEval2['conforme'] === false
    && count($cEval2['motifs']) === 1
    && str_contains($cEval2['motifs'][0], 'Votre âge est inférieur au minimum autorisé'),
    "Scénario Candidature 2 : Âge insuffisant -> Motif précis détecté"
);

// 3. Ancienneté insuffisante
$cEval3 = $candService->evaluateCandidature($dobAdulte25, 1, true);
checkTest(
    $cEval3['conforme'] === false
    && count($cEval3['motifs']) === 1
    && str_contains($cEval3['motifs'][0], 'Votre ancienneté déclarée est insuffisante'),
    "Scénario Candidature 3 : Ancienneté insuffisante -> Motif précis détecté"
);

// 4. Droit de candidature non payé
$cEval4 = $candService->evaluateCandidature($dobAdulte25, 4, false);
checkTest(
    $cEval4['conforme'] === false
    && count($cEval4['motifs']) === 1
    && $cEval4['motifs'][0] === "Le droit de candidature n'a pas encore été payé.",
    "Scénario Candidature 4 : Droit de candidature non payé -> Motif précis détecté"
);

// 5. Plusieurs conditions non respectées
$cEval5 = $candService->evaluateCandidature($dobJeune16, 1, false);
checkTest(
    $cEval5['conforme'] === false && count($cEval5['motifs']) === 3,
    "Scénario Candidature 5 : Plusieurs conditions non respectées -> Les 3 motifs sont tous affichés"
);

// 6. Validation et rejet par le bureau
$candidatModel->updateStatut($idC1, 'validee', null, 1, 1);
$validatedList = $candidatModel->getValidatedByElection($electionId);
$foundValidated = false;
foreach ($validatedList as $vc) {
    if ((int)$vc['id'] === $idC1) {
        $foundValidated = true;
        break;
    }
}
checkTest($foundValidated === true, "Scénario Candidature 6a : Validation par le bureau -> Le candidat apparaît sur le bulletin de vote");

$candidatModel->updateStatut($idC1, 'rejetee', 'Dossier rejeté lors du test', 1, 0);
$c1AfterReject = $candidatModel->findById($idC1);
$validatedListAfter = $candidatModel->getValidatedByElection($electionId);
$foundAfterReject = false;
foreach ($validatedListAfter as $vc) {
    if ((int)$vc['id'] === $idC1) {
        $foundAfterReject = true;
        break;
    }
}
checkTest(
    $c1AfterReject['statut'] === 'rejetee'
    && $c1AfterReject['motif_rejet'] === 'Dossier rejeté lors du test'
    && $foundAfterReject === false,
    "Scénario Candidature 6b : Rejet par le bureau avec motif -> Le candidat n'apparaît plus sur le bulletin de vote"
);

echo "\n--- C. TESTS D'AUTHENTIFICATION DES ÉLECTEURS ET DE VOTE ---\n";

// S'assurer que l'élection #1 est ouverte
$origElection = $electionModel->findById(1);
$electionModel->update(1, [
    'titre'       => $origElection['titre'],
    'description' => $origElection['description'],
    'date_debut'  => date('Y-m-d H:i:s', strtotime('-1 day')),
    'date_fin'    => date('Y-m-d H:i:s', strtotime('+2 days')),
    'statut'      => 'ouverte'
]);
$openElection = $electionModel->findById(1);

// 8. Tentative d'accès direct au bulletin sans authentification
$voteService->resetVoterSession();
$voteService->resetRateLimit();
$directAttempt = $voteService->castAuthenticatedVote($openElection, 1);
checkTest(
    $directAttempt['success'] === false && $voteService->getCurrentStep(1) === 1,
    "Scénario Vote 8 : Tentative d'accès direct au bulletin sans authentification bloquée"
);

// 2. Numéro incorrect
$voteService->resetVoterSession();
$resWrongPhone = $voteService->verifyPhoneStep($openElection, '0700998877');
checkTest(
    $resWrongPhone['success'] === false
    && $resWrongPhone['message'] === 'Numéro de téléphone incorrect ou introuvable dans la liste des électeurs. Veuillez vérifier vos informations.'
    && $voteService->getCurrentStep(1) === 1,
    "Scénario Vote 2 : Numéro incorrect -> Message exact affiché et accès refusé"
);

// 3. Membre non éligible (0799990002 créé plus haut avec eligibilite = 'non')
$voteService->resetVoterSession();
$resNonEligible = $voteService->verifyPhoneStep($openElection, '0799990002');
checkTest(
    $resNonEligible['success'] === false
    && str_starts_with($resNonEligible['message'], "Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que :")
    && $voteService->getCurrentStep(1) === 1,
    "Scénario Vote 3 : Membre non éligible -> Message détaillé avec motif affiché et accès refusé"
);

// 1. Numéro correct et membre éligible (0799990001)
$voteService->resetVoterSession();
$voteService->resetRateLimit();
$resPhoneOk = $voteService->verifyPhoneStep($openElection, '0799990001');
checkTest(
    $resPhoneOk['success'] === true
    && $voteService->getCurrentStep(1) === 2
    && !isset($_SESSION['vote_auth']['date_naissance']),
    "Scénario Vote 1 : Numéro correct et membre éligible -> Passage à l'étape 2 (sans exposer la date de naissance en session)"
);

// 4. Date de naissance incorrecte
$resWrongDob = $voteService->verifyBirthDateStep($openElection, '1980-01-01');
checkTest(
    $resWrongDob['success'] === false
    && $resWrongDob['message'] === 'Les informations fournies ne correspondent pas à celles enregistrées dans le système. Veuillez vérifier votre numéro de téléphone et votre date de naissance.'
    && $voteService->getCurrentStep(1) === 2,
    "Scénario Vote 4 : Date de naissance incorrecte -> Message exact affiché et accès au bulletin bloqué"
);

// 5. Téléphone et date de naissance corrects -> Accès au bulletin et vote
$resDobOk = $voteService->verifyBirthDateStep($openElection, $dobAdulte25);
checkTest(
    $resDobOk['success'] === true && $voteService->getCurrentStep(1) === 3,
    "Scénario Vote 5a : Téléphone et date de naissance corrects -> Accès autorisé à l'étape 3 (bulletin de vote)"
);

$validCandidates = $candidatModel->getValidatedByElection(1);
$targetCandidateId = (int)$validCandidates[0]['id'];
$votesBefore = (new Vote($pdo))->countByElection(1);

$castRes = $voteService->castAuthenticatedVote($openElection, $targetCandidateId);
$votesAfter = (new Vote($pdo))->countByElection(1);
$hasVotedCheck = $participationModel->hasVoted(1, $idM1);

checkTest(
    $castRes['success'] === true
    && $votesAfter === ($votesBefore + 1)
    && $hasVotedCheck === true
    && $voteService->getCurrentStep(1) === 1,
    "Scénario Vote 5b : Vote et participation enregistrés en transaction SQL, session nettoyée"
);

// 6. Membre ayant déjà voté
$resAlreadyVoted = $voteService->verifyPhoneStep($openElection, '0799990001');
checkTest(
    $resAlreadyVoted['success'] === false
    && $resAlreadyVoted['message'] === 'Vous avez déjà participé à cette élection. Merci de patienter pour les résultats.',
    "Scénario Vote 6 : Membre ayant déjà voté -> Message exact affiché dès l'étape 1 et seconde participation bloquée"
);

// 7. Élection clôturée
$electionModel->setStatut(1, 'cloturee');
$closedElection = $electionModel->findById(1);
$resClosed = $voteService->verifyPhoneStep($closedElection, '0701010101');
checkTest(
    $resClosed['success'] === false && str_contains($resClosed['message'], 'clôturé'),
    "Scénario Vote 7 : Élection clôturée -> Vote refusé"
);

// Restauration de l'élection #1 et nettoyage des données temporaires de test
$electionModel->update(1, [
    'titre'       => $origElection['titre'],
    'description' => $origElection['description'],
    'date_debut'  => $origElection['date_debut'],
    'date_fin'    => $origElection['date_fin'],
    'statut'      => $origElection['statut']
]);

// Supprimer le vote de test pour laisser les compteurs intacts
$pdo->exec("DELETE FROM participations WHERE membre_id = {$idM1}");
$stmtDelVote = $pdo->prepare("DELETE FROM votes WHERE election_id = 1 AND candidat_id = :cid ORDER BY id DESC LIMIT 1");
$stmtDelVote->execute([':cid' => $targetCandidateId]);
$pdo->exec("DELETE FROM membres WHERE telephone LIKE '079999%'");
$pdo->exec("DELETE FROM candidats WHERE telephone LIKE '078888%'");

// Vérifier que les anciennes données (membres et candidats existants) sont intactes
$existingMember = $membreModel->findByTelephone('0701010101');
$existingCandidats = $candidatModel->getValidatedByElection(1);
checkTest(
    $existingMember !== null && count($existingCandidats) >= 3,
    "Intégrité des données : Les anciens membres, candidats et élections sont intacts"
);

echo "\n=====================================================================\n";
echo " RÉSULTAT FINAL : {$passedTests} / {$totalTests} TESTS RÉUSSIS\n";
echo "=====================================================================\n";

exit($passedTests === $totalTests ? 0 : 1);

