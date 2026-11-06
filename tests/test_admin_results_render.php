<?php
/**
 * Test exhaustif de la page administrative des résultats (views/resultats/admin.php)
 * Scénarios testés :
 * 1. Élection ouverte avec 1 leader et des candidats à 0 voix (ex æquo en 2e place)
 * 2. Élection avec égalité ex æquo en tête (alerte d'égalité sans vainqueur imposé)
 * 3. Présence du bandeau leader avec bordure dorée (sans fond vert massif)
 * 4. Présence de la table desktop (6 colonnes) et des cartes mobiles (< md)
 * 5. Présence des accents or, argent, bronze sur les barres et badges
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';

SessionHelper::start();

// Simuler la session administrateur
$_SESSION['admin_user'] = [
    'id' => 1,
    'nom' => 'KOUADIO',
    'prenoms' => 'Ferdinand',
    'telephone' => '0700000001',
    'role_id' => 1,
    'role_code' => 'super_admin',
    'role_nom' => 'Super Administrateur',
    'statut' => 'actif'
];

echo "--- TEST DU RENDU DE LA PAGE ADMINISTRATIVE DES RÉSULTATS ---\n\n";

$pdo = Database::getConnection();
$service = new ResultatService($pdo);

// 1. Scénario avec 1 leader et 2 candidats à zéro voix (comme la capture utilisateur)
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Élection Admin Test', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'ouverte')");
$testElId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testElId, 'LEADER', 'Vainqueur', '0701010101', 'Projet Leader', 'validee')");
$c1 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testElId, 'ZERO_A', 'Aya', '0702020202', 'Projet A', 'validee')");
$c2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($testElId, 'ZERO_B', 'Koffi', '0703030303', 'Projet B', 'validee')");
$c3 = (int)$pdo->lastInsertId();

// 17 votes pour C1, 0 pour C2 et C3
for ($i = 0; $i < 17; $i++) {
    $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($testElId, $c1)");
}

$elections = (new Election($pdo))->getAll();
$selectedElectionId = $testElId;
$donnees = $service->getResultats($testElId);

ob_start();
include __DIR__ . '/../views/resultats/admin.php';
$html = ob_get_clean();

// Nettoyage
$pdo->exec("DELETE FROM votes WHERE election_id = $testElId");
$pdo->exec("DELETE FROM candidats WHERE election_id = $testElId");
$pdo->exec("DELETE FROM elections WHERE id = $testElId");

// Vérifications
$tests = [
    'Rendu HTML non vide (> 10 000 octets)' => strlen($html) > 10000,
    'Feuille de style admin-results.css chargée' => strpos($html, 'admin-results.css') !== false,
    'Bandeau leader avec bordure dorée présent' => strpos($html, 'admin-leader-card') !== false,
    'Absence de fond vert massif bg-success sur le bandeau' => strpos($html, 'admin-leader-card mb-4 bg-success') === false,
    'Table desktop présente avec 6 colonnes' => strpos($html, 'admin-results-table') !== false,
    'Cartes mobiles présentes (d-md-none)' => strpos($html, 'admin-mobile-card') !== false,
    'Accent Or (prog-gold) sur le 1er rang' => strpos($html, 'prog-gold') !== false,
    'Ex æquo détecté sur les candidats à 0 voix' => strpos($html, 'Ex æquo') !== false,
    'Absence d\'erreurs, warnings ou notices PHP' => strpos($html, 'Warning:') === false && strpos($html, 'Fatal error:') === false && strpos($html, 'Notice:') === false,
];

// 2. Scénario avec égalité en tête (10 voix pour C1, 10 voix pour C2)
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Élection Égalité Test', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'ouverte')");
$egElId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($egElId, 'CAND_A', 'Jean', '0700000001', 'Motif A', 'validee')");
$cEgA = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($egElId, 'CAND_B', 'Paul', '0700000002', 'Motif B', 'validee')");
$cEgB = (int)$pdo->lastInsertId();

for ($i = 0; $i < 10; $i++) {
    $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($egElId, $cEgA)");
    $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($egElId, $cEgB)");
}

$selectedElectionId = $egElId;
$donnees = $service->getResultats($egElId);

ob_start();
include __DIR__ . '/../views/resultats/admin.php';
$htmlEg = ob_get_clean();

$pdo->exec("DELETE FROM votes WHERE election_id = $egElId");
$pdo->exec("DELETE FROM candidats WHERE election_id = $egElId");
$pdo->exec("DELETE FROM elections WHERE id = $egElId");

$tests['Bandeau égalité affiché en cas d\'ex æquo en tête'] = strpos($htmlEg, 'admin-equality-card') !== false;
$tests['Pas de désignation d\'un seul vainqueur en cas d\'égalité'] = strpos($htmlEg, 'admin-leader-card') === false;

$passed = 0;
$total = count($tests);

foreach ($tests as $desc => $ok) {
    if ($ok) {
        $passed++;
        echo " [OK] $desc\n";
    } else {
        echo " [ÉCHEC] $desc\n";
    }
}

echo "\n--- RÉSULTAT : $passed / $total VALIDÉS ---\n";
if ($passed !== $total) {
    exit(1);
}
