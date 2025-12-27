<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('eleve');

$idEleve = $_SESSION['id_eleve'] ?? 0;

// Commandes de l'élève
$cmdStmt = db()->prepare('
    SELECT c.id_commande, c.quantite, c.statut, m.date_menu, m.type_repas, m.description
    FROM commandes c
    JOIN menus m ON c.id_menu = m.id_menu
    WHERE c.id_eleve = :id
    ORDER BY m.date_menu DESC
');
$cmdStmt->execute([':id' => $idEleve]);
$commandes = $cmdStmt->fetchAll();

// Suivi nutritionnel
$nutriStmt = db()->prepare('SELECT * FROM v_nutrition_eleve WHERE id_eleve = :id');
$nutriStmt->execute([':id' => $idEleve]);
$nutriRows = $nutriStmt->fetchAll();

// Solde
$soldeStmt = db()->prepare('SELECT * FROM v_solde_eleve WHERE id_eleve = :id');
$soldeStmt->execute([':id' => $idEleve]);
$solde = $soldeStmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Élève</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/eleve/commande_create.php">Passer une commande</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Déconnexion</a>
    </nav>
    <h1>Bonjour</h1>

    <h2>Vos commandes</h2>
    <table>
        <tr>
            <th>Date</th><th>Type</th><th>Description</th><th>Quantité</th><th>Statut</th>
        </tr>
        <?php foreach ($commandes as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['date_menu'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['type_repas'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['quantite'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['statut'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$commandes): ?>
            <tr><td colspan="5">Aucune commande.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Suivi nutritionnel</h2>
    <table>
        <tr>
            <?php if (!empty($nutriRows)): ?>
                <?php foreach (array_keys($nutriRows[0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            <?php endif; ?>
        </tr>
        <?php foreach ($nutriRows as $row): ?>
            <tr>
                <?php foreach ($row as $val): ?>
                    <td><?= htmlspecialchars($val ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$nutriRows): ?>
            <tr><td>Pas de données nutritionnelles.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Votre solde</h2>
    <?php if ($solde): ?>
        <p>Solde actuel : <strong><?= htmlspecialchars($solde['solde'] ?? '0') ?></strong></p>
    <?php else: ?>
        <p>Solde non disponible.</p>
    <?php endif; ?>
</div>
</body>
</html>
