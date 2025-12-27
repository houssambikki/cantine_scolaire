<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('eleve');

$idEleve = $_SESSION['id_eleve'] ?? 0;
$menus = db()->query('SELECT id_menu, date_menu, type_repas, description FROM menus ORDER BY date_menu')->fetchAll();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMenu = $_POST['id_menu'] ?? null;
    $quantite = $_POST['quantite'] ?? 1;

    try {
        $stmt = db()->prepare('INSERT INTO commandes (id_eleve, id_menu, quantite, statut) VALUES (:e, :m, :q, :s)');
        $stmt->execute([
            ':e' => $idEleve,
            ':m' => $idMenu,
            ':q' => $quantite,
            ':s' => 'en attente',
        ]);
        $success = "Commande enregistrée.";
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Passer une commande</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/eleve/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Déconnexion</a>
    </nav>
    <h1>Passer une commande</h1>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <label>Menu</label>
        <select name="id_menu" required>
            <option value="">-- Choisir un menu --</option>
            <?php foreach ($menus as $menu): ?>
                <option value="<?= $menu['id_menu'] ?>">
                    <?= htmlspecialchars($menu['date_menu'] . ' - ' . $menu['type_repas'] . ' - ' . $menu['description']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Quantité</label>
        <input type="number" name="quantite" value="1" min="1" required>

        <button type="submit">Commander</button>
    </form>
</div>
</body>
</html>
