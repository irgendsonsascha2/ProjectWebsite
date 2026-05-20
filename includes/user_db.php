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
