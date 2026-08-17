<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user-shell.php';

function renderDevSidebar(string $activeKey): string {
    return renderPamsSidebar($activeKey);
}

function renderDevHeader(string $pageTitle): string {
    return renderPamsHeader($pageTitle);
}
