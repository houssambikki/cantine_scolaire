<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

ensure_session();

// ✅ Seul un admin connecté peut créer d'autres admins
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /cantine_scolaire/admin/login.php');
    exit;
}

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = db()->prepare('INSERT INTO admins (email, mot_de_passe) VALUES (?, ?)');
            $stmt->execute([$email, $hash]);

            $success = "Admin créé avec succès.";
        } catch (Exception $e) {
            // erreur email déjà existant (UNIQUE) par ex
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un Admin</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <h2>Créer un nouvel Admin</h2>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Mot de passe</label>
        <input type="password" name="mot_de_passe" required>

        <button type="submit">Créer</button>
    </form>

    <p><a href="/cantine_scolaire/admin/dashboard.php">Retour au dashboard</a></p>
</div>
</body>
</html>
