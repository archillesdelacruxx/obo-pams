<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user-shell.php';

function renderAdminSidebar(string $activeKey): string {
    return renderPamsSidebar($activeKey);
}

function renderAdminHeader(string $pageTitle): string {
    return renderPamsHeader($pageTitle);
}
