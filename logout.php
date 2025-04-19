<?php
require_once 'auth/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: index.php');
exit();
?> 