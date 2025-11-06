<?php require_once __DIR__ . '/../layout_header.php';
$webshields = $data['webshields'] ?? [];
$managers = $data['managers'] ?? [];
$payment_types = $data['payment_types'] ?? [];
?>
<h3>Web Shields</h3>

<div class="card p-3 mb-3">
  <form method="post" action="index.php?action=admin_create_webshield">
    <div class="row g-2">
      <div class="col-md-4"><input name="name" placeholder="name" class="form-control" required></div>
      <div class="col-md-4"><input name="domain" placeholder="domain" class="form-control"></div>
      <div class="col-md-3">
        <select name="manager_id" class="form-control">
          <option value="">-- Chọn manager --</option>
          <?php foreach($managers as $m): ?>
            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['username'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-success">Tạo</button></div>
    </div>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Name</th><th>Domain</th><th>Manager</th><th>Payments</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($webshields as $w): ?>
        <tr>
          <td><?=$w['id']?></td>
          <td><?=htmlspecialchars($w['name'])?></td>
          <td><?=htmlspecialchars($w['domain'])?></td>
          <td><?=htmlspecialchars($w['manager_name'] ?? '—')?></td>
          <td>
            <form method="post" action="index.php?action=admin_attach_payment" class="d-flex">
              <input type="hidden" name="web_shield_id" value="<?=$w['id']?>">
              <select name="payment_type_id" class="form-control form-select me-2">
                <?php foreach($payment_types as $pt): ?>
                  <option value="<?=$pt['id']?>"><?=htmlspecialchars($pt['name'])?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-primary">Attach</button>
            </form>
            <ul class="mt-2">
              <?php
                $payments = [];
                foreach(db_query("SELECT wsp.id, pt.name, pt.code FROM web_shield_payments wsp JOIN payment_types pt ON pt.id = wsp.payment_type_id WHERE wsp.web_shield_id = ?", [$w['id']]) as $p) {
                  echo "<li>{$p['name']} <a href='index.php?action=admin_detach_payment&id={$p['id']}' class='btn btn-sm btn-danger ms-2'>Detach</a></li>";
                }
              ?>
            </ul>
          </td>
          <td>
            <a href="index.php?action=admin_delete_webshield&id=<?=$w['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa?')">Xóa</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div><?php require_once __DIR__ . '/../layout_footer.php'; ?>
