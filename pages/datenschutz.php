<?php
require_once __DIR__ . '/../includes/site_pages.php';

$pageKey = 'datenschutz';
$page = site_page_load_legal($pageKey, $db ?? null);
site_page_render_legal($pageKey, $page);
site_page_render_legal_edit_fab($pageKey);
