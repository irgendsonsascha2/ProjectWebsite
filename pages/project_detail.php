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
$isAjax = false;
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
        ['$sort' => ['updated_at' => -1]]
    ]);
    return iterator_to_array($commentsCursor);
}

function render_comment_items($comments) {
    ob_start();
    foreach ($comments as $comment) {
        ?>
        <div class="comment">
            <p class="author"><?php echo htmlspecialchars($comment['user_info']['username'] ?? $comment['user_info']['email']); ?></p>
            <p class="date"><?php echo $comment['updated_at']->toDateTime()->format('d.m.Y H:i'); ?></p>
            <p><?php echo nl2br(htmlspecialchars($comment['text'])); ?></p>
        </div>
        <?php
    }
    if (count($comments) === 0) {
        echo '<p>Noch keine Kommentare vorhanden.</p>';
    }
    return ob_get_clean();
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
        $likeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'like']);
        $dislikeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'dislike']);
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
if ($isLoggedIn && can('comment') && isset($_POST['submit_comment'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $commentText = trim($_POST['comment_text']);
    $commentLimit = 400;

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

    $db->comments->updateOne(
        ['project_id' => $projectObjectId, 'user_id' => $userId],
        [
            '$set' => [
                'text' => $commentText,
                'updated_at' => new UTCDateTime()
            ],
            '$setOnInsert' => [
                'created_at' => new UTCDateTime()
            ]
        ],
        ['upsert' => true]
    );
    $message = "Kommentar gespeichert!";
    if ($isAjax) {
        $comments = fetch_comments_with_users($db, $projectObjectId);
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'action' => 'comment',
            'message' => $message,
            'commentsHtml' => render_comment_items($comments)
        ]);
        exit();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- DATEN FÜR DIE ANZEIGE LADEN ---
$likeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'like']);
$dislikeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'dislike']);
$userLikeType = null;
if ($isLoggedIn && can('like_dislike')) {
    $userLike = $db->likes->findOne([
        'project_id' => $projectObjectId,
        'user_id' => new ObjectId($_SESSION['user_id'])
    ]);
    $userLikeType = $userLike['type'] ?? null;
}

// Kommentare mit User-Infos laden
$comments = fetch_comments_with_users($db, $projectObjectId);

// User's eigenen Kommentar finden
$userComment = null;
if($isLoggedIn) {
    $userComment = $db->comments->findOne(['project_id' => $projectObjectId, 'user_id' => new ObjectId($_SESSION['user_id'])]);
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
$ajaxActionUrl = 'pages/project_detail.php?id=' . urlencode($projectId);
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
    <?php if ($isLoggedIn && (can('like_dislike') || can('comment'))): ?>
        <section class="interaction-section">
            <h2>Interaktionen</h2>
            <p class="interaction-status" id="interaction-status" role="status" aria-live="polite"></p>

            <!-- LIKES / DISLIKES -->
            <?php if (can('like_dislike')): ?>
                <form method="POST" class="interaction-buttons" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <button type="submit" name="interaction" value="like" class="<?php echo $userLikeType === 'like' ? 'is-active' : ''; ?>" aria-pressed="<?php echo $userLikeType === 'like' ? 'true' : 'false'; ?>">
                        <span class="interaction-emoji" aria-hidden="true">🔥</span>
                        <span class="like-count"><?php echo $likeCount; ?></span>
                    </button>
                    <button type="submit" name="interaction" value="dislike" class="<?php echo $userLikeType === 'dislike' ? 'is-active' : ''; ?>" aria-pressed="<?php echo $userLikeType === 'dislike' ? 'true' : 'false'; ?>">
                        <span class="interaction-emoji" aria-hidden="true">💩</span>
                        <span class="dislike-count"><?php echo $dislikeCount; ?></span>
                    </button>
                </form>
            <?php endif; ?>

            <!-- KOMMENTAR-FORMULAR -->
            <?php if (can('comment')): ?>
                <h4>Dein Kommentar</h4>
                <form method="POST" class="comment-form" data-ajax="true" data-ajax-action="<?php echo htmlspecialchars($ajaxActionUrl); ?>">
                    <input type="hidden" name="ajax" value="1">
                    <textarea name="comment_text" placeholder="Schreibe einen Kommentar..." maxlength="400" data-maxlength="400"><?php echo htmlspecialchars($userComment['text'] ?? ''); ?></textarea>
                    <input type="hidden" name="submit_comment" value="1">
                    <button type="submit" name="submit_comment" aria-label="Kommentieren">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M2 21l21-9L2 3v7l15 2-15 2z" fill="currentColor"/>
                        </svg>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>


    <!-- KOMMENTAR-LISTE -->
    <section class="comment-list" id="comment-list">
        <h3>Kommentare</h3>
        <div class="comment-items" id="comment-items">
            <?php echo render_comment_items($comments); ?>
        </div>
    </section>
</article>

<?php if ($canEdit): ?>
<a href="index.php?page=edit_project&id=<?php echo (string)$projectObjectId; ?>" class="fab fab-edit" title="Projekt bearbeiten">✎</a>
<?php endif; ?>

<div class="lightbox" id="lightbox" aria-hidden="true">
    <div class="lightbox-content" role="dialog" aria-modal="true">
        <button class="lightbox-close" type="button" aria-label="Schließen">×</button>
        <div class="lightbox-media"></div>
    </div>
</div>

<script>
(() => {
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
    const commentTextarea = document.querySelector('.comment-form textarea');

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

    if (commentTextarea) {
        autoGrowTextarea(commentTextarea);
        commentTextarea.addEventListener('input', (event) => {
            const max = parseInt(commentTextarea.dataset.maxlength || '400', 10);
            if (commentTextarea.value.length >= max && event.inputType && event.inputType.startsWith('insert')) {
                if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
            } else if (statusEl && statusEl.textContent === 'Keine Romane Schreiben bitte') {
                statusEl.textContent = '';
            }
            autoGrowTextarea(commentTextarea);
        });

        commentTextarea.addEventListener('paste', (event) => {
            const max = parseInt(commentTextarea.dataset.maxlength || '400', 10);
            const text = (event.clipboardData || window.clipboardData).getData('text');
            const selection = commentTextarea.selectionEnd - commentTextarea.selectionStart;
            const available = max - (commentTextarea.value.length - selection);
            if (text.length > available) {
                event.preventDefault();
                const insert = text.slice(0, Math.max(0, available));
                const start = commentTextarea.selectionStart;
                const end = commentTextarea.selectionEnd;
                commentTextarea.setRangeText(insert, start, end, 'end');
                if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
                autoGrowTextarea(commentTextarea);
            }
        });
    }

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
