<?php
require_once __DIR__ . '/auth.php';
destrava_admin_logout();
header('Location: login.php');
exit;
