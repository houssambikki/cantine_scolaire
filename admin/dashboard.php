<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$today = date('Y-m-d');
$filterDate = $_GET['date'] ?? $today;

// Planification des repas (uniquement commandes confirmees et dates futures)
$sqlPlanif = "
  SELECT
    m.date_menu,
    m.type_repas,
    SUM(c.quantite) AS quantite_totale,
    m.description,
    m.calories_total,
    m.allergenes
  FROM commandes c
  JOIN menus m ON c.id_menu = m.id_menu
  WHERE m.date_menu >= CURDATE()
    AND c.statut = 'CONFIRMEE'
";
if (!empty($filterDate)) {
    $sqlPlanif .= " AND m.date_menu = :date_filter";
}
$sqlPlanif .= "
  GROUP BY m.date_menu, m.type_repas, m.description, m.calories_total, m.allergenes
  ORDER BY m.date_menu ASC
";
$stmtPlanif = db()->prepare($sqlPlanif);
if (!empty($filterDate)) {
    $stmtPlanif->bindValue(':date_filter', $filterDate, PDO::PARAM_STR);
}
$stmtPlanif->execute();
$planifications = $stmtPlanif->fetchAll();
$menusAVenir = count($planifications);

// Alertes allergenes du jour : seulement si allergenes menu recoupent allergies eleve
$stmt = db()->prepare("
  SELECT
    m.date_menu,
    CONCAT(e.nom, ' ', e.prenom) AS eleve,
    e.classe,
    m.allergenes AS allergene_menu,
    1 AS alerte
  FROM commandes c
  JOIN eleves e ON c.id_eleve = e.id_eleve
  JOIN menus m ON c.id_menu = m.id_menu
  WHERE c.statut = 'CONFIRMEE'
    AND m.date_menu = CURDATE()
    AND m.allergenes IS NOT NULL AND m.allergenes <> ''
    AND e.allergies IS NOT NULL AND e.allergies <> ''
    AND m.allergenes REGEXP CONCAT('(^|,)[[:space:]]*', REPLACE(e.allergies, ',', '|'), '([[:space:]]*,|$)')
");
$stmt->execute();
$alertes = $stmt->fetchAll();
$alertesCount = count($alertes);

// Soldes financiers (tous les eleves)
$soldeStmt = db()->query('
    SELECT e.nom, e.prenom, e.classe, COALESCE(v.solde, 0) AS solde
    FROM eleves e
    LEFT JOIN v_solde_eleve v ON v.id_eleve = e.id_eleve
    ORDER BY solde DESC, e.nom, e.prenom
');
$soldeRows = $soldeStmt->fetchAll();

// Commandes confirmees (statique)
$cmdCountStmt = db()->query("SELECT COUNT(*) AS total FROM commandes WHERE statut = 'CONFIRMEE'");
$cmdConfirmed = (int)($cmdCountStmt->fetch()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
    <script defer src="/cantine_scolaire/public/app.js"></script>
</head>
<body>
    <div class="container">
        <nav>
            <a class="btn" href="/cantine_scolaire/admin/menus_management.php">Gestion des menus</a>
            <a class="btn" href="/cantine_scolaire/admin/eleve_management.php">Gestion des eleves</a>
            <a class="btn" href="/cantine_scolaire/admin/commandes_management.php">Gestion des commandes</a>
            <a class="btn" href="/cantine_scolaire/admin/paiements_create.php">Enregistrer un paiement</a>
            <a class="btn" href="/cantine_scolaire/admin/create_admins.php">Creer un admin</a>
            <a class="btn" href="/cantine_scolaire/logout.php">Deconnexion</a>
        </nav>

        <div class="hero" style="margin-bottom:16px;">
            <h1>Dashboard Admin</h1>
            <p class="text-muted">Vision globale des menus, commandes et alertes allergenes.</p>
        </div>

        <div class="card-grid">
            <div class="card">
                <p class="text-muted">Menus à venir</p>
                <h2><?= htmlspecialchars($menusAVenir) ?></h2>
            </div>
            <div class="card">
                <p class="text-muted">Commandes confirmées</p>
                <h2><?= htmlspecialchars($cmdConfirmed) ?></h2>
            </div>
            <div class="card">
                <p class="text-muted">Alertes allergenes (aujourd'hui)</p>
                <h2><?= htmlspecialchars($alertesCount) ?></h2>
            </div>
        </div>

        <form method="get">
            <label>Filtrer par date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
            <button type="submit">Filtrer</button>
        </form>
        <input id="filter-global" type="text" placeholder="Rechercher dans les tableaux..." style="margin:12px 0;width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);">

        <h2>Planification des repas</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Quantite</th>
                    <th>Description</th>
                    <th>Calories</th>
                    <th>Allergenes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planifications as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['date_menu']) ?></td>
                        <td><?= htmlspecialchars($p['type_repas']) ?></td>
                        <td><strong><?= (int)$p['quantite_totale'] ?></strong></td>
                        <td><?= htmlspecialchars($p['description']) ?></td>
                        <td><?= htmlspecialchars($p['calories_total']) ?></td>
                        <td><?= htmlspecialchars($p['allergenes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Alertes allergenes (aujourd'hui)</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Eleve</th>
                    <th>Classe</th>
                    <th>Allergene</th>
                    <th>Alerte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alertes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['date_menu']) ?></td>
                        <td><?= htmlspecialchars($a['eleve']) ?></td>
                        <td><?= htmlspecialchars($a['classe']) ?></td>
                        <td><?= htmlspecialchars($a['allergene_menu'] ?: '-') ?></td>
                        <td><?= ((int)$a['alerte'] === 1) ? '1' : '0' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Soldes financiers</h2>
        <table>
            <tr>
                <th>Eleve</th>
                <th>Classe</th>
                <th>Solde</th>
            </tr>
            <?php foreach ($soldeRows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nom'] ?? '') ?> <?= htmlspecialchars($row['prenom'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['classe'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['solde'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$soldeRows): ?>
                <tr>
                    <td colspan="3">Aucun solde trouve.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filter = document.getElementById('filter-global');
    if (!filter) return;
    filter.addEventListener('input', () => {
        const q = filter.value.toLowerCase();
        document.querySelectorAll('table tbody tr').forEach(tr => {
            const text = tr.innerText.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    });

    // Tri simple sur clic d'entête
    document.querySelectorAll('table').forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((th, idx) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.children.length);
                const ascending = th.dataset.sortDir === 'asc' ? false : true;
                rows.sort((a, b) => {
                    const ta = (a.children[idx]?.innerText || '').trim().toLowerCase();
                    const tb = (b.children[idx]?.innerText || '').trim().toLowerCase();
                    // essai de tri numérique si applicable
                    const na = parseFloat(ta.replace(',', '.'));
                    const nb = parseFloat(tb.replace(',', '.'));
                    if (!isNaN(na) && !isNaN(nb)) {
                        return ascending ? na - nb : nb - na;
                    }
                    return ascending ? ta.localeCompare(tb) : tb.localeCompare(ta);
                });
                tbody.innerHTML = '';
                rows.forEach(r => tbody.appendChild(r));
                headers.forEach(h => delete h.dataset.sortDir);
                th.dataset.sortDir = ascending ? 'asc' : 'desc';
            });
        });
    });
});
</script>
