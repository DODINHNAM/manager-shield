<?php
require_once __DIR__ . '/../layout_header.php';
$webshield = $data['webshield'] ?? null;
$config = $data['config'] ?? null;
?>
<h3>Cấu hình thanh toán cho: <?=htmlspecialchars($webshield['name'])?></h3>

<form method="post" action="index.php?action=manager_save_payment">
  <input type="hidden" name="web_shield_id" value="<?= $webshield['id'] ?>">
  <div class="mb-3">
    <label>Type</label>
    <select name="type" class="form-control">
      <option value="paypal" <?=($config && $config['type']=='paypal')?'selected':''?>>PayPal</option>
      <option value="stripe" <?=($config && $config['type']=='stripe')?'selected':''?>>Stripe</option>
    </select>
  </div>
  <div class="mb-3">
    <label>Environment</label>
    <select name="environment" class="form-control">
      <option value="sandbox" <?=($config && $config['environment']=='sandbox')?'selected':''?>>Sandbox</option>
      <option value="live" <?=($config && $config['environment']=='live')?'selected':''?>>Live</option>
    </select>
  </div>
  <div class="mb-3">
    <label>Client ID</label>
    <input name="client_id" class="form-control" value="<?=htmlspecialchars($config['client_id'] ?? '')?>">
  </div>
  <div class="mb-3">
    <label>Secret ID</label>
    <input name="secret_id" class="form-control" value="<?=htmlspecialchars($config['secret_id'] ?? '')?>">
  </div>
  <button class="btn btn-primary">Lưu</button>
</form>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
