<?php
require_once 'config/auth.php';
session_destroy();
header('Location: /project/index.php');
exit();
?>
