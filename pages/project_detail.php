<?php
if (!headers_sent()) {
    ob_start();
}
require_once __DIR__ . '/../includes/bootstrap.php';

$commentTextMaxLength = defined('COMMENT_TEXT_MAX_LENGTH') ? (int) COMMENT_TEXT_MAX_LENGTH : 400;

$isLoggedIn = isset($_SESSION['user_id']);

// --- DATENBANK & PROJEKT LADEN ---
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    echo "Projekt nicht gefunden.";
    return;
}
$ajaxActionUrl = 'pages/project_detail.php?id=' . urlencode($projectId);

try {
    $projectObjectId = new ObjectId($projectId);
    $project = $db->projects->findOne(['_id' => $projectObjectId]);

    if (!$project) {
        echo "Projekt existiert nicht.";
        return;
    }
    if (! authz_can_view_project($project)) {
        echo 'Projekt existiert nicht.';
        return;
    }
} catch (Exception $e) {
    echo "Ungültige Projekt-ID.";
    return;
}

$message = '';
$canDeleteProjects = $isLoggedIn && can('delete_all');
$canViewProjects = can('view_projects');
$canViewComments = can('view_comments');
$canViewLikes = can('view_likes');
$canComment = $isLoggedIn && can('comment');
$canCommentLimit = $isLoggedIn && can('comment_limit');
$isAjax = false;
if (! authz_can_view_project($project)) {
    echo 'Du hast keine Berechtigung, dieses Projekt anzusehen.';
    return;
}
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    $isAjax = true;
} elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $isAjax = in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['xmlhttprequest', 'fetch'], true);
} elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $isAjax = true;
}

function parse_media_id($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    try {
        return new ObjectId($raw);
    } catch (Exception $e) {
        return null;
    }
}

function fetch_comments_with_users($db, $projectObjectId, $mediaObjectId) {
    $commentsCursor = $db->comments->find(
        ['project_id' => $projectObjectId, 'media_id' => $mediaObjectId],
        ['sort' => ['created_at' => 1]]
    );
    $comments = iterator_to_array($commentsCursor);

    return comment_attach_user_info($comments);
}

function fetch_recent_comments_with_users($db, $projectObjectId, $mediaObjectId, $limit = 2) {
    $cursor = $db->comments->find(
        ['project_id' => $projectObjectId, 'media_id' => $mediaObjectId],
        ['sort' => ['created_at' => -1], 'limit' => max(1, (int) $limit)]
    );
    $comments = iterator_to_array($cursor);

    return comment_attach_user_info($comments);
}

function build_comment_tree($comments) {
    $tree = [];
    foreach ($comments as $comment) {
        $parentKey = null;
        if (isset($comment['parent_comment_id'])) {
            $parentKey = (string)$comment['parent_comment_id'];
        }
        if (!isset($tree[$parentKey])) {
            $tree[$parentKey] = [];
        }
        $tree[$parentKey][] = $comment;
    }
    return $tree;
}

function sort_comments_by_created_at_desc(&$comments) {
    usort($comments, function ($a, $b) {
        $aTime = $a['created_at']->toDateTime()->getTimestamp();
        $bTime = $b['created_at']->toDateTime()->getTimestamp();
        return $bTime <=> $aTime;
    });
}

function sort_comments_by_created_at_asc(&$comments) {
    usort($comments, function ($a, $b) {
        $aTime = $a['created_at']->toDateTime()->getTimestamp();
        $bTime = $b['created_at']->toDateTime()->getTimestamp();
        return $aTime <=> $bTime;
    });
}

function render_comment_items($comments, $commentLimitReached, $commentLimit, $ajaxActionUrl, $currentUserId, $currentUserRole, $deleteRolesAllowed, $canDeleteOthers, $canComment, $mediaIdStr) {
    global $commentTextMaxLength;
    if (!isset($commentTextMaxLength)) {
        $commentTextMaxLength = defined('COMMENT_TEXT_MAX_LENGTH') ? (int) COMMENT_TEXT_MAX_LENGTH : 400;
    }
    $tree = build_comment_tree($comments);
    $topLevel = $tree[null] ?? [];
    sort_comments_by_created_at_desc($topLevel);

    ob_start();
    foreach ($topLevel as $comment) {
        $commentId = (string)$comment['_id'];
        $authorRole = $comment['user_info']['role'] ?? '';
        $isOwnComment = $currentUserId && (string)$comment['user_id'] === $currentUserId;
        $canDelete = $isOwnComment || ($canDeleteOthers && ($deleteRolesAllowed === ['*'] || in_array($authorRole, $deleteRolesAllowed, true)));
        ?>
        <div class="comment" data-comment-id="<?php echo htmlspecialchars($commentId); ?>">
            <p class="author"><?php echo htmlspecialchars($comment['user_info']['username'] ?? $comment['user_info']['email']); ?></p>
            <p class="date"><?php echo $comment['created_at']->toDateTime()->format('d.m.Y H:i'); ?></p>
            <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['text'])); ?></p>
            <?php if ($canDelete): ?>
                <form method="POST" class="comment-delete-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="media_id" value="<?php echo htmlspecialchars($mediaIdStr); ?>">
                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                    <button type="submit" name="delete_comment" value="1" class="comment-delete-button">Löschen</button>
                </form>
            <?php endif; ?>
            <?php if ($canComment): ?>
                <button type="button" class="reply-toggle" data-reply-to="<?php echo htmlspecialchars($commentId); ?>">Antworten</button>
                <form method="POST" class="comment-form reply-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="media_id" value="<?php echo htmlspecialchars($mediaIdStr); ?>">
                    <input type="hidden" name="parent_comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                    <div class="comment-form-row">
                        <textarea name="comment_text" placeholder="Antwort schreiben..." maxlength="<?php echo (int) $commentTextMaxLength; ?>" data-maxlength="<?php echo (int) $commentTextMaxLength; ?>" <?php echo $commentLimitReached ? 'disabled' : ''; ?> rows="1"></textarea>
                        <input type="hidden" name="submit_comment" value="1">
                        <button type="submit" name="submit_comment" aria-label="Antworten" <?php echo $commentLimitReached ? 'disabled' : ''; ?>>
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            <?php
            $replies = $tree[$commentId] ?? [];
            if (count($replies) > 0) {
                sort_comments_by_created_at_asc($replies);
                echo '<div class="comment-replies">';
                foreach ($replies as $reply) {
                    $replyId = (string)$reply['_id'];
                    $replyAuthorRole = $reply['user_info']['role'] ?? '';
                    $replyIsOwn = $currentUserId && (string)$reply['user_id'] === $currentUserId;
                    $replyCanDelete = $replyIsOwn || ($canDeleteOthers && ($deleteRolesAllowed === ['*'] || in_array($replyAuthorRole, $deleteRolesAllowed, true)));
                    ?>
                    <div class="comment comment-reply" data-comment-id="<?php echo htmlspecialchars($replyId); ?>">
                        <p class="author"><?php echo htmlspecialchars($reply['user_info']['username'] ?? $reply['user_info']['email']); ?></p>
                        <p class="date"><?php echo $reply['created_at']->toDateTime()->format('d.m.Y H:i'); ?></p>
                        <p class="comment-text"><?php echo nl2br(htmlspecialchars($reply['text'])); ?></p>
                        <?php if ($replyCanDelete): ?>
                            <form method="POST" class="comment-delete-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" name="media_id" value="<?php echo htmlspecialchars($mediaIdStr); ?>">
                                <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($replyId); ?>">
                                <button type="submit" name="delete_comment" value="1" class="comment-delete-button">Löschen</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }
    if (count($topLevel) === 0) {
        echo '<p>Noch keine Kommentare vorhanden.</p>';
    }
    if ($commentLimitReached && $canComment && $commentLimit > 0) {
        echo '<p class="comment-limit-note">Kommentar-Limit erreicht (max. ' . (int)$commentLimit . ' pro Nutzer).</p>';
    }
    return ob_get_clean();
}

