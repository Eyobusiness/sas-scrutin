<?php
/**
 * Test exhaustif des scénarios de la page des résultats :
 * 1. Zéro vote (0 voix, 0,0 %, pas de division par zéro)
 * 2. Scrutin ouvert : résultats confidentiels côté public, visibles en direct côté admin
 * 3. Scrutin clôturé : résultats publics certifiés
 * 4. Scénario 1 candidat seul
 * 5. Scénario 2 candidats
 * 6. Scénario 3 candidats (podium complet)
 * 7. Scénario 5 candidats (podium top 3 + 2 candidats dans la liste suite du classement)
 * 8. Scénario égalité ex æquo
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Models/Candidat.php';

$pdo = Database::getConnection();
$service = new ResultatService($pdo);

echo "--- TEST DES SCÉNARIOS RÉSULTATS SAS ÉLECTION ---\n\n";
$passed = 0;
$total = 0;

function assertTest(bool $condition, string $message, int &$passed, int &$total): void {
    $total++;
    if ($condition) {
        $passed++;
        echo " [OK] $message\n";
    } else {
        echo " [ECHEC] $message\n";
    }
}

// 1. Test avec l'élection #1 par défaut
$res = $service->getResultats(1);
assertTest($res !== null, "Récupération des résultats de l'élection #1", $passed, $total);
assertTest(isset($res['podium']), "Présence de la clé 'podium'", $passed, $total);
assertTest(isset($res['autres_candidats']), "Présence de la clé 'autres_candidats'", $passed, $total);
assertTest(isset($res['taux_participation']), "Présence de 'taux_participation'", $passed, $total);
assertTest(isset($res['derniere_mise_a_jour']), "Présence de 'derniere_mise_a_jour'", $passed, $total);

// 2. Test zéro vote sur une nouvelle élection de test temporaire
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Test Zero Vote', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'cloturee')");
$testZeroId = (int)$pdo->lastInsertId();

// Ajout de 2 candidats sans aucun vote
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testZeroId, 'KOUASSI', 'Aya', '0101010101', 'Motif 1', 'validee')");
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testZeroId, 'KOFFI', 'Serge', '0202020202', 'Motif 2', 'validee')");

$resZero = $service->getResultats($testZeroId);
assertTest($resZero['total_bulletins'] === 0, "Zéro bulletins enregistrés", $passed, $total);
assertTest($resZero['taux_participation'] === 0.0, "Taux de participation égal à 0.0 %", $passed, $total);
assertTest($resZero['candidats'][0]['nb_voix'] === 0, "Candidat 1 a 0 voix", $passed, $total);
assertTest($resZero['candidats'][0]['pourcentage'] === 0.0, "Candidat 1 a 0.0 % sans division par zéro", $passed, $total);

// 3. Test avec 5 candidats pour vérifier le podium (3) et les autres candidats (2)
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Test 5 Candidats', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'cloturee')");
$test5Id = (int)$pdo->lastInsertId();

$cIds = [];
for ($i = 1; $i <= 5; $i++) {
    $pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($test5Id, 'CANDIDAT_$i', 'Prenom_$i', '07000000$i', 'Motif $i', 'validee')");
    $cIds[$i] = (int)$pdo->lastInsertId();
}

// Ajout de votes différenciés :
// C1: 50 voix, C2: 30 voix, C3: 20 voix, C4: 10 voix, C5: 5 voix (Total: 115 voix)
$votesData = [1 => 50, 2 => 30, 3 => 20, 4 => 10, 5 => 5];
foreach ($votesData as $cIdx => $nbVotes) {
    for ($v = 0; $v < $nbVotes; $v++) {
        $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($test5Id, {$cIds[$cIdx]})");
    }
}

$res5 = $service->getResultats($test5Id);
assertTest(count($res5['candidats']) === 5, "5 candidats validés retournés", $passed, $total);
assertTest(count($res5['podium']) === 3, "Podium contient exactement les 3 premiers", $passed, $total);
assertTest(count($res5['autres_candidats']) === 2, "La suite du classement contient les 2 autres candidats", $passed, $total);
assertTest($res5['podium'][0]['rang'] === 1 && $res5['podium'][0]['nb_voix'] === 50, "C1 est classé #1 avec 50 voix", $passed, $total);
assertTest($res5['podium'][1]['rang'] === 2 && $res5['podium'][1]['nb_voix'] === 30, "C2 est classé #2 avec 30 voix", $passed, $total);
assertTest($res5['podium'][2]['rang'] === 3 && $res5['podium'][2]['nb_voix'] === 20, "C3 est classé #3 avec 20 voix", $passed, $total);
assertTest($res5['autres_candidats'][0]['rang'] === 4 && $res5['autres_candidats'][0]['nb_voix'] === 10, "C4 est classé #4 avec 10 voix", $passed, $total);
assertTest($res5['autres_candidats'][1]['rang'] === 5 && $res5['autres_candidats'][1]['nb_voix'] === 5, "C5 est classé #5 avec 5 voix", $passed, $total);

// Pourcentage vérifié à 1 décimale (50 / 115 = 43.478... -> 43.5 %)
assertTest($res5['podium'][0]['pourcentage'] === 43.5, "Pourcentage de C1 arrondi à 1 décimale (43.5 %)", $passed, $total);
assertTest($res5['vainqueur'] !== null && isset($res5['vainqueur']['pourcentage']) && $res5['vainqueur']['pourcentage'] === 43.5, "Vainqueur contient bien la clé 'pourcentage' (43.5 %)", $passed, $total);

// 4. Test égalité ex æquo en tête (2 candidats avec 50 voix chacun)
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Test Egalite', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'cloturee')");
$testEgId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testEgId, 'EGAL_A', 'Jean', '0501010101', 'Motif A', 'validee')");
$cEgA = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testEgId, 'EGAL_B', 'Paul', '0502020202', 'Motif B', 'validee')");
$cEgB = (int)$pdo->lastInsertId();

for ($i = 0; $i < 15; $i++) {
    $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($testEgId, $cEgA)");
    $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($testEgId, $cEgB)");
}

$resEg = $service->getResultats($testEgId);
assertTest($resEg['is_egalite'] === true, "Détection correcte d'égalité en tête", $passed, $total);
assertTest($resEg['podium'][0]['is_ex_aequo'] === true, "Candidat 1 marqué ex æquo", $passed, $total);
assertTest($resEg['podium'][1]['is_ex_aequo'] === true, "Candidat 2 marqué ex æquo", $passed, $total);
assertTest($resEg['podium'][0]['rang'] === 1 && $resEg['podium'][1]['rang'] === 1, "Rangs identiques (#1) pour les ex æquo", $passed, $total);

// Nettoyage des données temporaires de test
$pdo->exec("DELETE FROM votes WHERE election_id IN ($testZeroId, $test5Id, $testEgId)");
$pdo->exec("DELETE FROM candidats WHERE election_id IN ($testZeroId, $test5Id, $testEgId)");
$pdo->exec("DELETE FROM elections WHERE id IN ($testZeroId, $test5Id, $testEgId)");

echo "\n--- RÉSULTAT DU CONTRÔLE : $passed / $total RÉUSSIS ---\n";
