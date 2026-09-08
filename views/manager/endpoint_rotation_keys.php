<?php require_once __DIR__ . '/../layout_header.php';
$config = $data['config'] ?? [];
$credentials = $data['credentials'] ?? null;
?>
<div class="card endpoint-key-card">
  <div class="section-heading">
    <div>
      <div class="eyebrow">ADMIN ONLY</div>
      <h2>Endpoint credentials</h2>
      <span class="muted"><?= htmlspecialchars($config['name'] ?? '') ?> · <?= strtoupper(htmlspecialchars($config['payment_provider'] ?? '')) ?></span>
    </div>
    <a class="btn btn-secondary" href="index.php?action=endpoint_rotation">Back</a>
  </div>

  <?php if (!$credentials): ?>
    <div class="alert-error">This config was created before encrypted credentials were stored. Create a new config to obtain the full keys.</div>
  <?php else: ?>
    <div class="endpoint-key-grid">
      <div class="endpoint-key-item">
        <span>Manager endpoint</span>
        <code><?= htmlspecialchars($credentials['gateway_domain'] ?? '') ?></code>
      </div>
      <div class="endpoint-key-item">
        <span>Endpoint token</span>
        <code><?= htmlspecialchars($credentials['token'] ?? '') ?></code>
      </div>
      <div class="endpoint-key-item">
        <span>Secret key</span>
        <code><?= htmlspecialchars($credentials['secret'] ?? '') ?></code>
      </div>
    </div>
    <p class="muted endpoint-key-note">These credentials are visible only to administrators.</p>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