function render_hover_comment_items($comments) {
    if (empty($comments)) {
        return '';
    }
    ob_start();
    foreach ($comments as $comment) {
        $author = $comment['user_info']['username'] ?? $comment['user_info']['email'] ?? 'User';
        $text = $comment['text'] ?? '';
        ?>
        <div class="hover-comment">
            <span class="hover-author"><?php echo htmlspecialchars($author); ?></span>
            <span class="hover-text"><?php echo htmlspecialchars($text); ?></span>
        </div>
        <?php
    }
    return ob_get_clean();
}

function delete_project_files($project) {
    $gallery = normalize_gallery($project['gallery'] ?? []);
    foreach ($gallery as $item) {
        $url = $item['url'] ?? '';
        if (strpos($url, 'content/images/') === 0 || strpos($url, 'content/videos/') === 0) {
            $path = __DIR__ . '/../' . $url;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

if (isset($_POST['delete_project'])) {
    authz_require_can('delete_all');
    authz_require_verified_email();
    authz_require_active_account();
    delete_project_files($project);
    $db->projects->deleteOne(['_id' => $projectObjectId]);
    $db->likes->deleteMany(['project_id' => $projectObjectId]);
    $db->comments->deleteMany(['project_id' => $projectObjectId]);
    header('Location: index.php?page=project_grid');
    exit();
}

// --- LOGIK: LIKE / DISLIKE ---
if (isset($_POST['interaction'])) {
    authz_require_can('like_dislike');
    authz_require_verified_email();
    authz_require_active_account();
    $userId = new ObjectId($_SESSION['user_id']);
    $type = $_POST['interaction']; // 'like' or 'dislike'
    $mediaObjectId = parse_media_id($_POST['media_id'] ?? '');

    if (!$mediaObjectId) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'interaction',
                'message' => 'Ungültiges Medium.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $existing = $db->likes->findOne([
        'project_id' => $projectObjectId,
        'media_id' => $mediaObjectId,
        'user_id' => $userId
    ]);

    if ($type === 'like' || $type === 'dislike') {
        if ($existing && ($existing['type'] ?? null) === $type) {
            // Nochmal klicken => Vote entfernen
            $db->likes->deleteOne(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'user_id' => $userId]);
        } else {
            // Wechsel oder erster Vote
            $db->likes->deleteOne(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'user_id' => $userId]);
            $db->likes->insertOne([
                'project_id' => $projectObjectId,
                'media_id' => $mediaObjectId,
                'user_id' => $userId,
                'type' => $type,
                'created_at' => new UTCDateTime()
            ]);
        }
    }
    if ($isAjax) {
        $likeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'type' => 'like']) : 0;
        $dislikeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'type' => 'dislike']) : 0;
        $currentUserLike = $db->likes->findOne(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'user_id' => $userId]);
        $currentUserInteraction = $currentUserLike['type'] ?? null;
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'action' => 'interaction',
            'mediaId' => (string)$mediaObjectId,
            'likeCount' => $likeCount,
            'dislikeCount' => $dislikeCount,
            'currentUserInteraction' => $currentUserInteraction
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- LOGIK: KOMMENTAR ---
if (isset($_POST['delete_comment'])) {
    authz_require_verified_email();
    authz_require_active_account();
    $userId = new ObjectId($_SESSION['user_id']);
    $commentIdRaw = trim($_POST['comment_id'] ?? '');
    $mediaObjectId = parse_media_id($_POST['media_id'] ?? '');
    if (!$mediaObjectId) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Ungültiges Medium.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    try {
        $commentId = new ObjectId($commentIdRaw);
    } catch (Exception $e) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Ungültiger Kommentar.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $comment = $db->comments->findOne([
        '_id' => $commentId,
        'project_id' => $projectObjectId,
        'media_id' => $mediaObjectId
    ]);
    if (!$comment) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Kommentar nicht gefunden.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $deleteRolesAllowed = [];
    $roleData = null;
    if (isset($_SESSION['role'])) {
        $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
        if ($roleData && isset($roleData['comment_delete_roles'])) {
            $deleteRolesAllowed = is_array($roleData['comment_delete_roles'])
                ? $roleData['comment_delete_roles']
                : iterator_to_array($roleData['comment_delete_roles']);
        }
    }
    $allowed = authz_can_delete_comment(
        (array) $comment,
        (string) $userId,
        $deleteRolesAllowed,
        can('delete_comments')
    );

    if (! $allowed) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Keine Berechtigung zum Löschen.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $db->comments->deleteMany([
        'media_id' => $mediaObjectId,
        '$or' => [
            ['_id' => $commentId],
            ['parent_comment_id' => $commentId]
        ]
    ]);
    if ($isAjax) {
        $comments = fetch_comments_with_users($db, $projectObjectId, $mediaObjectId);
        $userCommentCount = $db->comments->countDocuments([
            'project_id' => $projectObjectId,
            'media_id' => $mediaObjectId,
            'user_id' => $userId
        ]);
        $commentLimit = 0;
        if ($canCommentLimit) {
            $roleData = $db->roles_config->findOne(['role' => $_SESSION['role'] ?? '']);
            if ($roleData && isset($roleData['comment_limit'])) {
                $commentLimit = max(0, (int)$roleData['comment_limit']);
            }
        }
        $commentLimitReached = $commentLimit > 0 && $userCommentCount >= $commentLimit;
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'action' => 'comment',
            'message' => 'Kommentar gelöscht.',
            'mediaId' => (string)$mediaObjectId,
            'commentsHtml' => render_comment_items(
                $comments,
                $commentLimitReached,
                $commentLimit,
                $ajaxActionUrl,
                (string)$userId,
                $_SESSION['role'] ?? '',
                $deleteRolesAllowed,
                $canDeleteOthers,
                $canComment,
                (string)$mediaObjectId
            ),
            'commentCount' => $canViewComments ? $db->comments->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId]) : 0,
            'hoverHtml' => $canViewComments ? render_hover_comment_items(fetch_recent_comments_with_users($db, $projectObjectId, $mediaObjectId)) : '',
            'commentLimitReached' => $commentLimitReached,
            'commentLimit' => $commentLimit
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['submit_comment'])) {
    authz_require_can('comment');
    authz_require_verified_email();
    authz_require_active_account();
    $userId = new ObjectId($_SESSION['user_id']);
    $commentText = trim($_POST['comment_text']);
    $commentLimit = defined('COMMENT_TEXT_MAX_LENGTH') ? COMMENT_TEXT_MAX_LENGTH : 400;
    $parentCommentIdRaw = trim($_POST['parent_comment_id'] ?? '');
    $mediaObjectId = parse_media_id($_POST['media_id'] ?? '');
    if (!$mediaObjectId) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Ungültiges Medium.'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    $parentCommentId = null;
    if ($parentCommentIdRaw !== '') {
        try {
            $parentCommentId = new ObjectId($parentCommentIdRaw);
        } catch (Exception $e) {
            if ($isAjax) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'action' => 'comment',
                    'message' => 'Ungültige Antwort.'
                ]);
                exit();
            }
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    if (empty($commentText)) {
        if ($isAjax) {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'action' => 'comment',
            'message' => 'Kommentar darf nicht leer sein.'
        ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    if (mb_strlen($commentText) > $commentLimit) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Keine Romane Schreiben bitte'
            ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $userCommentCount = $db->comments->countDocuments([
        'project_id' => $projectObjectId,
        'media_id' => $mediaObjectId,
        'user_id' => $userId
    ]);
    $commentLimit = 0;
    if ($canCommentLimit) {
        $roleData = $roleData ?? $db->roles_config->findOne(['role' => $_SESSION['role'] ?? '']);
        if ($roleData && isset($roleData['comment_limit'])) {
            $commentLimit = max(0, (int)$roleData['comment_limit']);
        }
    }
    if ($commentLimit > 0 && $userCommentCount >= $commentLimit) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                    'message' => 'Kommentar-Limit erreicht (max. ' . $commentLimit . ' pro Nutzer).'
                ]);
            exit();
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    if ($parentCommentId !== null) {
        $parentExists = $db->comments->countDocuments([
            '_id' => $parentCommentId,
            'project_id' => $projectObjectId,
            'media_id' => $mediaObjectId
        ]) > 0;
        if (!$parentExists) {
            if ($isAjax) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'action' => 'comment',
                    'message' => 'Antwort nicht möglich.'
                ]);
                exit();
            }
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    $payload = array_merge([
        'project_id' => $projectObjectId,
        'media_id' => $mediaObjectId,
        'user_id' => $userId,
        'text' => $commentText,
        'created_at' => new UTCDateTime(),
        'updated_at' => new UTCDateTime(),
    ], comment_author_snapshot_from_session());
    if ($parentCommentId !== null) {
        $payload['parent_comment_id'] = $parentCommentId;
    }

    $db->comments->insertOne($payload);
    $message = "Kommentar gespeichert!";
    if ($isAjax) {
        $comments = fetch_comments_with_users($db, $projectObjectId, $mediaObjectId);
        $userCommentCount = $db->comments->countDocuments([
            'project_id' => $projectObjectId,
            'media_id' => $mediaObjectId,
            'user_id' => $userId
        ]);
        $commentLimit = 0;
        if ($canCommentLimit) {
            $roleData = $db->roles_config->findOne(['role' => $_SESSION['role'] ?? '']);
            if ($roleData && isset($roleData['comment_limit'])) {
                $commentLimit = max(0, (int)$roleData['comment_limit']);
            }
        }
        $commentLimitReached = $commentLimit > 0 && $userCommentCount >= $commentLimit;
        $deleteRolesAllowed = [];
        $canDeleteOthers = can('delete_comments');
        if ($canDeleteOthers && isset($_SESSION['role'])) {
            $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
            if ($roleData && isset($roleData['comment_delete_roles'])) {
                $deleteRolesAllowed = is_array($roleData['comment_delete_roles']) ? $roleData['comment_delete_roles'] : iterator_to_array($roleData['comment_delete_roles']);
            }
        }
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'action' => 'comment',
            'message' => $message,
            'mediaId' => (string)$mediaObjectId,
            'commentsHtml' => render_comment_items(
                $comments,
                $commentLimitReached,
                $commentLimit,
                $ajaxActionUrl,
                (string)$userId,
                $_SESSION['role'] ?? '',
                $deleteRolesAllowed,
                $canDeleteOthers,
                $canComment,
                (string)$mediaObjectId
            ),
            'commentCount' => $canViewComments ? $db->comments->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId]) : 0,
            'hoverHtml' => $canViewComments ? render_hover_comment_items(fetch_recent_comments_with_users($db, $projectObjectId, $mediaObjectId)) : '',
            'commentLimitReached' => $commentLimitReached,
            'commentLimit' => $commentLimit
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

