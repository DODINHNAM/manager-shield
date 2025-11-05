<?php require_once __DIR__ . '/../layout_header.php';
$config = $data['config'] ?? [];
$wsp = $data['wsp'] ?? null;
?>
<h3>PayPal Config (<?=htmlspecialchars($wsp['payment_name'])?>)</h3>
<form method="post" action="index.php?action=manager_save_payment&wsp_id=<?=$wsp['id']?>">
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
  <button class="btn btn-primary">Save</button>
</form>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
