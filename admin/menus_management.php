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

// Suppression sécurisée via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $idMenu = (int)($_POST['id_menu'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    if (!$idMenu || $token !== $csrfToken) {
        $error = "Requete invalide (token ou identifiant manquant).";
    } else {
        try {
            $stmt = db()->prepare('DELETE FROM menus WHERE id_menu = ?');
            $stmt->execute([$idMenu]);
            $success = "Menu supprime.";
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = "Suppression impossible : ce menu est lie a des commandes. Annulez/retirez les commandes ou modifiez le statut.";
            } else {
                $error = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
    }
}

// Filtres
$q = trim($_GET['q'] ?? '');
$dateExacte = $_GET['date_exacte'] ?? '';
$typeRepas = $_GET['type_repas'] ?? '';
$calMin = $_GET['calories_min'] ?? '';
$calMax = $_GET['calories_max'] ?? '';
$orderBy = $_GET['order_by'] ?? 'date_menu';
$orderDir = strtoupper($_GET['order_dir'] ?? 'ASC');

$orderByWhitelist = ['date_menu', 'type_repas', 'description', 'calories_total'];
$orderDirWhitelist = ['ASC', 'DESC'];

if (!in_array($orderBy, $orderByWhitelist, true)) {
    $orderBy = 'date_menu';
}
if (!in_array($orderDir, $orderDirWhitelist, true)) {
    $orderDir = 'ASC';
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(description LIKE :q OR allergenes LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

if ($dateExacte !== '') {
    $where[] = 'date_menu = :d';
    $params[':d'] = $dateExacte;
}

$typeOptions = ['Dejeuner', 'Gouter', 'Diner'];
if ($typeRepas !== '' && in_array($typeRepas, $typeOptions, true)) {
    $where[] = 'type_repas = :t';
    $params[':t'] = $typeRepas;
}

if ($calMin !== '' && is_numeric($calMin)) {
    $where[] = 'calories_total >= :cmin';
    $params[':cmin'] = (int)$calMin;
}

if ($calMax !== '' && is_numeric($calMax)) {
    $where[] = 'calories_total <= :cmax';
    $params[':cmax'] = (int)$calMax;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT id_menu, date_menu, type_repas, description, calories_total, allergenes
    FROM menus
    $whereSql
    ORDER BY $orderBy $orderDir, type_repas ASC
";

$stmt = db()->prepare($sql);
foreach ($params as $key => $val) {
    // Calories sont bind en int sinon string
    if (in_array($key, [':cmin', ':cmax'], true)) {
        $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$stmt->execute();
$menus = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des menus</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
    <script>
        function confirmDelete(form) {
            if (confirm('Supprimer ce menu ?')) {
                form.submit();
            }
            return false;
        }
    </script>
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/admin/menus_create.php">Ajouter un menu</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>

    <h1>Gestion des menus</h1>
    <p class="text-muted">Recherche par description/allergenes, filtre date/type/calories, tri controle.</p>

    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="get">
        <label>Recherche (description, allergenes)</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Texte...">

        <label>Date exacte</label>
        <input type="date" name="date_exacte" value="<?= htmlspecialchars($dateExacte) ?>">

        <label>Type de repas</label>
        <select name="type_repas">
            <option value="">Tous</option>
            <?php foreach ($typeOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $typeRepas === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>

        <label>Calories min</label>
        <input type="number" name="calories_min" value="<?= htmlspecialchars($calMin) ?>" min="0" step="1">

        <label>Calories max</label>
        <input type="number" name="calories_max" value="<?= htmlspecialchars($calMax) ?>" min="0" step="1">

        <label>Tri</label>
        <select name="order_by">
            <option value="date_menu" <?= $orderBy === 'date_menu' ? 'selected' : '' ?>>Date</option>
            <option value="type_repas" <?= $orderBy === 'type_repas' ? 'selected' : '' ?>>Type repas</option>
            <option value="description" <?= $orderBy === 'description' ? 'selected' : '' ?>>Description</option>
            <option value="calories_total" <?= $orderBy === 'calories_total' ? 'selected' : '' ?>>Calories</option>
        </select>
        <select name="order_dir">
            <option value="ASC" <?= $orderDir === 'ASC' ? 'selected' : '' ?>>ASC</option>
            <option value="DESC" <?= $orderDir === 'DESC' ? 'selected' : '' ?>>DESC</option>
        </select>

        <button type="submit">Filtrer</button>
    </form>

    <h2>Liste des menus</h2>
    <table>
        <tr>
            <th>id_menu</th><th>Date</th><th>Type</th><th>Description</th><th>Calories</th><th>Allergenes</th><th>Actions</th>
        </tr>
        <?php foreach ($menus as $menu): ?>
            <tr>
                <td><?= htmlspecialchars($menu['id_menu']) ?></td>
                <td><?= htmlspecialchars($menu['date_menu']) ?></td>
                <td><?= htmlspecialchars($menu['type_repas']) ?></td>
                <td><?= htmlspecialchars($menu['description']) ?></td>
                <td><?= htmlspecialchars($menu['calories_total']) ?></td>
                <td><?= htmlspecialchars($menu['allergenes']) ?></td>
                <td>
                    <a class="btn" href="/cantine_scolaire/admin/menus_edit.php?id_menu=<?= urlencode($menu['id_menu']) ?>" style="background:#0ea5e9;">Modifier</a>
                    <form method="post" onsubmit="return confirmDelete(this);" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_menu" value="<?= htmlspecialchars($menu['id_menu']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn" style="background:#e11d48;">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$menus): ?>
            <tr><td colspan="7">Aucun menu trouve.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
