<?php
require_once __DIR__ . '/../lib/auth.php';
ensure_session();
redirect_if_logged_in();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    $stmt = db()->prepare('SELECT id_admin, mot_de_passe FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['mot_de_passe'])) {
        $_SESSION['role'] = 'admin';
        $_SESSION['id_admin'] = $admin['id_admin'];
        header('Location: /cantine_scolaire/admin/dashboard.php');
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
    <title>Login Admin</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <h1>Connexion Admin</h1>
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
