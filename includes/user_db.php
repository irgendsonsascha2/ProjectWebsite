<?php

/**
 * Lesende Zugriffe auf users ohne Passwort-Feld (Defense in Depth neben Mongo-RBAC).
 */

if (!function_exists('user_public_projection')) {
    /**
     * @return array<string, int>
     */
    function user_public_projection(): array
    {
        return [
            'email' => 1,
            'username' => 1,
            'role' => 1,
            'created_at' => 1,
            'email_verified_at' => 1,
            'account_moderation' => 1,
        ];
    }
}

if (!function_exists('user_find_public_by_id')) {
    /**
     * @param MongoDB\Database $db
     * @param string|MongoDB\BSON\ObjectId $id
     * @return array<string, mixed>|object|null
     */
    function user_find_public_by_id($db, $id)
    {
        try {
            $oid = $id instanceof MongoDB\BSON\ObjectId ? $id : new MongoDB\BSON\ObjectId((string) $id);
        } catch (Throwable) {
            return null;
        }

        return $db->users->findOne(
            ['_id' => $oid],
            ['projection' => user_public_projection()]
        );
    }
}

if (!function_exists('user_session_from_document')) {
    /**
     * @param array<string, mixed>|object $user
     * @return array{email: string, username: string, role: string}
     */
    function user_session_from_document($user): array
    {
        if (is_object($user)) {
            $user = (array) $user;
        }

        return [
            'email' => (string) ($user['email'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? 'viewer'),
        ];
    }
}

if (!function_exists('comment_author_snapshot_from_session')) {
    /**
     * @return array{author_username: string, author_role: string}
     */
    function comment_author_snapshot_from_session(): array
    {
        $username = (string) ($_SESSION['username'] ?? '');
        if ($username === '') {
            $username = (string) ($_SESSION['email'] ?? 'User');
        }

        return [
            'author_username' => $username,
            'author_role' => (string) ($_SESSION['role'] ?? 'viewer'),
        ];
    }
}

if (!function_exists('roles_config_labels_by_role')) {
    /**
     * @return array<string, string> role key => Anzeigename aus roles_config
     */
    function roles_config_labels_by_role(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            require_once __DIR__ . '/db.php';
            [, $adminDb] = get_admin_mongo_connection();
            foreach ($adminDb->roles_config->find() as $roleDoc) {
                $key = (string) ($roleDoc['role'] ?? '');
                if ($key === '') {
                    continue;
                }
                $cache[$key] = (string) ($roleDoc['label'] ?? $key);
            }
        } catch (Throwable) {
            // Leerer Cache – Fallback auf Rollen-Schlüssel
        }

        return $cache;
    }
}

if (!function_exists('role_display_label')) {
    function role_display_label(string $roleKey): string
    {
        if ($roleKey === '') {
            return '';
        }
        $labels = roles_config_labels_by_role();

        return $labels[$roleKey] ?? $roleKey;
    }
}

if (!function_exists('comment_document_to_array')) {
    /**
     * Mongo find() liefert BSONDocument — für Kommentar-Helfer in Arrays umwandeln.
     *
     * @param array<string, mixed>|object $comment
     * @return array<string, mixed>
     */
    function comment_document_to_array($comment): array
    {
        if (is_array($comment)) {
            return $comment;
        }
        if ($comment instanceof MongoDB\Model\BSONDocument || $comment instanceof MongoDB\Model\BSONArray) {
            return $comment->getArrayCopy();
        }
        if ($comment instanceof ArrayObject) {
            return $comment->getArrayCopy();
        }

        return (array) $comment;
    }
}

if (!function_exists('comment_author_role_key')) {
    /**
     * @param array<string, mixed>|object $comment
     */
    function comment_author_role_key($comment): string
    {
        $comment = comment_document_to_array($comment);
        $info = $comment['user_info'] ?? null;
        if (is_array($info) && ($info['role'] ?? '') !== '') {
            return (string) $info['role'];
        }

        return (string) ($comment['author_role'] ?? '');
    }
}

if (!function_exists('comment_author_display_html')) {
    /**
     * Name + Rollen-Label (escaped) für Kommentar-Kopfzeile.
     *
     * @param array<string, mixed> $comment
     */
    function comment_author_display_html($comment): string
    {
        $comment = comment_document_to_array($comment);
        $info = is_array($comment['user_info'] ?? null) ? $comment['user_info'] : [];
        $name = (string) ($info['username'] ?? $info['email'] ?? $comment['author_username'] ?? 'Unbekannt');
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $roleKey = comment_author_role_key($comment);
        if ($roleKey === '') {
            return $nameEsc;
        }
        $roleLabel = htmlspecialchars(role_display_label($roleKey), ENT_QUOTES, 'UTF-8');

        return $nameEsc.' <span class="comment-role">'.$roleLabel.'</span>';
    }
}

if (!function_exists('comment_attach_user_info')) {
    /**
     * Ergänzt Kommentar-Dokumente um user_info (ohne Passwort), nutzt Snapshot oder Admin-Lesen.
     *
     * @param array<int, array<string, mixed>> $comments
     * @return array<int, array<string, mixed>>
     */
    function comment_attach_user_info(array $comments): array
    {
        if (count($comments) === 0) {
            return $comments;
        }

        require_once __DIR__ . '/db.php';

        foreach ($comments as &$comment) {
            $comment = comment_document_to_array($comment);
            if (!empty($comment['author_username'])) {
                $comment['user_info'] = [
                    'username' => (string) $comment['author_username'],
                    'email' => (string) ($comment['author_username']),
                    'role' => (string) ($comment['author_role'] ?? 'viewer'),
                ];
                continue;
            }

            if (!isset($comment['user_id'])) {
                continue;
            }

            try {
                [, $adminDb] = get_admin_mongo_connection();
                $user = user_find_public_by_id($adminDb, $comment['user_id']);
                if ($user !== null) {
                    $comment['user_info'] = user_session_from_document($user);
                    $comment['user_info']['username'] = $comment['user_info']['username'] !== ''
                        ? $comment['user_info']['username']
                        : $comment['user_info']['email'];
                }
            } catch (Throwable) {
                // Kommentar ohne Anzeigenamen belassen
            }
        }
        unset($comment);

        return $comments;
    }
}
