<?php require_once __DIR__ . '/../layout_header.php';
$webshields = $data['webshields'] ?? [];
?>
<div class="card">
  <h3>Web Shields của tôi</h3>
  <table class="data-table">
    <thead><tr><th>ID</th><th>Tên</th><th>Domain</th><th>Thanh toán</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach($webshields as $w): ?>
        <tr>
          <td><?=$w['id']?></td>
          <td><?=htmlspecialchars($w['name'])?></td>
          <td><?=htmlspecialchars($w['domain'])?></td>
          <td>
            <?php
              $payments = db_query("SELECT pt.name FROM web_shield_payments wsp JOIN payment_types pt ON pt.id = wsp.payment_type_id WHERE wsp.web_shield_id = ?", [$w['id']]);
              $payment_names = array_column($payments, 'name');
              echo htmlspecialchars(implode(', ', $payment_names));
            ?>
          </td>
          <td>
            <a class="btn btn-sm btn-primary" href="index.php?action=manager_payments&web_id=<?=$w['id']?>">Cấu hình</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>