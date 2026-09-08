<?php require_once __DIR__ . '/../layout_header.php';
$manager = $data['manager'] ?? null;
$domains = $data['domains'] ?? [];
?>

<div class="card">
    <h3>Web Shield whitelist for <?= htmlspecialchars($manager['username']) ?></h3>
    <p>Whitelist entries are configured separately for each shield.</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Domain</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($domains as $domain): ?>
            <tr>
                <td><?= htmlspecialchars($domain['id']) ?></td>
                <td><?= htmlspecialchars($domain['domain']) ?></td>
                <td><a class="btn btn-sm btn-primary" href="index.php?action=admin_webshield_whitelist&web_id=<?= $domain['id'] ?>">Configure whitelist</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
