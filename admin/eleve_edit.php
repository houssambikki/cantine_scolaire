<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');
ensure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$idEleve = (int)($_GET['id_eleve'] ?? 0);
$error = null;
$success = null;

$allowedClasses = ['GI', 'IID', 'GE', 'IRIC', 'GP'];

// Charger l'eleve
if ($idEleve > 0) {
    $stmt = db()->prepare('SELECT * FROM eleves WHERE id_eleve = ?');
    $stmt->execute([$idEleve]);
    $eleve = $stmt->fetch();
    if (!$eleve) {
        $error = "Eleve introuvable.";
    }
} else {
    $error = "Identifiant d'eleve manquant.";
    $eleve = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token !== $csrfToken) {
        $error = "Token CSRF invalide.";
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $classe = $_POST['classe'] ?? '';
        $allergies = trim($_POST['allergies'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (!$nom || !$prenom || !$classe || !$email) {
            $error = "Nom, prenom, classe et email sont obligatoires.";
        } elseif (!in_array($classe, $allowedClasses, true)) {
            $error = "Classe invalide.";
        } else {
            try {
                if ($motDePasse !== '') {
                    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                    $stmt = db()->prepare('
                        UPDATE eleves
                        SET nom = :n, prenom = :p, classe = :c, allergies = :a, email = :e, mot_de_passe = :m
                        WHERE id_eleve = :id
                    ');
                    $stmt->execute([
                        ':n' => $nom,
                        ':p' => $prenom,
                        ':c' => $classe,
                        ':a' => $allergies,
                        ':e' => $email,
                        ':m' => $hash,
                        ':id' => $idEleve,
                    ]);
                } else {
                    $stmt = db()->prepare('
                        UPDATE eleves
                        SET nom = :n, prenom = :p, classe = :c, allergies = :a, email = :e
                        WHERE id_eleve = :id
                    ');
                    $stmt->execute([
                        ':n' => $nom,
                        ':p' => $prenom,
                        ':c' => $classe,
                        ':a' => $allergies,
                        ':e' => $email,
                        ':id' => $idEleve,
                    ]);
                }
                $success = "Eleve mis a jour.";
                // rafraichir
                $eleve = [
                    'id_eleve' => $idEleve,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'classe' => $classe,
                    'allergies' => $allergies,
                    'email' => $email,
                ];
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise a jour : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un eleve</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/eleve_management.php">Retour gestion eleves</a>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>

    <h1>Modifier un eleve</h1>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($eleve): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <label>Nom</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($eleve['nom'] ?? '') ?>" required>

            <label>Prenom</label>
            <input type="text" name="prenom" value="<?= htmlspecialchars($eleve['prenom'] ?? '') ?>" required>

            <label>Classe</label>
            <select name="classe" required>
                <?php foreach ($allowedClasses as $c): ?>
                    <option value="<?= $c ?>" <?= ($eleve['classe'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>

            <label>Allergies</label>
            <input type="text" name="allergies" value="<?= htmlspecialchars($eleve['allergies'] ?? '') ?>">

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($eleve['email'] ?? '') ?>" required>

            <label>Nouveau mot de passe (laisser vide pour conserver)</label>
            <input type="password" name="mot_de_passe">

            <button type="submit">Enregistrer les modifications</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
