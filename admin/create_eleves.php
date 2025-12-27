<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $classe = trim($_POST['classe'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($nom && $prenom && $classe && $email && $motDePasse) {
        try {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO eleves (nom, prenom, classe, allergies, email, mot_de_passe) VALUES (:n, :p, :c, :a, :e, :m)');
            $stmt->execute([
                ':n' => $nom,
                ':p' => $prenom,
                ':c' => $classe,
                ':a' => $allergies,
                ':e' => $email,
                ':m' => $hash,
            ]);
            $success = "Eleve cree avec succes.";
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Creer un eleve</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>
    <h1>Creer un eleve</h1>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <label>Nom</label>
        <input type="text" name="nom" required>

        <label>Prenom</label>
        <input type="text" name="prenom" required>

        <label>Classe</label>
        <input type="text" name="classe" required>

        <label>Allergies (optionnel)</label>
        <input type="text" name="allergies">

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Mot de passe</label>
        <input type="password" name="mot_de_passe" required>

        <button type="submit">Creer l'eleve</button>
    </form>
</div>
</body>
</html>
