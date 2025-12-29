<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('eleve');

ensure_session();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$idEleve = $_SESSION['id_eleve'] ?? 0;
$success = null;
$error = null;

// Action annuler commande (autorisee seulement si CONFIRMEE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $token = $_POST['csrf_token'] ?? '';
    $idCommande = (int)($_POST['id_commande'] ?? 0);
    if ($token !== $csrfToken || !$idCommande) {
        $error = "Requete invalide.";
    } else {
        $stmt = db()->prepare('SELECT statut FROM commandes WHERE id_commande = :id AND id_eleve = :e');
        $stmt->execute([':id' => $idCommande, ':e' => $idEleve]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = "Commande introuvable.";
        } elseif ($row['statut'] !== 'CONFIRMEE') {
            $error = "Annulation autorisee uniquement si la commande est CONFIRMEE.";
        } else {
            $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id AND id_eleve = :e');
            $up->execute([':s' => 'ANNULEE', ':id' => $idCommande, ':e' => $idEleve]);
            $success = "Commande annulee.";
        }
    }
}

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

// Soldes et totaux
$soldeStmt = db()->prepare('SELECT * FROM v_solde_eleve WHERE id_eleve = :id');
$soldeStmt->execute([':id' => $idEleve]);
$solde = $soldeStmt->fetch();

$totalAPayerStmt = db()->prepare('SELECT total_a_payer FROM v_total_a_payer_par_eleve WHERE id_eleve = :id');
$totalAPayerStmt->execute([':id' => $idEleve]);
$totalAPayerRow = $totalAPayerStmt->fetch();
$totalAPayer = $totalAPayerRow['total_a_payer'] ?? 0;

$totalPayeStmt = db()->prepare('SELECT total_paye FROM v_total_paye_par_eleve WHERE id_eleve = :id');
$totalPayeStmt->execute([':id' => $idEleve]);
$totalPayeRow = $totalPayeStmt->fetch();
$totalPaye = $totalPayeRow['total_paye'] ?? 0;

// Menus disponibles (liste statique pour affichage)
$menusDisponibles = db()->query("SELECT id_menu, date_menu, type_repas, description FROM menus WHERE date_menu >= CURDATE() ORDER BY date_menu")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard eleve</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
    <script defer src="/cantine_scolaire/public/app.js"></script>
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/eleve/commande_create.php">Passer une commande</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>
    <div class="hero" style="margin-bottom:16px;">
        <h1>Bonjour</h1>
        <p class="text-muted">Suivez vos commandes, soldes et menus disponibles.</p>
    </div>

    <div class="card-grid">
        <div class="card">
            <p class="text-muted">Solde actuel</p>
            <h2><?= htmlspecialchars(number_format($solde['solde'] ?? 0, 2)) ?></h2>
        </div>
        <div class="card">
            <p class="text-muted">Total à payer</p>
            <h2><?= htmlspecialchars(number_format($totalAPayer, 2)) ?></h2>
        </div>
        <div class="card">
            <p class="text-muted">Total payé</p>
            <h2><?= htmlspecialchars(number_format($totalPaye, 2)) ?></h2>
        </div>
    </div>

    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <h2>Menus disponibles</h2>
    <div style="margin-bottom:12px;">
        <input id="menus-search" type="text" placeholder="Rechercher un menu..." style="margin:6px 0;width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#fdfbf5;">
        <select id="menus-type" style="margin:6px 0;width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#fdfbf5;">
            <option value="">Tous les types</option>
            <option value="Dejeuner">Dejeuner</option>
            <option value="Gouter">Gouter</option>
            <option value="Diner">Diner</option>
        </select>
    </div>
    <div class="card-grid" id="menus-grid">
        <?php foreach ($menusDisponibles as $menu): ?>
            <div class="card menu-card" data-type="<?= htmlspecialchars($menu['type_repas']) ?>">
                <p class="text-muted"><?= htmlspecialchars($menu['date_menu']) ?></p>
                <h3><?= htmlspecialchars($menu['type_repas']) ?></h3>
                <p><?= htmlspecialchars($menu['description']) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if (!$menusDisponibles): ?>
            <div class="card">Aucun menu disponible.</div>
        <?php endif; ?>
    </div>

    <h2>Mes commandes</h2>
    <div style="margin-bottom:12px;">
        <input id="cmd-search" type="text" placeholder="Rechercher une commande..." style="margin:6px 0;width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#fdfbf5;">
        <select id="cmd-status" style="margin:6px 0;width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#fdfbf5;">
            <option value="">Tous statuts</option>
            <option value="CONFIRMEE">CONFIRMEE</option>
            <option value="SERVIE">SERVIE</option>
            <option value="ANNULEE">ANNULEE</option>
        </select>
    </div>
    <table id="cmd-table">
        <tr>
            <th>Date</th><th>Type</th><th>Description</th><th>Quantite</th><th>Statut</th><th>Actions</th>
        </tr>
        <?php foreach ($commandes as $row): ?>
            <tr data-statut="<?= htmlspecialchars($row['statut'] ?? '') ?>">
                <td><?= htmlspecialchars($row['date_menu'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['type_repas'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['quantite'] ?? '') ?></td>
                <td>
                    <?php $s = $row['statut'] ?? ''; ?>
                    <span class="badge <?= $s === 'CONFIRMEE' ? 'badge-gold' : ($s === 'SERVIE' ? 'badge-neutral' : 'badge-danger') ?>">
                        <?= htmlspecialchars($s) ?>
                    </span>
                </td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id_commande" value="<?= htmlspecialchars($row['id_commande']) ?>">
                        <button type="submit" class="btn" style="background:#e11d48;" <?= ($row['statut'] === 'CONFIRMEE') ? '' : 'disabled' ?>>Annuler</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$commandes): ?>
            <tr><td colspan="6">Aucune commande.</td></tr>
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
            <tr><td>Pas de donnees nutritionnelles.</td></tr>
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
