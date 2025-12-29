<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');
ensure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$success = null;
$error = null;

// Actions admin : marquer servie / annuler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $idCommande = (int)($_POST['id_commande'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($token !== $csrfToken || !$idCommande) {
        $error = "Requete invalide.";
    } elseif (!in_array($action, ['serve', 'cancel'], true)) {
        $error = "Action inconnue.";
    } else {
        // Vérifier statut actuel
        $stmt = db()->prepare('SELECT statut FROM commandes WHERE id_commande = ?');
        $stmt->execute([$idCommande]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = "Commande introuvable.";
        } elseif ($row['statut'] !== 'CONFIRMEE') {
            $error = "Action autorisee uniquement sur une commande CONFIRMEE.";
        } else {
            if ($action === 'serve') {
                $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id');
                $up->execute([':s' => 'SERVIE', ':id' => $idCommande]);
                $success = "Commande marquee SERVIE.";
            } else {
                $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id');
                $up->execute([':s' => 'ANNULEE', ':id' => $idCommande]);
                $success = "Commande annulee.";
            }
        }
    }
}

// Filtres
$q = trim($_GET['q'] ?? '');
$statut = $_GET['statut'] ?? '';
$typeRepas = $_GET['type_repas'] ?? '';
$dateExacte = $_GET['date_exacte'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';
$qteMin = $_GET['qte_min'] ?? '';
$qteMax = $_GET['qte_max'] ?? '';
$orderBy = $_GET['order_by'] ?? 'date_menu';
$orderDir = strtoupper($_GET['order_dir'] ?? 'DESC');

$orderWhitelist = [
    'eleve' => 'e.nom, e.prenom',
    'date_menu' => 'm.date_menu',
    'type_repas' => 'm.type_repas',
    'quantite' => 'c.quantite',
    'statut' => 'c.statut',
];
$orderDirWhitelist = ['ASC', 'DESC'];

$orderSql = $orderWhitelist[$orderBy] ?? $orderWhitelist['date_menu'];
$orderDirSql = in_array($orderDir, $orderDirWhitelist, true) ? $orderDir : 'DESC';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(e.nom LIKE :q OR e.prenom LIKE :q OR e.email LIKE :q OR m.description LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$statuts = ['CONFIRMEE', 'ANNULEE', 'SERVIE'];
if ($statut !== '' && in_array($statut, $statuts, true)) {
    $where[] = 'c.statut = :statut';
    $params[':statut'] = $statut;
}

$types = ['Dejeuner', 'Gouter', 'Diner'];
if ($typeRepas !== '' && in_array($typeRepas, $types, true)) {
    $where[] = 'm.type_repas = :type';
    $params[':type'] = $typeRepas;
}

if ($dateExacte !== '') {
    $where[] = 'm.date_menu = :d_exacte';
    $params[':d_exacte'] = $dateExacte;
} else {
    if ($dateDebut !== '') {
        $where[] = 'm.date_menu >= :d_debut';
        $params[':d_debut'] = $dateDebut;
    }
    if ($dateFin !== '') {
        $where[] = 'm.date_menu <= :d_fin';
        $params[':d_fin'] = $dateFin;
    }
}

if ($qteMin !== '' && is_numeric($qteMin)) {
    $where[] = 'c.quantite >= :qmin';
    $params[':qmin'] = (int)$qteMin;
}
if ($qteMax !== '' && is_numeric($qteMax)) {
    $where[] = 'c.quantite <= :qmax';
    $params[':qmax'] = (int)$qteMax;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        c.id_commande,
        c.quantite,
        c.statut,
        e.nom,
        e.prenom,
        e.classe,
        e.email,
        m.date_menu,
        m.type_repas
    FROM commandes c
    JOIN eleves e ON c.id_eleve = e.id_eleve
    JOIN menus m ON c.id_menu = m.id_menu
    $whereSql
    ORDER BY $orderSql $orderDirSql, e.nom ASC
";

$stmt = db()->prepare($sql);
foreach ($params as $key => $val) {
    if (in_array($key, [':qmin', ':qmax'], true)) {
        $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$stmt->execute();
$commandes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des commandes</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>

    <h1>Gestion des commandes</h1>
    <p class="text-muted">Recherche eleve/menu, filtres statut/type/date/quantite, actions via CSRF.</p>

    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="get">
        <label>Recherche (nom, prenom, email, description)</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>">

        <label>Statut</label>
        <select name="statut">
            <option value="">Tous</option>
            <?php foreach ($statuts as $st): ?>
                <option value="<?= $st ?>" <?= $statut === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>

        <label>Type de repas</label>
        <select name="type_repas">
            <option value="">Tous</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= $typeRepas === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>

        <label>Date exacte</label>
        <input type="date" name="date_exacte" value="<?= htmlspecialchars($dateExacte) ?>">

        <label>Date debut</label>
        <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>">

        <label>Date fin</label>
        <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>">

        <label>Quantite min</label>
        <input type="number" name="qte_min" min="1" value="<?= htmlspecialchars($qteMin) ?>">

        <label>Quantite max</label>
        <input type="number" name="qte_max" min="1" value="<?= htmlspecialchars($qteMax) ?>">

        <label>Tri</label>
        <select name="order_by">
            <option value="date_menu" <?= $orderBy === 'date_menu' ? 'selected' : '' ?>>Date</option>
            <option value="eleve" <?= $orderBy === 'eleve' ? 'selected' : '' ?>>Eleve</option>
            <option value="type_repas" <?= $orderBy === 'type_repas' ? 'selected' : '' ?>>Type repas</option>
            <option value="quantite" <?= $orderBy === 'quantite' ? 'selected' : '' ?>>Quantite</option>
            <option value="statut" <?= $orderBy === 'statut' ? 'selected' : '' ?>>Statut</option>
        </select>
        <select name="order_dir">
            <option value="DESC" <?= $orderDirSql === 'DESC' ? 'selected' : '' ?>>DESC</option>
            <option value="ASC" <?= $orderDirSql === 'ASC' ? 'selected' : '' ?>>ASC</option>
        </select>

        <button type="submit">Filtrer</button>
    </form>

    <h2>Commandes</h2>
    <table>
        <tr>
            <th>id_commande</th><th>Eleve</th><th>Classe</th><th>Menu</th><th>Quantite</th><th>Statut</th><th>Actions</th>
        </tr>
        <?php foreach ($commandes as $cmd): ?>
            <tr>
                <td><?= htmlspecialchars($cmd['id_commande']) ?></td>
                <td><?= htmlspecialchars($cmd['nom'] . ' ' . $cmd['prenom']) ?></td>
                <td><?= htmlspecialchars($cmd['classe']) ?></td>
                <td><?= htmlspecialchars($cmd['date_menu'] . ' — ' . $cmd['type_repas']) ?></td>
                <td><?= htmlspecialchars($cmd['quantite']) ?></td>
                <td><?= htmlspecialchars($cmd['statut']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="id_commande" value="<?= htmlspecialchars($cmd['id_commande']) ?>">
                        <input type="hidden" name="action" value="serve">
                        <button type="submit" class="btn" <?= $cmd['statut'] === 'CONFIRMEE' ? '' : 'disabled' ?>>Marquer SERVIE</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="id_commande" value="<?= htmlspecialchars($cmd['id_commande']) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn" style="background:#e11d48;" <?= $cmd['statut'] === 'CONFIRMEE' ? '' : 'disabled' ?>>Annuler</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$commandes): ?>
            <tr><td colspan="7">Aucune commande trouvee.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
