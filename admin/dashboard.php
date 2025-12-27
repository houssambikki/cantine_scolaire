<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$today = date('Y-m-d');
$filterDate = $_GET['date'] ?? $today;

// Planification des repas
$planStmt = db()->prepare('SELECT * FROM v_planification_repas WHERE date_menu = :d ORDER BY date_menu');
$planStmt->execute([':d' => $filterDate]);
$planifications = $planStmt->fetchAll();

// Alertes allergenes
$alertStmt = db()->prepare('SELECT * FROM v_alertes_allergenes WHERE alerte = 1 AND date_menu = :d');
$alertStmt->execute([':d' => $filterDate]);
$alertes = $alertStmt->fetchAll();

// Soldes financiers (inclut tous les eleves, valeurs issues de la vue)
$soldeStmt = db()->query('
    SELECT e.nom, e.prenom, e.classe, COALESCE(v.solde, 0) AS solde
    FROM eleves e
    LEFT JOIN v_solde_eleve v ON v.id_eleve = e.id_eleve
    ORDER BY solde DESC, e.nom, e.prenom
');
$soldeRows = $soldeStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/menus_management.php">Gestion des menus</a>
        <a class="btn" href="/cantine_scolaire/admin/eleve_management.php">Gestion des eleves</a>
        <a class="btn" href="/cantine_scolaire/admin/paiements_create.php">Enregistrer un paiement</a>
        <a class="btn" href="/cantine_scolaire/admin/create_admins.php">Creer un admin</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>

    <h1>Dashboard Admin</h1>

    <form method="get">
        <label>Filtrer par date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        <button type="submit">Filtrer</button>
    </form>

    <h2>Planification des repas</h2>
    <table>
        <tr>
            <th>Date</th><th>Type</th><th>Description</th><th>Calories</th><th>Allergenes</th>
        </tr>
        <?php foreach ($planifications as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['date_menu'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['type_repas'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['calories_total'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['allergenes'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$planifications): ?>
            <tr><td colspan="5">Aucun resultat.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Alertes allergenes</h2>
    <table>
        <tr>
            <th>Date</th><th>Eleve</th><th>Classe</th><th>Allergene</th><th>Alerte</th>
        </tr>
        <?php foreach ($alertes as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['date_menu'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['nom'] ?? '') ?> <?= htmlspecialchars($row['prenom'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['classe'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['allergenes'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['alerte'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$alertes): ?>
            <tr><td colspan="5">Aucune alerte pour cette date.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Soldes financiers</h2>
    <table>
        <tr>
            <th>Eleve</th><th>Classe</th><th>Solde</th>
        </tr>
        <?php foreach ($soldeRows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['nom'] ?? '') ?> <?= htmlspecialchars($row['prenom'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['classe'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['solde'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$soldeRows): ?>
            <tr><td colspan="3">Aucun solde trouve.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
