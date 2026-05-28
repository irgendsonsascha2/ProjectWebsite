<?php
if (!headers_sent()) {
    ob_start();
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/request.php';

$commentTextMaxLength = defined('COMMENT_TEXT_MAX_LENGTH') ? (int) COMMENT_TEXT_MAX_LENGTH : 400;

$isLoggedIn = isset($_SESSION['user_id']);

// --- DATENBANK & PROJEKT LADEN ---
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$projectObjectId = req_get_objectid('id');
if ($projectObjectId === null) {
    echo "Projekt nicht gefunden.";
    return;
}
$ajaxActionUrl = 'index.php?page=project_detail&id=' . urlencode((string) ($projectObjectId));

try {
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
if (req_post_bool('ajax')) {
    $isAjax = true;
} elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $isAjax = in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['xmlhttprequest', 'fetch'], true);
} elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $isAjax = true;
}

function parse_media_id($raw) {
    return req_objectid_from_scalar($raw);
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
            <p class="author"><?php echo comment_author_display_html($comment); ?></p>
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
                        <p class="author"><?php echo comment_author_display_html($reply); ?></p>
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
        $hoverRoleKey = comment_author_role_key($comment);
        $text = $comment['text'] ?? '';
        ?>
        <div class="hover-comment">
            <span class="hover-author"><?php echo htmlspecialchars($author); ?><?php if ($hoverRoleKey !== ''): ?> <span class="comment-role"><?php echo htmlspecialchars(role_display_label($hoverRoleKey)); ?></span><?php endif; ?></span>
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
    authz_require_login();
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
    authz_require_login();
    authz_require_active_account();
    if (! rate_limit_interaction_allow()) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'interaction',
                'message' => 'Zu viele Aktionen — bitte kurz warten.',
            ]);
            exit();
        }
        header('Location: '.$_SERVER['REQUEST_URI'].'&interaction_err=throttle');
        exit();
    }
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
    authz_require_login();
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
        'project_id' => $projectObjectId,
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
    authz_require_login();
    authz_require_active_account();
    if (! rate_limit_comment_allow()) {
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => 'Zu viele Kommentare — bitte kurz warten.',
            ]);
            exit();
        }
        header('Location: '.$_SERVER['REQUEST_URI'].'&comment_err=throttle');
        exit();
    }
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

    try {
        $db->comments->insertOne($payload);
    } catch (Throwable $insertError) {
        $failMessage = 'Kommentar konnte nicht gespeichert werden.';
        if (app_debug_enabled()) {
            $failMessage .= ' (' . $insertError->getMessage() . ')';
        }
        if ($isAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'action' => 'comment',
                'message' => $failMessage,
            ]);
            exit();
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }
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