if ($isAjax && isset($_POST['load_media'])) {
    $mediaObjectId = parse_media_id($_POST['media_id'] ?? '');
    if (!$mediaObjectId) {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'action' => 'load_media',
            'message' => 'Ungültiges Medium.'
        ]);
        exit();
    }

    $likeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'type' => 'like']) : 0;
    $dislikeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId, 'type' => 'dislike']) : 0;
    $currentUserInteraction = null;
    if ($isLoggedIn && can('like_dislike')) {
        $userLike = $db->likes->findOne([
            'project_id' => $projectObjectId,
            'media_id' => $mediaObjectId,
            'user_id' => new ObjectId($_SESSION['user_id'])
        ]);
        $currentUserInteraction = $userLike['type'] ?? null;
    }

    $comments = $canViewComments ? fetch_comments_with_users($db, $projectObjectId, $mediaObjectId) : [];
    $commentLimitReached = false;
    $commentLimit = 0;
    $currentUserId = $isLoggedIn ? $_SESSION['user_id'] : null;
    $currentUserRole = $_SESSION['role'] ?? '';
    $canDeleteOthers = $isLoggedIn && can('delete_comments');
    $deleteRolesAllowed = [];
    if ($isLoggedIn) {
        $userCommentCount = $db->comments->countDocuments([
            'project_id' => $projectObjectId,
            'media_id' => $mediaObjectId,
            'user_id' => new ObjectId($_SESSION['user_id'])
        ]);
        $roleData = $db->roles_config->findOne(['role' => $currentUserRole]);
        if ($canCommentLimit && $roleData && isset($roleData['comment_limit'])) {
            $commentLimit = max(0, (int)$roleData['comment_limit']);
        }
        $commentLimitReached = $commentLimit > 0 && $userCommentCount >= $commentLimit;
        if ($canDeleteOthers && $roleData && isset($roleData['comment_delete_roles'])) {
            $deleteRolesAllowed = is_array($roleData['comment_delete_roles']) ? $roleData['comment_delete_roles'] : iterator_to_array($roleData['comment_delete_roles']);
        }
    }

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'action' => 'load_media',
        'mediaId' => (string)$mediaObjectId,
        'likeCount' => $likeCount,
        'dislikeCount' => $dislikeCount,
        'commentCount' => $canViewComments ? $db->comments->countDocuments(['project_id' => $projectObjectId, 'media_id' => $mediaObjectId]) : 0,
        'currentUserInteraction' => $currentUserInteraction,
        'commentsHtml' => $canViewComments ? render_comment_items(
            $comments,
            $commentLimitReached,
            $commentLimit,
            $ajaxActionUrl,
            $currentUserId,
            $currentUserRole,
            $deleteRolesAllowed,
            $canDeleteOthers,
            $canComment,
            (string)$mediaObjectId
        ) : '<p>Keine Berechtigung, Kommentare zu sehen.</p>',
        'commentLimitReached' => $commentLimitReached,
        'commentLimit' => $commentLimit
    ]);
    exit();
}

