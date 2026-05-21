<?php

/**
 * Erlaubte Rückkehr-URLs nach Projekt-Bearbeitung (nur index.php mit bekannten page-Werten).
 */
function project_safe_return_to(?string $candidate, string $default): string
{
    if ($candidate === null || trim($candidate) === '') {
        return $default;
    }

    $query = parse_url($candidate, PHP_URL_QUERY);
    if (! is_string($query) || $query === '') {
        return $default;
    }

    parse_str($query, $params);
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['page'] ?? ''));
    if ($page === '') {
        return $default;
    }

    if ($page === 'project_detail') {
        $id = preg_replace('/[^a-fA-F0-9]/', '', (string) ($params['id'] ?? ''));
        if (strlen($id) === 24) {
            return 'index.php?page=project_detail&id='.$id;
        }

        return $default;
    }

    if (in_array($page, ['project_grid', 'create_project', 'home', 'account'], true)) {
        return 'index.php?page='.$page;
    }

    if ($page === 'edit_project') {
        $id = preg_replace('/[^a-fA-F0-9]/', '', (string) ($params['id'] ?? ''));
        if (strlen($id) === 24) {
            return 'index.php?page=edit_project&id='.$id;
        }

        return $default;
    }

    return $default;
}
