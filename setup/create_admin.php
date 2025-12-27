<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

ensure_session();

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

            $stmt = db()->prepare(
                'INSERT INTO admins (email, mot_de_passe) VALUES (?, ?)'
            );
            $stmt->execute([$email, $hash]);

            $success = "Admin créé avec succès. Supprimez ce fichier après usage.";
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = "Cet email admin existe déjà.";
            } else {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création Admin (Setup)</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <h1>Création du premier administrateur</h1>

    <div class="alert">
        Page de configuration temporaire.  
        À supprimer après la création du premier admin.
    </div>

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

        <button type="submit">Créer l’admin</button>
    </form>

    <p><a href="/cantine_scolaire/index.php">Retour</a></p>
</div>
</body>
</html>
