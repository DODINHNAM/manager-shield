<?php require_once __DIR__ . '/../layout_header.php'; ?>
<div class="card">
    <h2>Choose a Web Shield</h2>
    <p>Whitelist entries are configured separately for each shield.</p>
    <table class="data-table">
        <thead><tr><th>ID</th><th>Shield</th><th>Domain</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach (($data['webshields'] ?? []) as $shield): ?>
            <tr><td><?= htmlspecialchars($shield['id']) ?></td><td><?= htmlspecialchars($shield['name']) ?></td><td><?= htmlspecialchars($shield['domain']) ?></td><td><a class="btn btn-sm btn-primary" href="index.php?action=manager_webshield_whitelist&web_id=<?= $shield['id'] ?>">Configure whitelist</a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
