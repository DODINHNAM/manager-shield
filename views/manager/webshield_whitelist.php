<?php require_once __DIR__ . '/../layout_header.php';
$shield = $data['webshield'];
$domains = $data['domains'] ?? [];
$admin = !empty($data['is_admin']);
?>
<div class="card">
    <h2>Whitelist: <?= htmlspecialchars($shield['name']) ?></h2>
    <p>Only these merchant domains can use this shield.</p>
    <form method="post" action="index.php?action=<?= $admin ? 'admin_webshield_whitelist_add' : 'manager_webshield_whitelist_add' ?>&web_id=<?= $shield['id'] ?>">
        <input name="domain" class="form-control" placeholder="store.example.com" required>
        <button type="submit" class="btn btn-success mt-3">Add domain</button>
    </form>
    <table class="data-table mt-3">
        <thead><tr><th>ID</th><th>Domain</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($domains as $domain): ?>
            <tr>
                <td><?= htmlspecialchars($domain['id']) ?></td>
                <td><?= htmlspecialchars($domain['domain']) ?></td>
                <td><?= $domain['active'] ? 'Active' : 'Disabled' ?></td>
                <td><a class="btn btn-sm btn-danger" href="index.php?action=<?= $admin ? 'admin_webshield_whitelist_delete' : 'manager_webshield_whitelist_delete' ?>&id=<?= $domain['id'] ?>&web_id=<?= $shield['id'] ?>">Delete</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
