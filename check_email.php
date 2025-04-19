<?php
require_once 'config/database.php';

header('Content-Type: application/json');

$email = $_GET['email'] ?? '';

if (empty($email)) {
    echo json_encode(['exists' => false]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE email = :email');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

echo json_encode(['exists' => $row['count'] > 0]);
?> 