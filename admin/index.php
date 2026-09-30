<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
redirect(admin_logged() ? 'admin/dashboard' : 'admin/login');
