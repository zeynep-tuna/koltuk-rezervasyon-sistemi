<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$id = $_GET['id'] ?? null;

if ($id && ctype_digit((string)$id)) {
    $stmt = $pdo->prepare("UPDATE vatandaslar SET deleted = 1 WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

header('Location: vatandaslar.php');
exit;