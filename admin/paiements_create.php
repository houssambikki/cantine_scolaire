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
$beforeSolde = null;
$afterSolde = null;
$beforeTotalPaye = null;
$afterTotalPaye = null;
$beforeTotalAPayer = null;
$afterTotalAPayer = null;

$modes = ['CASH', 'CARTE', 'VIREMENT'];

// Liste eleves pour le select
$eleves = db()->query('SELECT id_eleve, nom, prenom, classe, email FROM eleves ORDER BY nom, prenom')->fetchAll();

// Helpers finance
function fetchFinance(int $idEleve): array {
    $db = db();
    $soldeStmt = $db->prepare('SELECT solde FROM v_solde_eleve WHERE id_eleve = :id');
    $soldeStmt->execute([':id' => $idEleve]);
    $soldeRow = $soldeStmt->fetch();

    $totalPayeStmt = $db->prepare('SELECT total_paye FROM v_total_paye_par_eleve WHERE id_eleve = :id');
    $totalPayeStmt->execute([':id' => $idEleve]);
    $totalPayeRow = $totalPayeStmt->fetch();

    $totalAPayerStmt = $db->prepare('SELECT total_a_payer FROM v_total_a_payer_par_eleve WHERE id_eleve = :id');
    $totalAPayerStmt->execute([':id' => $idEleve]);
    $totalAPayerRow = $totalAPayerStmt->fetch();

    return [
        'solde' => $soldeRow['solde'] ?? 0,
        'total_paye' => $totalPayeRow['total_paye'] ?? 0,
        'total_a_payer' => $totalAPayerRow['total_a_payer'] ?? 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $idEleve = (int)($_POST['id_eleve'] ?? 0);
    $montant = $_POST['montant'] ?? '';
    $mode = $_POST['mode_paiement'] ?? '';

    if ($token !== $csrfToken) {
        $error = "Requete invalide (CSRF).";
    } elseif (!$idEleve) {
        $error = "Eleve introuvable.";
    } elseif (!is_numeric($montant) || (float)$montant <= 0) {
        $error = "Montant invalide (doit être > 0).";
    } elseif (!in_array($mode, $modes, true)) {
        $error = "Mode de paiement invalide.";
    } else {
        // Verifier existence eleve
        $stmt = db()->prepare('SELECT id_eleve FROM eleves WHERE id_eleve = ?');
        $stmt->execute([$idEleve]);
        $eleveExists = $stmt->fetch();
        if (!$eleveExists) {
            $error = "Eleve introuvable.";
        } else {
            // Finance avant
            $before = fetchFinance($idEleve);
            $beforeSolde = $before['solde'];
            $beforeTotalPaye = $before['total_paye'];
            $beforeTotalAPayer = $before['total_a_payer'];

            try {
                $insert = db()->prepare('INSERT INTO paiements (id_eleve, montant, mode_paiement) VALUES (:id, :m, :mode)');
                $insert->execute([
                    ':id' => $idEleve,
                    ':m' => $montant,
                    ':mode' => $mode,
                ]);
                $success = "Paiement enregistré avec succès";

                // Finance apres
                $after = fetchFinance($idEleve);
                $afterSolde = $after['solde'];
                $afterTotalPaye = $after['total_paye'];
                $afterTotalAPayer = $after['total_a_payer'];
            } catch (PDOException $e) {
                $error = "Erreur lors de l'enregistrement du paiement.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Enregistrer un paiement</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
    </nav>
    <h1>Enregistrer un paiement</h1>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <label>Elève</label>
        <select name="id_eleve" required>
            <option value="">-- Choisir un élève --</option>
            <?php foreach ($eleves as $eleve): ?>
                <option value="<?= $eleve['id_eleve'] ?>" <?= (isset($idEleve) && (int)$idEleve === (int)$eleve['id_eleve']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['classe'] . ') — ' . $eleve['email']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Montant</label>
        <input type="number" name="montant" min="0.01" step="0.01" value="<?= htmlspecialchars($_POST['montant'] ?? '') ?>" required>

        <label>Mode de paiement</label>
        <select name="mode_paiement" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($modes as $m): ?>
                <option value="<?= $m ?>" <?= (($_POST['mode_paiement'] ?? '') === $m) ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Enregistrer</button>
    </form>

    <?php if ($beforeSolde !== null): ?>
        <h2>Résumé financier</h2>
        <table>
            <tr><th></th><th>Avant paiement</th><th>Après paiement</th></tr>
            <tr><td>Total à payer</td><td><?= htmlspecialchars($beforeTotalAPayer) ?></td><td><?= htmlspecialchars($afterTotalAPayer) ?></td></tr>
            <tr><td>Total payé</td><td><?= htmlspecialchars($beforeTotalPaye) ?></td><td><?= htmlspecialchars($afterTotalPaye) ?></td></tr>
            <tr><td>Solde</td><td><?= htmlspecialchars($beforeSolde) ?></td><td><?= htmlspecialchars($afterSolde) ?></td></tr>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
