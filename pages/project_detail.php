<?php
if (!headers_sent()) {
    ob_start();
}
require_once __DIR__ . '/../includes/bootstrap.php';

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
    if (($project['is_draft'] ?? false) === true) {
        $isOwner = $isLoggedIn && isset($project['author_id']) && (string)$project['author_id'] === $_SESSION['user_id'];
        if (!$isOwner) {
            echo "Projekt existiert nicht.";
            return;
        }
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
if (!$canViewProjects) {
    echo "Du hast keine Berechtigung, dieses Projekt anzusehen.";
    return;
}
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    $isAjax = true;
} elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $isAjax = in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['xmlhttprequest', 'fetch'], true);
} elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $isAjax = true;
}

function fetch_comments_with_users($db, $projectObjectId) {
    $commentsCursor = $db->comments->aggregate([
        ['$match' => ['project_id' => $projectObjectId]],
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'user_id',
            'foreignField' => '_id',
            'as' => 'user_info'
        ]],
        ['$unwind' => '$user_info'],
        ['$sort' => ['created_at' => 1]]
    ]);
    return iterator_to_array($commentsCursor);
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

function render_comment_items($comments, $commentLimitReached, $commentLimit, $ajaxActionUrl, $currentUserId, $currentUserRole, $deleteRolesAllowed, $canDeleteOthers, $canComment) {
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
            <p><?php echo nl2br(htmlspecialchars($comment['text'])); ?></p>
            <?php if ($canDelete): ?>
                <form method="POST" class="comment-delete-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                    <button type="submit" name="delete_comment" value="1" class="comment-delete-button">Löschen</button>
                </form>
            <?php endif; ?>
            <?php if ($canComment): ?>
                <button type="button" class="reply-toggle" data-reply-to="<?php echo htmlspecialchars($commentId); ?>">Antworten</button>
                <form method="POST" class="comment-form reply-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="parent_comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                    <textarea name="comment_text" placeholder="Antwort schreiben..." maxlength="400" data-maxlength="400" <?php echo $commentLimitReached ? 'disabled' : ''; ?>></textarea>
                    <input type="hidden" name="submit_comment" value="1">
                    <button type="submit" name="submit_comment" aria-label="Antworten" <?php echo $commentLimitReached ? 'disabled' : ''; ?>>
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                        </svg>
                    </button>
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
                        <p><?php echo nl2br(htmlspecialchars($reply['text'])); ?></p>
                        <?php if ($replyCanDelete): ?>
                            <form method="POST" class="comment-delete-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                                <input type="hidden" name="ajax" value="1">
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

if ($canDeleteProjects && isset($_POST['delete_project'])) {
    delete_project_files($project);
    $db->projects->deleteOne(['_id' => $projectObjectId]);
    $db->likes->deleteMany(['project_id' => $projectObjectId]);
    $db->comments->deleteMany(['project_id' => $projectObjectId]);
    header('Location: index.php?page=project_grid');
    exit();
}

// --- LOGIK: LIKE / DISLIKE ---
if ($isLoggedIn && can('like_dislike') && isset($_POST['interaction'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $type = $_POST['interaction']; // 'like' or 'dislike'

    $existing = $db->likes->findOne(['project_id' => $projectObjectId, 'user_id' => $userId]);

    if ($type === 'like' || $type === 'dislike') {
        if ($existing && ($existing['type'] ?? null) === $type) {
            // Nochmal klicken => Vote entfernen
            $db->likes->deleteOne(['project_id' => $projectObjectId, 'user_id' => $userId]);
        } else {
            // Wechsel oder erster Vote
            $db->likes->deleteOne(['project_id' => $projectObjectId, 'user_id' => $userId]);
            $db->likes->insertOne([
                'project_id' => $projectObjectId,
                'user_id' => $userId,
                'type' => $type,
                'created_at' => new UTCDateTime()
            ]);
        }
    }
    if ($isAjax) {
        $likeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'like']) : 0;
        $dislikeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'dislike']) : 0;
        $currentUserLike = $db->likes->findOne(['project_id' => $projectObjectId, 'user_id' => $userId]);
        $currentUserInteraction = $currentUserLike['type'] ?? null;
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'action' => 'interaction',
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
if ($isLoggedIn && isset($_POST['delete_comment'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $commentIdRaw = trim($_POST['comment_id'] ?? '');
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

    $comment = $db->comments->findOne(['_id' => $commentId, 'project_id' => $projectObjectId]);
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

    $canDeleteOwn = can('comment') && (string)$comment['user_id'] === (string)$userId;
    $canDeleteOthers = can('delete_comments');
    $deleteRolesAllowed = [];
    $roleData = null;
    if (isset($_SESSION['role'])) {
        $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
        if ($canDeleteOthers && $roleData && isset($roleData['comment_delete_roles'])) {
            $deleteRolesAllowed = is_array($roleData['comment_delete_roles']) ? $roleData['comment_delete_roles'] : iterator_to_array($roleData['comment_delete_roles']);
        }
    }
    $allowed = $canDeleteOwn;
    if (!$allowed && $canDeleteOthers) {
        $author = $db->users->findOne(['_id' => $comment['user_id']], ['projection' => ['role' => 1]]);
        $authorRole = $author['role'] ?? '';
        if ($deleteRolesAllowed === ['*'] || in_array($authorRole, $deleteRolesAllowed, true)) {
            $allowed = true;
        }
    }

    if (!$allowed) {
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
        '$or' => [
            ['_id' => $commentId],
            ['parent_comment_id' => $commentId]
        ]
    ]);
    if ($isAjax) {
        $comments = fetch_comments_with_users($db, $projectObjectId);
        $userCommentCount = $db->comments->countDocuments([
            'project_id' => $projectObjectId,
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
            'commentsHtml' => render_comment_items(
                $comments,
                $commentLimitReached,
                $commentLimit,
                $ajaxActionUrl,
                (string)$userId,
                $_SESSION['role'] ?? '',
                $deleteRolesAllowed,
                $canDeleteOthers,
                $canComment
            ),
            'commentLimitReached' => $commentLimitReached,
            'commentLimit' => $commentLimit
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

if ($isLoggedIn && can('comment') && isset($_POST['submit_comment'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $commentText = trim($_POST['comment_text']);
    $commentLimit = 400;
    $parentCommentIdRaw = trim($_POST['parent_comment_id'] ?? '');
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
            'project_id' => $projectObjectId
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

    $payload = [
        'project_id' => $projectObjectId,
        'user_id' => $userId,
        'text' => $commentText,
        'created_at' => new UTCDateTime(),
        'updated_at' => new UTCDateTime()
    ];
    if ($parentCommentId !== null) {
        $payload['parent_comment_id'] = $parentCommentId;
    }

    $db->comments->insertOne($payload);
    $message = "Kommentar gespeichert!";
    if ($isAjax) {
        $comments = fetch_comments_with_users($db, $projectObjectId);
        $userCommentCount = $db->comments->countDocuments([
            'project_id' => $projectObjectId,
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
            'commentsHtml' => render_comment_items(
                $comments,
                $commentLimitReached,
                $commentLimit,
                $ajaxActionUrl,
                (string)$userId,
                $_SESSION['role'] ?? '',
                $deleteRolesAllowed,
                $canDeleteOthers,
                $canComment
            ),
            'commentLimitReached' => $commentLimitReached,
            'commentLimit' => $commentLimit
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- DATEN FÜR DIE ANZEIGE LADEN ---
$likeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'like']) : 0;
$dislikeCount = $canViewLikes ? $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'dislike']) : 0;
$userLikeType = null;
if ($isLoggedIn && can('like_dislike')) {
    $userLike = $db->likes->findOne([
        'project_id' => $projectObjectId,
        'user_id' => new ObjectId($_SESSION['user_id'])
    ]);
    $userLikeType = $userLike['type'] ?? null;
}

// Kommentare mit User-Infos laden
$comments = $canViewComments ? fetch_comments_with_users($db, $projectObjectId) : [];
$commentLimitReached = false;
$commentLimit = 0;
$currentUserId = null;
$currentUserRole = $_SESSION['role'] ?? '';
$canDeleteOthers = $isLoggedIn && can('delete_comments');
$deleteRolesAllowed = [];
if ($isLoggedIn) {
    $currentUserId = $_SESSION['user_id'];
    $userCommentCount = $db->comments->countDocuments([
        'project_id' => $projectObjectId,
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
$mediaLimit = isset($_GET['media_limit']) ? (int)$_GET['media_limit'] : 30;
$mediaLimit = max(0, min($mediaLimit, 120));
if ($mediaLimit === 0) {
    $mediaLimit = 30;
}
$gallerySlice = array_slice($gallery, 0, $mediaLimit);
$hasMore = count($gallery) > $mediaLimit;
?>

<link rel="stylesheet" href="style/project_detail.css">

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
                    ?>
                    <?php if ($url): ?>
                        <div class="media-card">
                            <button class="media-item" data-type="<?php echo htmlspecialchars($type); ?>" data-src="<?php echo htmlspecialchars($url); ?>">
                                <?php if ($type === 'video'): ?>
                                    <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline></video>
                                    <span class="media-badge">Video</span>
                                    <span class="media-play">▶</span>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy">
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


    <!-- INTERACTION SECTION -->
    <section class="interaction-section">
        <h2>Interaktionen</h2>
        <p class="interaction-status" id="interaction-status" role="status" aria-live="polite"></p>

        <!-- LIKES / DISLIKES -->
        <?php if (can('like_dislike')): ?>
            <form method="POST" class="interaction-buttons" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                <input type="hidden" name="ajax" value="1">
                <button type="submit" name="interaction" value="like" class="<?php echo $userLikeType === 'like' ? 'is-active' : ''; ?>" aria-pressed="<?php echo $userLikeType === 'like' ? 'true' : 'false'; ?>">
                    <span class="interaction-emoji" aria-hidden="true">🔥</span>
                    <?php if ($canViewLikes): ?>
                        <span class="like-count"><?php echo $likeCount; ?></span>
                    <?php endif; ?>
                </button>
                <button type="submit" name="interaction" value="dislike" class="<?php echo $userLikeType === 'dislike' ? 'is-active' : ''; ?>" aria-pressed="<?php echo $userLikeType === 'dislike' ? 'true' : 'false'; ?>">
                    <span class="interaction-emoji" aria-hidden="true">💩</span>
                    <?php if ($canViewLikes): ?>
                        <span class="dislike-count"><?php echo $dislikeCount; ?></span>
                    <?php endif; ?>
                </button>
            </form>
        <?php else: ?>
            <?php if ($canViewLikes): ?>
                <div class="interaction-buttons" aria-hidden="true">
                    <div>
                        <span class="interaction-emoji" aria-hidden="true">🔥</span>
                        <span class="like-count"><?php echo $likeCount; ?></span>
                    </div>
                    <div>
                        <span class="interaction-emoji" aria-hidden="true">💩</span>
                        <span class="dislike-count"><?php echo $dislikeCount; ?></span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- KOMMENTAR-FORMULAR -->
        <?php if ($canComment): ?>
            <h4>Dein Kommentar</h4>
            <p class="comment-limit-note">
                <?php if ($commentLimitReached && $commentLimit > 0): ?>
                    Kommentar-Limit erreicht (max. <?php echo (int)$commentLimit; ?> pro Nutzer).
                <?php endif; ?>
            </p>
            <form method="POST" class="comment-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                <input type="hidden" name="ajax" value="1">
                <textarea name="comment_text" placeholder="Schreibe einen Kommentar..." maxlength="400" data-maxlength="400" <?php echo $commentLimitReached ? 'disabled' : ''; ?>></textarea>
                <input type="hidden" name="submit_comment" value="1">
                <button type="submit" name="submit_comment" aria-label="Kommentieren" <?php echo $commentLimitReached ? 'disabled' : ''; ?>>
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                    </svg>
                </button>
            </form>
        <?php endif; ?>
    </section>


    <!-- KOMMENTAR-LISTE -->
    <section class="comment-list" id="comment-list">
        <h3>Kommentare</h3>
        <div class="comment-items" id="comment-items">
            <?php
                if ($canViewComments) {
                    echo render_comment_items(
                        $comments,
                        $commentLimitReached,
                        $commentLimit,
                        $ajaxActionUrl,
                        $currentUserId,
                        $currentUserRole,
                        $deleteRolesAllowed,
                        $canDeleteOthers,
                        $canComment
                    );
                } else {
                    echo '<p>Keine Berechtigung, Kommentare zu sehen.</p>';
                }
            ?>
        </div>
    </section>
</article>

<?php if ($canEdit): ?>
<a href="index.php?page=edit_project&id=<?php echo (string)$projectObjectId; ?>" class="fab fab-edit" title="Projekt bearbeiten">✎</a>
<?php endif; ?>

<?php if ($canDeleteProjects): ?>
<form method="POST" action="index.php?page=project_detail&id=<?php echo (string)$projectObjectId; ?>" class="fab fab-delete" id="delete-project-form">
    <button type="submit" name="delete_project" value="1" title="Projekt löschen" aria-label="Projekt löschen">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
            <path d="M9 4h6l1 2h4v2H4V6h4l1-2zm1 6h2v9h-2V10zm4 0h2v9h-2V10zM7 10h2v9H7V10z" fill="currentColor"/>
            <path d="M6 8h12l-1 12a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 8z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
    </button>
</form>
<?php endif; ?>

<div class="lightbox" id="lightbox" aria-hidden="true">
    <div class="lightbox-content" role="dialog" aria-modal="true">
        <button class="lightbox-close" type="button" aria-label="Schließen">×</button>
        <div class="lightbox-media"></div>
    </div>
</div>

<script>
(() => {
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
        if (!(target instanceof HTMLTextAreaElement)) return;
        if (!target.closest('.comment-form')) return;
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

    const statusEl = document.getElementById('interaction-status');
    const commentItems = document.getElementById('comment-items');
    const likeCountEl = document.querySelector('.like-count');
    const dislikeCountEl = document.querySelector('.dislike-count');

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
                if (likeCountEl) likeCountEl.textContent = data.likeCount ?? likeCountEl.textContent;
                if (dislikeCountEl) dislikeCountEl.textContent = data.dislikeCount ?? dislikeCountEl.textContent;
                if (data.currentUserInteraction !== undefined) {
                    const likeBtn = document.querySelector('button[name="interaction"][value="like"]');
                    const dislikeBtn = document.querySelector('button[name="interaction"][value="dislike"]');
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
                if (statusEl) statusEl.textContent = '';
            } else if (data.action === 'comment') {
                if (commentItems && typeof data.commentsHtml === 'string') {
                    commentItems.innerHTML = data.commentsHtml;
                    setupTextareas(commentItems);
                }
                if (typeof data.commentLimit === 'number') {
                    const note = document.querySelector('.interaction-section .comment-limit-note');
                    if (note && data.commentLimitReached && data.commentLimit > 0) {
                        note.textContent = `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`;
                    }
                }
            if (data.commentLimitReached !== undefined) {
                    const mainForm = document.querySelector('.interaction-section .comment-form:not(.reply-form)');
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
                        const note = document.querySelector('.interaction-section .comment-limit-note');
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
                if (statusEl) statusEl.textContent = data.message || 'Kommentar gespeichert.';
            }
        } catch (error) {
            if (statusEl) statusEl.textContent = 'Fehler beim Speichern.';
        }
    });

    function autoGrowTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
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

    setupTextareas(document);

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

    const lightbox = document.getElementById('lightbox');
    const lightboxMedia = lightbox.querySelector('.lightbox-media');
    const closeBtn = lightbox.querySelector('.lightbox-close');

    function openLightbox(type, src) {
        lightboxMedia.innerHTML = '';
        if (type === 'video') {
            const video = document.createElement('video');
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            lightboxMedia.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Bild';
            lightboxMedia.appendChild(img);
        }
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
    }

    function closeLightbox() {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxMedia.innerHTML = '';
    }

    document.querySelectorAll('.media-item[data-src]').forEach((item) => {
        item.addEventListener('click', () => {
            openLightbox(item.dataset.type, item.dataset.src);
        });
    });

    const mediaItems = Array.from(document.querySelectorAll('.media-item'));
    mediaItems.forEach((card) => {
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

    closeBtn.addEventListener('click', closeLightbox);
    lightboxMedia.addEventListener('click', (e) => {
        if (e.target && (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO')) {
            closeLightbox();
        }
    });
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (lightbox.classList.contains('is-open')) {
                closeLightbox();
            } else {
                window.location.href = 'index.php?page=project_grid';
            }
        }
    });
})();
</script>
