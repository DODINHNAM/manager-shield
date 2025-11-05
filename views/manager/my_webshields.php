<?php require_once __DIR__ . '/../layout_header.php';
$webshields = $data['webshields'] ?? [];
?>
<h3>My Web Shields</h3>
<ul class="list-group">
<?php foreach($webshields as $w): ?>
  <li class="list-group-item">
    <strong><?=htmlspecialchars($w['name'])?></strong> - <?=htmlspecialchars($w['domain'] ?? '')?>
    <a class="btn btn-sm btn-primary float-end" href="index.php?action=manager_payments&web_id=<?=$w['id']?>">Payments</a>
  </li>
<?php endforeach; ?>
</ul>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
