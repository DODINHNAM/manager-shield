<?php require_once __DIR__ . '/../layout_header.php';
$webshields = $data['webshields'] ?? [];
$managers = $data['managers'] ?? [];
$payment_types = $data['payment_types'] ?? [];
?>
<h3>Web Shields</h3>

<div class="card p-3 mb-3">
  <h4>Add Web Shield</h4>
  <form method="post" action="index.php?action=admin_create_webshield">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" placeholder="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Domain</label>
      <input name="domain" placeholder="domain" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Manager</label>
      <select name="manager_id" class="form-control" required>
        <option value="">-- Chọn manager --</option>
        <?php foreach($managers as $m): ?>
          <option value="<?=$m['id']?>"><?=htmlspecialchars($m['username'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-success">Tạo</button>
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
            <?php
              $payments = db_query("SELECT pt.name FROM web_shield_payments wsp JOIN payment_types pt ON pt.id = wsp.payment_type_id WHERE wsp.web_shield_id = ?", [$w['id']]);
              $payment_names = array_column($payments, 'name');
              echo htmlspecialchars(implode(', ', $payment_names));
            ?>
          </td>
          <td>
            <a href="index.php?action=admin_edit_webshield&id=<?=$w['id']?>" class="btn btn-sm btn-primary">Edit</a>
            <a href="index.php?action=admin_delete_webshield&id=<?=$w['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa?')">Xóa</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div><?php require_once __DIR__ . '/../layout_footer.php'; ?>