// --- DATEN FÜR DIE ANZEIGE LADEN ---
// Berechtigungs-Logik für den Edit-Button
$canEdit = false;
if ($isLoggedIn) {
    if (can('edit_all')) {
        $canEdit = true;
    } elseif (can('edit_own') && isset($project['author_id']) && (string)$project['author_id'] === $_SESSION['user_id']) {
        $canEdit = true;
    }
}

$gallery = normalize_gallery($project['gallery'] ?? []);
$defaultMediaLimit = defined('PROJECT_DETAIL_MEDIA_LIMIT') ? (int) PROJECT_DETAIL_MEDIA_LIMIT : 30;
$mediaLimit = isset($_GET['media_limit']) ? (int) $_GET['media_limit'] : $defaultMediaLimit;
if ($mediaLimit < 1) {
    $mediaLimit = $defaultMediaLimit;
}
$mediaLimit = min($mediaLimit, 200);
$gallerySlice = array_slice($gallery, 0, $mediaLimit);
$hasMore = count($gallery) > $mediaLimit;

$mediaIds = [];
foreach ($gallerySlice as $item) {
    if (!empty($item['media_id']) && $item['media_id'] instanceof ObjectId) {
        $mediaIds[] = $item['media_id'];
    }
}

$likeCounts = [];
$dislikeCounts = [];
if ($canViewLikes && !empty($mediaIds)) {
    $likeAgg = $db->likes->aggregate([
        ['$match' => ['project_id' => $projectObjectId, 'media_id' => ['$in' => $mediaIds]]],
        ['$group' => [
            '_id' => ['media_id' => '$media_id', 'type' => '$type'],
            'count' => ['$sum' => 1]
        ]]
    ]);
    foreach ($likeAgg as $row) {
        $mediaKey = (string)$row['_id']['media_id'];
        $type = $row['_id']['type'] ?? '';
        if ($type === 'like') {
            $likeCounts[$mediaKey] = (int)$row['count'];
        } elseif ($type === 'dislike') {
            $dislikeCounts[$mediaKey] = (int)$row['count'];
        }
    }
}

$commentCounts = [];
if ($canViewComments && !empty($mediaIds)) {
    $commentAgg = $db->comments->aggregate([
        ['$match' => ['project_id' => $projectObjectId, 'media_id' => ['$in' => $mediaIds]]],
        ['$group' => [
            '_id' => '$media_id',
            'count' => ['$sum' => 1]
        ]]
    ]);
    foreach ($commentAgg as $row) {
        $commentCounts[(string)$row['_id']] = (int)$row['count'];
    }
}

$hoverPreviews = [];
if ($canViewComments && !empty($mediaIds)) {
    foreach ($gallerySlice as $item) {
        if (empty($item['media_id']) || !($item['media_id'] instanceof ObjectId)) {
            continue;
        }
        $mediaKey = (string)$item['media_id'];
        $preview = fetch_recent_comments_with_users($db, $projectObjectId, $item['media_id'], 2);
        $hoverPreviews[$mediaKey] = render_hover_comment_items($preview);
    }
}
?>

