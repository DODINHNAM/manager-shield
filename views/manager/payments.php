<?php require_once __DIR__ . '/../layout_header.php';
$webshield = $data['webshield'] ?? null;
$payments = $data['payments'] ?? [];
?>
<div class="card">
  <h3>Thanh toán cho <?=htmlspecialchars($webshield['name'])?></h3>
  <ul>
  <?php foreach($payments as $p): ?>
    <li>
      <strong><?=htmlspecialchars($p['payment_name'])?></strong>
      <a class="btn btn-sm btn-outline-primary float-end" href="index.php?action=manager_edit_payment&wsp_id=<?=$p['id']?>">Sửa</a>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
