<?php require_once __DIR__ . '/../layout_header.php';
$webshields = $data['webshields'] ?? [];
?>
<div class="card">
  <h3>Web Shields của tôi</h3>
  <ul>
  <?php foreach($webshields as $w): ?>
    <li>
      <strong><?=htmlspecialchars($w['name'])?></strong> - <?=htmlspecialchars($w['domain'] ?? '')?>
      <?php if (currentUser()['role'] === 'admin'): ?>
          (<?=htmlspecialchars($w['manager_name'] ?? 'Không có người quản lý')?>)
      <?php endif; ?>
      <a class="btn btn-sm btn-primary float-end" href="index.php?action=manager_payments&web_id=<?=$w['id']?>">Thanh toán</a>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
