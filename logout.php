<?php
require_once __DIR__ . '/includes/auth.php';
startSession();
logout();
redirect('index.php');
