<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('admin/login');
