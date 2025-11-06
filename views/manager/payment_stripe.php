<?php require_once __DIR__ . '/../layout_header.php';
$config = $data['config'] ?? [];
$wsp = $data['wsp'] ?? null;
?>
<div class="card">
  <h3>Stripe Config (<?=htmlspecialchars($wsp['payment_name'])?>)</h3>
  <form method="post" action="index.php?action=manager_save_payment&wsp_id=<?=$wsp['id']?>">
    <div class="mb-3"><label>API Key</label><input name="api_key" class="form-control" value="<?=htmlspecialchars($config['api_key'] ?? '')?>"></div>
    <div class="mb-3"><label>Publishable Key</label><input name="publishable_key" class="form-control" value="<?=htmlspecialchars($config['publishable_key'] ?? '')?>"></div>
    <button class="btn btn-primary">Save</button>
  </form>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
