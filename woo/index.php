<?php
$_GET['endpoint'] = trim($_GET['endpoint'] ?? '', '/');
require __DIR__ . '/../api/endpoint-rotation.php';
