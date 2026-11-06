<?php
require_once __DIR__ . '/inc/auth.php';
destrava_admin_logout();
header('Location: login.php');
exit;
