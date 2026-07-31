<?php

define('APP_NAME', 'PAMS — Permit Application Management System');
define('APP_URL', 'http://localhost/pams');
$__appRootDir = dirname(str_replace('\\', '/', __DIR__));
$__docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
define('BASE_PATH', ($__docRoot && strpos($__appRootDir, $__docRoot) === 0) ? substr($__appRootDir, strlen($__docRoot)) : '');
unset($__appRootDir, $__docRoot);
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6');
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('SESSION_LIFETIME', 7200);
define('LOGIN_MAX_ATTEMPTS', 6);
define('LOGIN_LOCK_MINUTES', 15);

define('MODULES', [
    'dashboard'              => 'Dashboard',
    'order-of-payment'       => 'Order of Payment Encoding',
    'op-records'             => 'Order of Payment Records',
    'permit-workflow'        => 'Permit Workflow',
    'workflow-details'       => 'Permit Workflow Records',
    'permit-approval-encoding' => 'Permit Approval Encoding',
    'permit-approval-records'  => 'Permit Approval Records',
    'releasing'              => 'Releasing Plans',
    'releasing-records'      => 'Releasing Records',
    'notifications'          => 'Notifications',
    'announcements'          => 'Announcements',
    'profile'                => 'Profile',
    'settings'               => 'Settings',
]);

define('MODULE_ICONS', [
    'dashboard'              => 'grid',
    'order-of-payment'       => 'file-text',
    'op-records'             => 'layers',
    'permit-workflow'        => 'git-branch',
    'workflow-details'       => 'git-branch',
    'permit-approval-encoding' => 'award',
    'permit-approval-records'  => 'layers',
    'releasing'              => 'package',
    'releasing-records'      => 'layers',
    'notifications'          => 'bell',
    'announcements'          => 'megaphone',
    'profile'                => 'user',
    'settings'               => 'settings',
]);

define('MODULE_SECTIONS', [
    'dashboard'              => 'Main',
    'order-of-payment'       => 'Modules',
    'op-records'             => 'Modules',
    'permit-workflow'        => 'Modules',
    'workflow-details'       => 'Modules',
    'permit-approval-encoding' => 'Modules',
    'permit-approval-records'  => 'Modules',
    'releasing'              => 'Modules',
    'releasing-records'      => 'Modules',
    'notifications'          => 'Account',
    'announcements'          => 'Account',
    'profile'                => 'Account',
    'settings'               => 'Account',
]);
