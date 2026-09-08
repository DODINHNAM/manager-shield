<?php require_once __DIR__ . '/../layout_header.php';
$types = $data['types'] ?? [];
$globalRows = $data['global'] ?? [];
$localRows = $data['local'] ?? [];
$selected = $data['selected'] ?? null;
$managerId = (int) ($data['manager_id'] ?? 0);

$values = static function ($rows, $type) {
    return implode(",\n", array_map(static function ($row) { return $row['rule_value']; }, array_filter($rows, static function ($row) use ($type) { return $row['rule_type'] === $type; })));
};
$isEnabled = static function ($rows, $type) {
    foreach ($rows as $row) if ($row['rule_type'] === $type && (int) $row['active'] === 1) return true;
    return false;
};
?>
<div class="page-intro">
  <div class="eyebrow">TOOLS</div>
  <h2>Restrictions</h2>
  <p>Control which customers and merchant domains can use your PayPal shields.</p>
</div>

<div class="restriction-grid">
  <section class="card restriction-card">
    <div class="section-heading"><div><h3>Global restrictions</h3><span class="muted">Applied to all shields owned by one manager.</span></div></div>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
      <form method="get" class="restriction-shield-picker">
        <input type="hidden" name="action" value="restrictions">
        <label for="restriction-manager">Manager account</label>
        <select id="restriction-manager" name="manager_id" class="form-control" onchange="this.form.submit()">
          <?php foreach (($data['managers'] ?? []) as $manager): ?><option value="<?= (int) $manager['id'] ?>" <?= (int) $manager['id'] === $managerId ? 'selected' : '' ?>><?= htmlspecialchars($manager['username']) ?></option><?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
    <form method="post" action="index.php?action=restrictions_save_global">
      <input type="hidden" name="manager_id" value="<?= $managerId ?>">
      <?php foreach ($types as $type => $label): ?>
        <div class="restriction-field">
          <div class="restriction-label-row"><label for="global-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($label) ?></label><label class="restriction-switch"><input type="checkbox" name="global_enabled[<?= htmlspecialchars($type) ?>]" value="1" <?= $isEnabled($globalRows, $type) ? 'checked' : '' ?>><span>Enabled</span></label></div>
          <textarea id="global-<?= htmlspecialchars($type) ?>" name="global_<?= htmlspecialchars($type) ?>" class="form-control" rows="3" placeholder="Values separated by comma or new line"><?= htmlspecialchars($values($globalRows, $type)) ?></textarea>
        </div>
      <?php endforeach; ?>
      <div class="restriction-actions"><button class="btn btn-primary" type="submit">Save global rules</button></div>
    </form>
  </section>

  <section class="card restriction-card">
    <div class="section-heading"><div><h3>Local restrictions</h3><span class="muted">Applied only to the selected shield.</span></div></div>
    <form method="get" class="restriction-shield-picker">
      <input type="hidden" name="action" value="restrictions">
      <label for="restriction-shield">Shield</label>
      <select id="restriction-shield" name="shield_id" class="form-control" onchange="this.form.submit()">
        <?php foreach (($data['webshields'] ?? []) as $shield): ?><option value="<?= (int) $shield['id'] ?>" <?= $selected && (int) $selected['id'] === (int) $shield['id'] ? 'selected' : '' ?>><?= htmlspecialchars($shield['name']) ?> · <?= htmlspecialchars($shield['domain']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($selected): ?>
      <form method="post" action="index.php?action=restrictions_save_local&shield_id=<?= (int) $selected['id'] ?>">
        <?php foreach ($types as $type => $label): ?>
          <div class="restriction-field">
            <div class="restriction-label-row"><label for="local-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($label) ?></label><label class="restriction-switch"><input type="checkbox" name="local_enabled[<?= htmlspecialchars($type) ?>]" value="1" <?= $isEnabled($localRows, $type) ? 'checked' : '' ?>><span>Enabled</span></label></div>
            <textarea id="local-<?= htmlspecialchars($type) ?>" name="local_<?= htmlspecialchars($type) ?>" class="form-control" rows="3" placeholder="Values separated by comma or new line"><?= htmlspecialchars($values($localRows, $type)) ?></textarea>
          </div>
        <?php endforeach; ?>
        <div class="restriction-actions"><button class="btn btn-primary" type="submit">Save local rules</button></div>
      </form>
    <?php else: ?>
      <div class="empty-state">Create or assign a shield before adding local restrictions.</div>
    <?php endif; ?>
  </section>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
