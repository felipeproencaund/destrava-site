<?php
require_once __DIR__ . '/../inc/bootstrap.php';
sorteio_session_start();
$_SESSION = [];
session_destroy();
sorteio_redirect('login.php');