<article class="project-detail">
    <a href="index.php?page=project_grid" class="back-link">← Zurück zur Übersicht</a>

    <h1><?php echo htmlspecialchars($project['title']); ?></h1>

    <section class="gallery-section">
        <div class="gallery-grid">
            <?php if (!empty($gallerySlice)): ?>
                <?php foreach ($gallerySlice as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                        $mediaId = $item['media_id'] ?? null;
                        $mediaIdStr = ($mediaId instanceof ObjectId) ? (string)$mediaId : '';
                        $likeCount = $mediaIdStr !== '' ? ($likeCounts[$mediaIdStr] ?? 0) : 0;
                        $dislikeCount = $mediaIdStr !== '' ? ($dislikeCounts[$mediaIdStr] ?? 0) : 0;
                        $commentCount = $mediaIdStr !== '' ? ($commentCounts[$mediaIdStr] ?? 0) : 0;
                        $hoverHtml = $mediaIdStr !== '' ? ($hoverPreviews[$mediaIdStr] ?? '') : '';
                    ?>
                    <?php if ($url): ?>
                        <div class="media-card">
                            <button class="media-item skeleton-host" data-skeleton-media data-type="<?php echo htmlspecialchars($type); ?>" data-src="<?php echo htmlspecialchars($url); ?>" data-media-id="<?php echo htmlspecialchars($mediaIdStr); ?>">
                                <span class="skeleton-panel skeleton-panel--tile" aria-hidden="true"></span>
                                <?php if ($type === 'video'): ?>
                                    <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline disablepictureinpicture></video>
                                    <span class="media-play">▶</span>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy">
                                <?php endif; ?>
                                <?php if ($canViewLikes || $canViewComments): ?>
                                    <div class="media-metrics" data-media-id="<?php echo htmlspecialchars($mediaIdStr); ?>">
                                        <?php if ($canViewLikes): ?>
                                            <span class="metric" title="Likes">
                                                <span class="metric-icon" aria-hidden="true">
                                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                                    </svg>
                                                </span>
                                                <span class="metric-count" data-kind="like"><?php echo (int)$likeCount; ?></span>
                                            </span>
                                            <span class="metric" title="Dislikes">
                                                <span class="metric-icon" aria-hidden="true">
                                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                                        <g transform="translate(0 24) scale(1 -1)">
                                                            <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                                        </g>
                                                    </svg>
                                                </span>
                                                <span class="metric-count" data-kind="dislike"><?php echo (int)$dislikeCount; ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($canViewComments): ?>
                                            <span class="metric" title="Kommentare">
                                                <span class="metric-icon" aria-hidden="true">
                                                    <svg class="ui-icon ui-icon--stroke" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Z" />
                                                    </svg>
                                                </span>
                                                <span class="metric-count" data-kind="comment"><?php echo (int)$commentCount; ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($canViewComments): ?>
                                    <div class="media-hover-comments" data-media-id="<?php echo htmlspecialchars($mediaIdStr); ?>">
                                        <?php echo $hoverHtml; ?>
                                    </div>
                                <?php endif; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="media-card" aria-hidden="true">
                    <div class="media-item placeholder-tile">
                        <img src="img/placeholder.svg" alt="Platzhalter">
                        <span class="placeholder-text">Noch keine Medien</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($hasMore): ?>
            <?php
                $nextLimit = min(count($gallery), $mediaLimit + 30);
                $query = $_GET;
                $query['media_limit'] = $nextLimit;
                $loadMoreUrl = 'index.php?' . http_build_query($query);
            ?>
            <a class="load-more" href="<?php echo htmlspecialchars($loadMoreUrl); ?>">Mehr laden</a>
        <?php endif; ?>
    </section>

    <p class="description"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
    <p>Gepostet am: <?php echo $project['created_at']->toDateTime()->format('d.m.Y'); ?></p>


    
</article>

<?php if ($canEdit): ?>
<a href="index.php?page=edit_project&id=<?php echo (string)$projectObjectId; ?>" class="fab fab-edit" title="Projekt bearbeiten"><?php echo svg_icon_pencil(22); ?></a>
<?php endif; ?>

<?php if ($canDeleteProjects): ?>
<form method="POST" action="index.php?page=project_detail&id=<?php echo (string)$projectObjectId; ?>" id="delete-project-form">
    <input type="hidden" name="delete_project" value="1">
</form>
<button type="submit" form="delete-project-form" class="fab fab-delete" title="Projekt löschen" aria-label="Projekt löschen">
    <?php echo svg_icon_trash(22); ?>
</button>
<?php endif; ?>

<div class="lightbox" id="lightbox" aria-hidden="true">
    <div class="lightbox-content" role="dialog" aria-modal="true">
        <button class="lightbox-close" type="button" aria-label="Schließen">×</button>
        <div class="lightbox-body">
            <div class="lightbox-media"></div>
            <aside class="lightbox-panel">
                <p class="interaction-status" id="interaction-status" role="status" aria-live="polite"></p>

                <?php if (can('like_dislike')): ?>
                    <form method="POST" class="interaction-buttons lightbox-interaction-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="media_id" value="">
                        <button type="submit" name="interaction" value="like" aria-pressed="false">
                            <span class="interaction-emoji" aria-hidden="true">
                                <span class="ui-icon-swap">
                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                    </svg>
                                    <svg class="ui-icon ui-icon--filled" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M1 21h4V9H1v12zM23 10c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                                    </svg>
                                </span>
                            </span>
                            <?php if ($canViewLikes): ?>
                                <span class="like-count">0</span>
                            <?php endif; ?>
                        </button>
                        <button type="submit" name="interaction" value="dislike" aria-pressed="false">
                            <span class="interaction-emoji" aria-hidden="true">
                                <span class="ui-icon-swap">
                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <g transform="translate(0 24) scale(1 -1)">
                                            <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                        </g>
                                    </svg>
                                    <svg class="ui-icon ui-icon--filled" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <g transform="translate(0 24) scale(1 -1)">
                                            <path d="M1 21h4V9H1v12zM23 10c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <?php if ($canViewLikes): ?>
                                <span class="dislike-count">0</span>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <?php if ($canViewLikes): ?>
                        <div class="interaction-buttons lightbox-interaction-display">
                            <button type="button" class="interaction-button interaction-button--locked" data-auth-redirect="like_dislike" aria-disabled="true">
                                <span class="interaction-emoji" aria-hidden="true">
                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                    </svg>
                                </span>
                                <span class="like-count">0</span>
                            </button>
                            <button type="button" class="interaction-button interaction-button--locked" data-auth-redirect="like_dislike" aria-disabled="true">
                                <span class="interaction-emoji" aria-hidden="true">
                                    <svg class="ui-icon ui-icon--outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                        <g transform="translate(0 24) scale(1 -1)">
                                            <path d="M20 8h-5.612l1.123-3.367c.202-.608.1-1.282-.275-1.802S14.253 2 13.612 2H12c-.297 0-.578.132-.769.36L6.531 8H4c-1.103 0-2 .897-2 2v9c0 1.103.897 2 2 2h13.307a2.01 2.01 0 0 0 1.873-1.298l2.757-7.351A1 1 0 0 0 22 12v-2c0-1.103-.897-2-2-2zM4 10h2v9H4v-9zm16 1.819L17.307 19H8V9.362L12.468 4h1.146l-1.562 4.683A.998.998 0 0 0 13 10h7v1.819z"/>
                                        </g>
                                    </svg>
                                </span>
                                <span class="dislike-count">0</span>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($canComment): ?>
                    <p class="comment-limit-note"></p>
                    <form method="POST" class="comment-form lightbox-comment-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="media_id" value="">
                        <div class="comment-form-row">
                            <textarea name="comment_text" placeholder="Schreibe einen Kommentar..." maxlength="<?php echo (int) $commentTextMaxLength; ?>" data-maxlength="<?php echo (int) $commentTextMaxLength; ?>" rows="1"></textarea>
                            <input type="hidden" name="submit_comment" value="1">
                            <button type="submit" name="submit_comment" aria-label="Kommentieren">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                    <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="comment-form comment-form--locked" aria-disabled="true">
                        <div class="comment-form-row">
                            <textarea
                                class="comment-form-locked-input"
                                readonly
                                aria-readonly="true"
                                data-auth-redirect="comment"
                                placeholder="Schreibe einen Kommentar... (Anmeldung erforderlich)"
                                rows="1"
                            ></textarea>
                            <button type="button" class="interaction-button interaction-button--locked" data-auth-redirect="comment" aria-disabled="true" aria-label="Zum Login">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                    <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="comment-list lightbox-comment-list">
                    <h3>Kommentare</h3>
                    <div class="comment-items" id="lightbox-comment-items">
                        <?php if (!$canViewComments): ?>
                            <p>Keine Berechtigung, Kommentare zu sehen.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

<script>
(() => {
    // ESC muss immer funktionieren (Firefox/Safari-kompatibel), auch wenn später ein JS-Teil scheitert.
    window.addEventListener('keydown', function (e) {
        var key = e && (e.key || e.code) ? (e.key || e.code) : '';
        var isEsc = key === 'Escape' || key === 'Esc' || e.keyCode === 27;
        if (!isEsc) return;
        var lb = document.getElementById('lightbox');
        if (lb && lb.classList.contains('is-open')) {
            // Immer zentrale Close-Routine nutzen, damit Scroll-Lock sauber gelöst wird.
            if (typeof closeLightbox === 'function') {
                closeLightbox();
            } else {
                lb.classList.remove('is-open');
                lb.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                document.body.style.paddingRight = '';
            }
            return;
        }
        window.location.href = 'index.php?page=project_grid';
    }, true);

    const deleteForm = document.getElementById('delete-project-form');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            const ok = window.confirm('Dieses Projekt wirklich löschen?');
            if (!ok) {
                event.preventDefault();
            }
        });
    }
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        if (!target || !target.tagName || target.tagName.toUpperCase() !== 'TEXTAREA') return;
        if (!target.closest || !target.closest('.comment-form')) return;
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            const form = target.closest('form');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });

    const lightbox = document.getElementById('lightbox');
    const lightboxMedia = lightbox ? lightbox.querySelector('.lightbox-media') : null;
    const lightboxPanel = lightbox ? lightbox.querySelector('.lightbox-panel') : null;
    const closeBtn = lightbox ? lightbox.querySelector('.lightbox-close') : null;
    const statusEl = document.getElementById('interaction-status');
    const commentItems = document.getElementById('lightbox-comment-items');
    const likeCountEl = lightboxPanel ? lightboxPanel.querySelector('.like-count') : null;
    const dislikeCountEl = lightboxPanel ? lightboxPanel.querySelector('.dislike-count') : null;
    const lightboxInteractionForm = lightbox ? lightbox.querySelector('.lightbox-interaction-form') : null;
    const lightboxCommentForm = lightbox ? lightbox.querySelector('.lightbox-comment-form') : null;
    const body = document.body;
    let bodyOverflow = '';
    let bodyPaddingRight = '';
    let bodyPosition = '';
    let bodyTop = '';
    let bodyWidth = '';
    let scrollYBeforeLock = 0;
    let activeMediaId = null;

    async function submitAjaxForm(form, submitter) {
        const formData = new FormData(form);
        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value);
        }
        const actionUrl = form.dataset.ajaxAction || form.action || window.location.href;
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        });
        if (!response.ok) {
            throw new Error('Serverfehler');
        }
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return response.json();
        }
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('Ungültige Antwort vom Server');
        }
    }

    function setActiveMediaId(mediaId) {
        activeMediaId = mediaId;
        lightbox.querySelectorAll('input[name="media_id"]').forEach((input) => {
            input.value = mediaId || '';
        });
    }

    function updateMetricCount(mediaId, kind, value) {
        if (!mediaId) return;
        const metric = document.querySelector(`.media-metrics[data-media-id="${mediaId}"] .metric-count[data-kind="${kind}"]`);
        if (metric) {
            metric.textContent = value;
        }
    }

    function updateHoverPreview(mediaId, html) {
        if (!mediaId) return;
        const container = document.querySelector(`.media-hover-comments[data-media-id="${mediaId}"]`);
        if (container) {
            container.innerHTML = html || '';
            setupHoverRotationFor(container);
        }
    }

    async function loadMediaData(mediaId) {
        if (!mediaId) return;
        const formData = new FormData();
        formData.set('ajax', '1');
        formData.set('load_media', '1');
        formData.set('media_id', mediaId);
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        });
        if (!response.ok) {
            throw new Error('Serverfehler');
        }
        const data = await response.json();
        if (data.ok === false) {
            if (statusEl) statusEl.textContent = data.message || 'Fehler beim Laden.';
            return;
        }
        if (likeCountEl && data.likeCount !== undefined) likeCountEl.textContent = data.likeCount;
        if (dislikeCountEl && data.dislikeCount !== undefined) dislikeCountEl.textContent = data.dislikeCount;
        if (commentItems && typeof data.commentsHtml === 'string') {
            commentItems.innerHTML = data.commentsHtml;
            setupTextareas(commentItems);
            setupCommentExpanders(commentItems);
        }
        if (typeof data.commentLimit === 'number') {
            const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
            if (note) {
                note.textContent = data.commentLimitReached && data.commentLimit > 0
                    ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                    : '';
            }
            if (lightboxCommentForm) {
                const textarea = lightboxCommentForm.querySelector('textarea');
                const button = lightboxCommentForm.querySelector('button[type="submit"]');
                if (textarea) textarea.disabled = !!data.commentLimitReached;
                if (button) button.disabled = !!data.commentLimitReached;
            }
        }
        if (data.currentUserInteraction !== undefined) {
            const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
            const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
            if (likeBtn) {
                const isActive = data.currentUserInteraction === 'like';
                likeBtn.classList.toggle('is-active', isActive);
                likeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
            if (dislikeBtn) {
                const isActive = data.currentUserInteraction === 'dislike';
                dislikeBtn.classList.toggle('is-active', isActive);
                dislikeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        }
        if (data.commentCount !== undefined) {
            updateMetricCount(mediaId, 'comment', data.commentCount);
        }
    }

    let lastSubmitter = null;
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const button = target.closest('button[type="submit"], input[type="submit"]');
        if (!button) return;
        lastSubmitter = button;
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.ajax) return;
        event.preventDefault();
            if (statusEl) statusEl.textContent = 'Speichern...';
        try {
            const submitter = event.submitter || (lastSubmitter && form.contains(lastSubmitter) ? lastSubmitter : null);
            const data = await submitAjaxForm(form, submitter);
            if (data.ok === false) {
                if (statusEl) statusEl.textContent = data.message || 'Fehler beim Speichern.';
                return;
            }
            if (data.action === 'interaction') {
                const mediaId = data.mediaId || activeMediaId;
                if (likeCountEl) likeCountEl.textContent = data.likeCount ?? likeCountEl.textContent;
                if (dislikeCountEl) dislikeCountEl.textContent = data.dislikeCount ?? dislikeCountEl.textContent;
                if (data.currentUserInteraction !== undefined) {
                    const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
                    const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
                    if (likeBtn) {
                        const isActive = data.currentUserInteraction === 'like';
                        likeBtn.classList.toggle('is-active', isActive);
                        likeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                    if (dislikeBtn) {
                        const isActive = data.currentUserInteraction === 'dislike';
                        dislikeBtn.classList.toggle('is-active', isActive);
                        dislikeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                }
                if (mediaId) {
                    if (data.likeCount !== undefined) updateMetricCount(mediaId, 'like', data.likeCount);
                    if (data.dislikeCount !== undefined) updateMetricCount(mediaId, 'dislike', data.dislikeCount);
                }
                if (statusEl) statusEl.textContent = '';
            } else if (data.action === 'comment') {
                if (commentItems && typeof data.commentsHtml === 'string') {
                    commentItems.innerHTML = data.commentsHtml;
                    setupTextareas(commentItems);
                    setupCommentExpanders(commentItems);
                }
                if (typeof data.commentLimit === 'number') {
                    const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
                    if (note) {
                        note.textContent = data.commentLimitReached && data.commentLimit > 0
                            ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                            : '';
                    }
                }
            if (data.commentLimitReached !== undefined) {
                    const mainForm = lightboxCommentForm;
                    if (mainForm) {
                        const textarea = mainForm.querySelector('textarea');
                        const button = mainForm.querySelector('button[type="submit"]');
                        if (textarea) textarea.disabled = data.commentLimitReached;
                        if (button) button.disabled = data.commentLimitReached;
                    }
                    if (commentItems) {
                        commentItems.querySelectorAll('.reply-form textarea').forEach((el) => {
                            el.disabled = data.commentLimitReached;
                        });
                        commentItems.querySelectorAll('.reply-form button[type="submit"]').forEach((el) => {
                            el.disabled = data.commentLimitReached;
                        });
                    }
                    if (data.commentLimit !== undefined) {
                        const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
                        if (note) {
                            note.textContent = data.commentLimitReached && data.commentLimit > 0
                                ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                                : '';
                        }
                    }
                }
                if (form) {
                    const textarea = form.querySelector('textarea');
                    if (textarea) {
                        textarea.value = '';
                        autoGrowTextarea(textarea);
                    }
                }
                if (data.commentCount !== undefined) {
                    const mediaId = data.mediaId || activeMediaId;
                    if (mediaId) updateMetricCount(mediaId, 'comment', data.commentCount);
                }
                if (data.hoverHtml !== undefined) {
                    const mediaId = data.mediaId || activeMediaId;
                    if (mediaId) updateHoverPreview(mediaId, data.hoverHtml);
                }
                if (statusEl) statusEl.textContent = data.message || 'Kommentar gespeichert.';
            }
        } catch (error) {
            if (statusEl) statusEl.textContent = 'Fehler beim Speichern.';
        }
    });

    function autoGrowTextarea(textarea) {
        textarea.style.height = 'auto';
        const cs = getComputedStyle(textarea);
        const minH = parseFloat(cs.minHeight) || 0;
        const maxHPx = parseFloat(cs.maxHeight);
        const cap = Number.isFinite(maxHPx) && maxHPx > 0 ? maxHPx : Number.POSITIVE_INFINITY;
        const next = Math.min(Math.max(textarea.scrollHeight, minH), cap);
        textarea.style.height = `${next}px`;
    }

    function setupTextareas(root) {
        const textareas = root.querySelectorAll('.comment-form textarea');
        textareas.forEach((textarea) => {
            if (textarea.dataset.enhanced === '1') return;
            textarea.dataset.enhanced = '1';
            autoGrowTextarea(textarea);
            textarea.addEventListener('input', (event) => {
                const max = parseInt(textarea.dataset.maxlength || '400', 10);
                if (textarea.value.length >= max && event.inputType && event.inputType.startsWith('insert')) {
                    if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
                } else if (statusEl && statusEl.textContent === 'Keine Romane Schreiben bitte') {
                    statusEl.textContent = '';
                }
                autoGrowTextarea(textarea);
            });

            textarea.addEventListener('paste', (event) => {
                const max = parseInt(textarea.dataset.maxlength || '400', 10);
                const text = (event.clipboardData || window.clipboardData).getData('text');
                const selection = textarea.selectionEnd - textarea.selectionStart;
                const available = max - (textarea.value.length - selection);
                if (text.length > available) {
                    event.preventDefault();
                    const insert = text.slice(0, Math.max(0, available));
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    textarea.setRangeText(insert, start, end, 'end');
                    if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
                    autoGrowTextarea(textarea);
                }
            });
        });
    }

    function setupCommentExpanders(root) {
        const texts = root.querySelectorAll('.comment-text');
        texts.forEach((textEl) => {
            if (textEl.dataset.clampReady === '1') return;
            textEl.dataset.clampReady = '1';

            textEl.classList.add('is-collapsed');

            const needsClamp = textEl.scrollHeight > textEl.clientHeight + 1;
            if (!needsClamp) {
                textEl.classList.remove('is-collapsed');
                return;
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'comment-expand';
            btn.textContent = 'Mehr anzeigen';
            btn.addEventListener('click', () => {
                const isCollapsed = textEl.classList.contains('is-collapsed');
                if (isCollapsed) {
                    textEl.classList.remove('is-collapsed');
                    btn.textContent = 'Weniger';
                } else {
                    textEl.classList.add('is-collapsed');
                    btn.textContent = 'Mehr anzeigen';
                }
            });

            textEl.insertAdjacentElement('afterend', btn);
        });
    }

    setupTextareas(document);
    setupCommentExpanders(document);

    function setupHoverRotationFor(container) {
        if (!container) return;
        const comments = Array.from(container.querySelectorAll('.hover-comment'));
        if (comments.length === 0) return;
        comments.forEach((item) => {
            item.classList.remove('is-active');
            item.classList.remove('is-leaving');
        });
        comments[0].classList.add('is-active');
        container.dataset.hoverIndex = '0';
    }

    function rotateHoverComment(container) {
        const comments = Array.from(container.querySelectorAll('.hover-comment'));
        if (comments.length <= 1) return false;
        const currentIndex = parseInt(container.dataset.hoverIndex || '0', 10) || 0;
        const nextIndex = (currentIndex + 1) % comments.length;
        const current = comments[currentIndex];
        if (!current) return false;
        const next = comments[nextIndex];
        if (!next || current === next) return true;
        current.classList.remove('is-active');
        current.classList.add('is-leaving');
        next.classList.remove('is-leaving');
        next.classList.add('is-active');
        window.setTimeout(() => {
            current.classList.remove('is-leaving');
        }, 260);
        container.dataset.hoverIndex = String(nextIndex);
        return true;
    }

    document.querySelectorAll('.media-hover-comments').forEach((container) => {
        setupHoverRotationFor(container);
        const card = container.closest('.media-item');
        if (!card) return;
        let intervalId = null;
        card.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'touch') return;
            if (intervalId) return;
            setupHoverRotationFor(container);
            intervalId = window.setInterval(() => {
                const keepGoing = rotateHoverComment(container);
                if (!keepGoing && intervalId) {
                    window.clearInterval(intervalId);
                    intervalId = null;
                }
            }, 2500);
        });
        card.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'touch') return;
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
            const comments = Array.from(container.querySelectorAll('.hover-comment'));
            comments.forEach((item) => {
                item.classList.remove('is-active');
                item.classList.remove('is-leaving');
            });
            container.dataset.hoverIndex = '-1';
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const toggle = target.closest('.reply-toggle');
        if (!toggle) return;
        const comment = toggle.closest('.comment');
        if (!comment) return;
        const form = comment.querySelector('.reply-form');
        if (!form) return;
        form.classList.toggle('is-open');
        if (form.classList.contains('is-open')) {
            const textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
                autoGrowTextarea(textarea);
            }
        }
    });

    function resetLightboxState() {
        if (likeCountEl) likeCountEl.textContent = '0';
        if (dislikeCountEl) dislikeCountEl.textContent = '0';
        if (commentItems) commentItems.innerHTML = '';
        const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
        if (note) note.textContent = '';
        const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
        const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
        if (likeBtn) likeBtn.classList.remove('is-active');
        if (dislikeBtn) dislikeBtn.classList.remove('is-active');
    }

    function lockBodyScroll() {
        if (body.dataset.scrollLock === '1') return;
        bodyOverflow = body.style.overflow;
        bodyPaddingRight = body.style.paddingRight;
        bodyPosition = body.style.position;
        bodyTop = body.style.top;
        bodyWidth = body.style.width;
        scrollYBeforeLock = window.scrollY || window.pageYOffset || 0;
        const scrollBarWidth = window.innerWidth - document.documentElement.clientWidth;
        // Robust (Mobile Safari): body fixieren statt nur overflow hidden
        body.style.position = 'fixed';
        body.style.top = `-${scrollYBeforeLock}px`;
        body.style.width = '100%';
        body.style.overflow = 'hidden';
        if (scrollBarWidth > 0) {
            body.style.paddingRight = `${scrollBarWidth}px`;
        }
        body.dataset.scrollLock = '1';
    }

    function unlockBodyScroll() {
        if (body.dataset.scrollLock !== '1') return;
        body.style.overflow = bodyOverflow;
        body.style.paddingRight = bodyPaddingRight;
        body.style.position = bodyPosition;
        body.style.top = bodyTop;
        body.style.width = bodyWidth;
        window.scrollTo(0, scrollYBeforeLock || 0);
        delete body.dataset.scrollLock;
    }

    function getLightboxVideo() {
        return lightboxMedia ? lightboxMedia.querySelector('video') : null;
    }

    function toggleLightboxMute() {
        const video = getLightboxVideo();
        if (!video) return;
        video.muted = !video.muted;
    }

    function isSpaceKey(e) {
        return e.key === ' ' || e.code === 'Space';
    }

    function isLightboxOpen() {
        return lightbox && lightbox.classList.contains('is-open');
    }

    function toggleLightboxPlayPause() {
        const video = getLightboxVideo();
        if (!video) return;
        if (video.paused) {
            const playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }
        } else {
            video.pause();
        }
    }

    function stopGalleryTilePreview() {
        document.querySelectorAll('.page-project_detail .media-item.is-video-previewing').forEach((el) => {
            el.classList.remove('is-video-previewing');
            const tileVideo = el.querySelector('video');
            if (!tileVideo) return;
            tileVideo.pause();
            try {
                tileVideo.currentTime = 0;
            } catch (e) {
                /* ignore */
            }
        });
    }

    function blurLightboxTriggerFocus() {
        const active = document.activeElement;
        if (active instanceof HTMLElement && active.closest('.media-item')) {
            active.blur();
        }
    }

    function shouldHandleLightboxSpace(e) {
        const target = e.target;
        const isFormField = target instanceof Element
            && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable);
        return isLightboxOpen() && !isFormField && isSpaceKey(e) && !!getLightboxVideo();
    }

    function handleLightboxSpaceKeydown(e) {
        if (!shouldHandleLightboxSpace(e)) return;
        e.preventDefault();
        e.stopPropagation();
        toggleLightboxPlayPause();
    }

    function handleLightboxSpaceKeyup(e) {
        if (!shouldHandleLightboxSpace(e)) return;
        e.preventDefault();
        e.stopPropagation();
    }

    function openLightbox(type, src, mediaId) {
        if (!lightbox || !lightboxMedia) {
            return;
        }
        lightboxMedia.innerHTML = '';
        if (type === 'video') {
            const video = document.createElement('video');
            video.disablePictureInPicture = true;
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            video.muted = true;
            lightboxMedia.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Bild';
            lightboxMedia.appendChild(img);
        }
        setActiveMediaId(mediaId || '');
        resetLightboxState();
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        lockBodyScroll();
        stopGalleryTilePreview();
        blurLightboxTriggerFocus();
        if (mediaId) {
            loadMediaData(mediaId).catch(() => {});
        }
    }

    function closeLightbox() {
        if (!lightbox || !lightboxMedia) {
            return;
        }
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxMedia.innerHTML = '';
        setActiveMediaId('');
        resetLightboxState();
        unlockBodyScroll();
    }

    function redirectToLogin() {
        const next = window.location.href;
        window.location.href = `index.php?page=login&err=forbidden&next=${encodeURIComponent(next)}`;
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const locked = target.closest('[data-auth-redirect]');
        if (!locked) return;
        event.preventDefault();
        redirectToLogin();
    }, true);

    document.addEventListener('focusin', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (!target.closest('[data-auth-redirect]')) return;
        redirectToLogin();
    }, true);

    document.querySelectorAll('.media-item[data-src]').forEach((item) => {
        item.addEventListener('click', () => {
            openLightbox(item.dataset.type, item.dataset.src, item.dataset.mediaId || '');
        });
    });

    document.querySelectorAll('.media-card').forEach((card) => {
        if (!card.querySelector('.media-item[data-src]')) return;

        let rafId = 0;
        let lastEvent = null;

        function applyTilt() {
            rafId = 0;
            if (!lastEvent) return;
            const rect = card.getBoundingClientRect();
            const x = Math.min(Math.max((lastEvent.clientX - rect.left) / rect.width, 0), 1);
            const y = Math.min(Math.max((lastEvent.clientY - rect.top) / rect.height, 0), 1);
            const rx = (y - 0.5) * 18;
            const ry = (0.5 - x) * 20;

            card.style.setProperty('--rx', `${rx}deg`);
            card.style.setProperty('--ry', `${ry}deg`);
            card.style.setProperty('--mx', `${x * 100}%`);
            card.style.setProperty('--my', `${y * 100}%`);
            card.classList.add('is-tilting');
        }

        card.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'touch') return;
            lastEvent = event;
            if (!rafId) {
                rafId = window.requestAnimationFrame(applyTilt);
            }
        });

        card.addEventListener('pointerleave', () => {
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = 0;
            }
            lastEvent = null;
            card.classList.remove('is-tilting');
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
            card.style.removeProperty('--mx');
            card.style.removeProperty('--my');
        });
    });

    function getLightboxItems() {
        return Array.from(document.querySelectorAll('.media-item[data-src]'));
    }

    function getActiveIndex(items) {
        if (!items.length) return -1;
        if (activeMediaId) {
            const idIndex = items.findIndex((el) => (el.dataset.mediaId || '') === activeMediaId);
            if (idIndex >= 0) return idIndex;
        }
        const current = lightboxMedia.querySelector('img, video');
        if (current) {
            const src = current.getAttribute('src') || '';
            const srcIndex = items.findIndex((el) => (el.dataset.src || '') === src);
            if (srcIndex >= 0) return srcIndex;
        }
        return -1;
    }

    function navigateLightbox(delta) {
        const items = getLightboxItems();
        if (!items.length) return;
        const currentIndex = getActiveIndex(items);
        if (currentIndex < 0) return;
        const nextIndex = currentIndex + delta;
        if (nextIndex < 0 || nextIndex >= items.length) return;
        const item = items[nextIndex];
        openLightbox(item.dataset.type, item.dataset.src, item.dataset.mediaId || '');
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }
    if (lightboxMedia) {
        lightboxMedia.addEventListener('click', (e) => {
            const target = e.target;
            if (!(target instanceof Element)) return;
            if (target.tagName === 'IMG') {
                closeLightbox();
            }
        });
    }
    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
    }

    document.addEventListener('keydown', handleLightboxSpaceKeydown, true);
    document.addEventListener('keyup', handleLightboxSpaceKeyup, true);

    document.addEventListener('keydown', (e) => {
        const target = e.target;
        const isFormField = target instanceof Element
            && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable);
        if (e.key === 'Escape') {
            if (lightbox && lightbox.classList.contains('is-open')) {
                closeLightbox();
            } else {
                window.location.href = 'index.php?page=project_grid';
            }
            return;
        }
        if (
            lightbox
            && lightbox.classList.contains('is-open')
            && !isFormField
            && (e.key === 'm' || e.key === 'M')
            && getLightboxVideo()
        ) {
            e.preventDefault();
            toggleLightboxMute();
            return;
        }
        const isDesktop = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!isDesktop || isFormField) return;
        if (lightbox && lightbox.classList.contains('is-open')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateLightbox(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateLightbox(1);
            }
        }
    });
})();
</script>
