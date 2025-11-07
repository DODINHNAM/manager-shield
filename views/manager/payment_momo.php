<?php require_once __DIR__ . '/../layout_header.php';
$config = $data['config'] ?? [];
$wsp = $data['wsp'] ?? null;
?>
<div class="card">
  <h3>Cấu hình Momo (<?=htmlspecialchars($wsp['payment_name'])?>)</h3>
  <form method="post" action="index.php?action=manager_save_payment&wsp_id=<?=$wsp['id']?>">
    <div class="mb-3"><label>Partner Code</label><input name="partner_code" class="form-control" value="<?=htmlspecialchars($config['partner_code'] ?? '')?>"></div>
    <div class="mb-3"><label>Access Key</label><input name="access_key" class="form-control" value="<?=htmlspecialchars($config['access_key'] ?? '')?>"></div>
    <div class="mb-3"><label>Secret Key</label><input name="secret_key" class="form-control" value="<?=htmlspecialchars($config['secret_key'] ?? '')?>"></div>
    <div class="mb-3"><label>Môi trường</label><select name="environment" class="form-control"><option value="sandbox">Sandbox</option><option value="production">Production</option></select></div>
    <div class="mt-3">
      <button class="btn btn-primary">Lưu</button>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
