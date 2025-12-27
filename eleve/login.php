<?php
require_once __DIR__ . '/../lib/auth.php';
ensure_session();
redirect_if_logged_in();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    $stmt = db()->prepare('SELECT id_eleve, mot_de_passe FROM eleves WHERE email = ?');
    $stmt->execute([$email]);
    $eleve = $stmt->fetch();

    if ($eleve && password_verify($password, $eleve['mot_de_passe'])) {
        $_SESSION['role'] = 'eleve';
        $_SESSION['id_eleve'] = $eleve['id_eleve'];
        header('Location: /cantine_scolaire/eleve/dashboard.php');
        exit;
    } else {
        $error = "Identifiants invalides.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Login Élève</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <h1>Connexion Élève</h1>
    <a href="/cantine_scolaire/index.php">Retour</a>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Mot de passe</label>
        <input type="password" name="mot_de_passe" required>
        <button type="submit">Se connecter</button>
    </form>
</div>
</body>
</html>
