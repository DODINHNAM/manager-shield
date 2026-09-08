<?php require_once __DIR__ . '/../layout_header.php';
$configs = $data['configs'] ?? [];
$webshields = $data['webshields'] ?? [];
$newToken = $_SESSION['endpoint_rotation_token'] ?? null;
$newSecret = $_SESSION['endpoint_rotation_secret'] ?? null;
$newGateway = $_SESSION['endpoint_rotation_gateway'] ?? null;
unset($_SESSION['endpoint_rotation_token']);
unset($_SESSION['endpoint_rotation_secret'], $_SESSION['endpoint_rotation_gateway']);
?>
<div class="endpoint-page-heading">
  <div>
    <div class="eyebrow">PAYMENT ROUTING</div>
    <h2>Endpoint rotation</h2>
    <p class="muted">Group shields into private endpoint collections and rotate traffic safely.</p>
  </div>
  <button class="btn btn-primary" type="button" id="open-endpoint-config">New endpoint config</button>
</div>

<?php if ($newToken): ?>
  <div class="endpoint-token-notice" role="status">
    <div class="endpoint-token-icon">L</div>
    <div>
      <strong>Endpoint config created</strong>
      <p>Copy both values into CardsShield Gateway PayPal. They are shown only once.</p>
      <div class="endpoint-credential"><span>Manager endpoint</span><code><?= htmlspecialchars($newGateway) ?></code></div>
      <div class="endpoint-credential"><span>Endpoint token</span><code><?= htmlspecialchars($newToken) ?></code></div>
      <div class="endpoint-credential"><span>Secret key</span><code><?= htmlspecialchars($newSecret) ?></code></div>
    </div>
  </div>
<?php endif; ?>

<div class="endpoint-summary-grid">
  <div class="endpoint-summary-card"><span class="endpoint-summary-label">Collections</span><strong><?= count($configs) ?></strong><small>Endpoint configs</small></div>
  <div class="endpoint-summary-card"><span class="endpoint-summary-label">Available shields</span><strong><?= count($webshields) ?></strong><small>Shields you can assign</small></div>
  <div class="endpoint-summary-card endpoint-summary-accent"><span class="endpoint-summary-label">Whitelist aware</span><strong>ON</strong><small>Per-shield merchant checks</small></div>
</div>

<div class="card endpoint-configs-card">
  <div class="section-heading">
    <div><h3>Your endpoint collections</h3><span class="muted">Each token maps to one independent shield rotation.</span></div>
    <button class="btn btn-info btn-sm" type="button" id="open-endpoint-config-secondary">+ Add collection</button>
  </div>
  <div class="table-scroll">
    <table class="data-table endpoint-config-table">
    <thead><tr><th>Collection</th><th>Provider</th><th>Token</th><th>Rotation</th><th>Shields</th><th>Created by</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($configs as $config): ?>
        <tr>
          <td><strong><?= htmlspecialchars($config['name']) ?></strong><small class="table-subtext">#<?= (int) $config['id'] ?></small></td>
          <td><span class="endpoint-provider-badge provider-<?= htmlspecialchars($config['payment_provider']) ?>"><?= strtoupper(htmlspecialchars($config['payment_provider'])) ?></span></td>
          <td><code><?= htmlspecialchars($config['token_preview']) ?>...</code></td>
          <td><span class="endpoint-method-badge"><?= $config['rotation_method'] === 'by_amount' ? 'Amount / day' : 'Time' ?></span></td>
          <td><?= (int) $config['member_count'] ?></td>
          <td><?= htmlspecialchars($config['creator_name'] ?? '') ?></td>
          <td class="endpoint-actions">
            <?php if (($user['role'] ?? '') === 'admin'): ?>
              <a class="btn btn-sm btn-info" href="index.php?action=endpoint_rotation_keys&id=<?= (int) $config['id'] ?>">View keys</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-danger" href="index.php?action=endpoint_rotation_delete&id=<?= (int) $config['id'] ?>" onclick="return confirm('Delete this endpoint config?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="endpointRotationModal" class="modal endpoint-modal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="eyebrow">NEW COLLECTION</div>
      <h4>Configure endpoint rotation</h4>
      <button type="button" class="close endpoint-modal-close" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <form method="post" action="index.php?action=endpoint_rotation_save" class="endpoint-rotation-form">
        <div class="endpoint-form-intro"><strong>Start with the basics</strong><span>Choose how this token will move between shields.</span></div>
        <div class="endpoint-form-grid">
          <div class="form-group">
            <label class="form-label" for="endpoint-config-name">Config name</label>
            <input id="endpoint-config-name" name="name" class="form-control" placeholder="Main PayPal collection" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="endpoint-payment-provider">Payment provider</label>
            <select id="endpoint-payment-provider" name="payment_provider" class="form-control">
              <option value="paypal">PayPal</option>
              <option value="stripe">Stripe</option>
              <option value="momo">Momo</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="endpoint-rotation-method">Rotation method</label>
            <select id="endpoint-rotation-method" name="rotation_method" class="form-control">
              <option value="by_time">By time (minutes)</option>
              <option value="by_amount">By amount per day</option>
            </select>
          </div>
        </div>
        <div class="endpoint-collection-heading"><div><strong>Shield collection</strong><span>Select the shields that can serve this endpoint.</span></div><span><?= count($webshields) ?> available</span></div>
        <div class="endpoint-shield-list">
          <?php foreach ($webshields as $shield): ?>
            <label class="endpoint-shield-row">
              <input type="checkbox" name="shield_id[]" value="<?= (int) $shield['id'] ?>">
              <span class="endpoint-shield-check"></span>
              <span class="endpoint-shield-info"><strong><?= htmlspecialchars($shield['name']) ?></strong><small><?= htmlspecialchars($shield['domain'] ?? '') ?></small></span>
              <span class="endpoint-value-wrap"><input type="number" name="rotation_value[<?= (int) $shield['id'] ?>]" class="form-control" min="0.01" step="0.01" value="60" aria-label="Rotation value for <?= htmlspecialchars($shield['name']) ?>"><small class="endpoint-value-unit">value</small></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="endpoint-modal-actions"><button type="button" class="btn btn-secondary endpoint-modal-close">Cancel</button><button class="btn btn-primary" type="submit">Create endpoint config</button></div>
      </form>
    </div>
  </div>
</div>
<script src="/assets/js/endpoint_rotation.js?v=<?= filemtime(__DIR__ . '/../../assets/js/endpoint_rotation.js') ?>"></script>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
