<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// --- HILFSFUNKTIONEN ---
if (!function_exists('can')) {
    function can($permission)
    {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}
$isLoggedIn = isset($_SESSION['user_id']);

// --- DATENBANK & PROJEKT LADEN ---
use MongoDB\BSON\ObjectId;

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    die("Projekt nicht gefunden.");
}

try {
    $projectObjectId = new ObjectId($projectId);
    // Die $db Variable wird von der index.php bereitgestellt
    $project = $db->projects->findOne(['_id' => $projectObjectId]);

    if (!$project) {
        die("Projekt existiert nicht.");
    }
} catch (Exception $e) {
    die("Ungültige Projekt-ID.");
}

// --- BERECHTIGUNGS-CHECK ---
$canEdit = false;
if ($isLoggedIn) {
    if (can('edit_all')) {
        $canEdit = true;
    } elseif (can('edit_own') && isset($project['author_id']) && (string)$project['author_id'] === $_SESSION['user_id']) {
        $canEdit = true;
    }
}
if (!$canEdit) {
    die("<h1>Zugriff verweigert</h1><p>Sie haben nicht die nötigen Rechte, um dieses Projekt zu bearbeiten.</p>");
}

$message = "";
$contentBaseDir = __DIR__ . '/../content';
$contentImageDir = $contentBaseDir . '/images';
$contentVideoDir = $contentBaseDir . '/videos';
$contentTmpDir = $contentBaseDir . '/tmp';

if (!is_dir($contentImageDir)) {
    mkdir($contentImageDir, 0755, true);
}
if (!is_dir($contentVideoDir)) {
    mkdir($contentVideoDir, 0755, true);
}

function media_target_dir($type, $imageDir, $videoDir) {
    return $type === 'video' ? $videoDir : $imageDir;
}

function media_temp_dir($type, $baseTmpDir, $userId, $projectId) {
    $subdir = $type === 'video' ? 'videos' : 'images';
    return $baseTmpDir . '/' . $userId . '/' . $projectId . '/' . $subdir;
}

function build_temp_url($type, $userId, $projectId, $filename) {
    $subdir = $type === 'video' ? 'videos' : 'images';
    return 'content/tmp/' . $userId . '/' . $projectId . '/' . $subdir . '/' . $filename;
}

function normalize_gallery_items($gallery) {
    $gallery = normalize_gallery($gallery);
    $normalized = [];
    foreach ($gallery as $item) {
        if (is_array($item)) {
            $normalized[] = $item;
        } elseif ($item instanceof Traversable) {
            $normalized[] = iterator_to_array($item);
        } else {
            $normalized[] = (array)$item;
        }
    }
    return $normalized;
}

function render_media_manager($workingGallery, $editActionUrl, $message, $showMessage = false) {
    ob_start();
    ?>
        <h2>Projekt‑Medien</h2>

        <?php if ($showMessage && $message): ?>
            <div class="alert media-alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($workingGallery)): ?>
            <div class="media-grid">
                <?php foreach ($workingGallery as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                    ?>
                    <?php if ($url): ?>
                        <div class="media-tile">
                            <?php if ($type === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline></video>
                                <span class="media-badge">Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy">
                            <?php endif; ?>
                            <div class="media-actions">
                                <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" data-ajax="true">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" name="move_media" value="1">↑</button>
                                </form>
                                <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" data-ajax="true">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" name="move_media" value="1">↓</button>
                                </form>
                            </div>
                            <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" class="media-delete" data-ajax="true">
                                <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                <button type="submit" name="delete_media">Löschen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Noch keine Medien vorhanden.</p>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" enctype="multipart/form-data" class="media-upload-form" id="media-upload-form" data-ajax="true">
            <label for="gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="gallery_files" name="gallery_files[]" multiple accept="image/*,video/*">
            <input type="hidden" name="upload_media" value="1">
        </form>
    <?php
    return ob_get_clean();
}

$sessionGalleryKey = 'edit_gallery_' . (string)$projectObjectId;
$sessionOriginalKey = 'edit_gallery_original_' . (string)$projectObjectId;
if (!isset($_SESSION[$sessionGalleryKey])) {
    $_SESSION[$sessionGalleryKey] = normalize_gallery_items($project['gallery'] ?? []);
    $_SESSION[$sessionOriginalKey] = normalize_gallery_items($project['gallery'] ?? []);
}

$workingGallery = normalize_gallery_items($_SESSION[$sessionGalleryKey] ?? []);
$_SESSION[$sessionGalleryKey] = $workingGallery;

// --- LOGIK: MEDIA REIHENFOLGE ---
if (isset($_POST['move_media']) && isset($_POST['media_index']) && isset($_POST['direction'])) {
    $index = (int)$_POST['media_index'];
    $direction = $_POST['direction'];
    $gallery = $workingGallery;
    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

    if (isset($gallery[$index]) && isset($gallery[$swapIndex])) {
        $tmp = $gallery[$index];
        $gallery[$index] = $gallery[$swapIndex];
        $gallery[$swapIndex] = $tmp;

        $_SESSION[$sessionGalleryKey] = $gallery;
        $workingGallery = $gallery;
        $message = "✅ Reihenfolge aktualisiert.";
    }
}

// --- LOGIK: MEDIA LÖSCHEN ---
if (isset($_POST['delete_media']) && isset($_POST['media_index'])) {
    $index = (int)$_POST['media_index'];
    $gallery = $workingGallery;

    if (isset($gallery[$index])) {
        $item = $gallery[$index];
        if (!empty($item['is_temp']) && !empty($item['tmp_path'])) {
            $path = $item['tmp_path'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        unset($gallery[$index]);
        $gallery = array_values($gallery);

        $_SESSION[$sessionGalleryKey] = $gallery;
        $workingGallery = $gallery;
        $message = "✅ Medium gelöscht.";
    }
}

// --- LOGIK: MEDIA UPLOAD ---
if (isset($_POST['upload_media']) && isset($_FILES['gallery_files'])) {
    $files = $_FILES['gallery_files'];
    $newItems = [];
    $uploadErrors = [];
    $uploadedFiles = [];
    if (count($files['name']) > MEDIA_UPLOAD_MAX_FILES) {
        $uploadErrors[] = "Maximal " . MEDIA_UPLOAD_MAX_FILES . " Dateien pro Upload.";
    }

    $limit = min(count($files['name']), MEDIA_UPLOAD_MAX_FILES);
    for ($i = 0; $i < $limit; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $uploadErrors[] = htmlspecialchars($files['name'][$i]) . ": " . upload_error_message($files['error'][$i]);
            }
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $type = detect_media_type($tmpPath, $files['name'][$i]);
        if (!$type) {
            $uploadErrors[] = "Ungültiger Dateityp: " . htmlspecialchars($files['name'][$i]);
            continue;
        }
        $validationError = validate_media_upload($tmpPath, $files['size'][$i], $type);
        if ($validationError) {
            $uploadErrors[] = htmlspecialchars($files['name'][$i]) . ": " . $validationError;
            continue;
        }
        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $safeExt = sanitize_extension($ext);
        $filename = uniqid('media_', true) . $safeExt;
        $targetDir = media_temp_dir($type, $contentTmpDir, $_SESSION['user_id'], (string)$projectObjectId);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetFile = $targetDir . '/' . $filename;

        if (move_uploaded_file($tmpPath, $targetFile)) {
            $publicPath = build_temp_url($type, $_SESSION['user_id'], (string)$projectObjectId, $filename);
            $newItems[] = [
                'type' => $type,
                'url' => $publicPath,
                'is_temp' => true,
                'tmp_path' => $targetFile
            ];
            $uploadedFiles[] = $targetFile;
        } else {
            $uploadErrors[] = "Fehler beim Speichern: " . htmlspecialchars($files['name'][$i]);
        }
    }

    if (!empty($newItems)) {
        $gallery = $workingGallery;
        $gallery = array_merge($gallery, $newItems);
        $_SESSION[$sessionGalleryKey] = $gallery;
        $workingGallery = $gallery;
        $message = "✅ Medien hochgeladen. Änderungen sind noch nicht gespeichert.";
    } elseif (!$message) {
        $message = "❌ Keine gültigen Medien zum Upload gefunden.";
    }
    if (!empty($uploadErrors)) {
        $message .= "<br>" . implode("<br>", $uploadErrors);
    }
}

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

// --- LOGIK: PROJEKT AKTUALISIEREN ---
if (isset($_POST['update_project'])) {
    $rawTags = [];
    if (isset($_POST['tags'])) {
        $rawTags = array_map('trim', explode(',', $_POST['tags']));
    }
    $tags = array_values(array_filter($rawTags, function ($tag) {
        return $tag !== '';
    }));
    $updateData = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        // Tags sind im Schema nicht explizit als Pflichtfeld im Validator, 
        // aber wir behalten sie bei.
        'tags' => $tags,
        'updated_at' => new \MongoDB\BSON\UTCDateTime() // PFLICHT laut Schema
    ];

    if (empty($message) && !empty($updateData['title'])) {
        $finalGallery = [];
        $workingGallery = normalize_gallery_items($_SESSION[$sessionGalleryKey] ?? ($project['gallery'] ?? []));
        foreach ($workingGallery as $item) {
            if (empty($item['type']) || empty($item['url'])) {
                continue;
            }
            if (!empty($item['is_temp']) && !empty($item['tmp_path'])) {
                $type = $item['type'];
                $ext = pathinfo($item['tmp_path'], PATHINFO_EXTENSION);
                $safeExt = sanitize_extension($ext);
                $filename = uniqid('media_', true) . $safeExt;
                $targetDir = media_target_dir($type, $contentImageDir, $contentVideoDir);
                $targetFile = $targetDir . '/' . $filename;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (rename($item['tmp_path'], $targetFile)) {
                    $publicPath = 'content/' . ($type === 'video' ? 'videos' : 'images') . '/' . $filename;
                    $finalGallery[] = [
                        'type' => $type,
                        'url' => $publicPath
                    ];
                } else {
                    $message = "❌ Fehler beim Finalisieren der Medien.";
                    break;
                }
            } else {
                $finalGallery[] = [
                    'type' => $item['type'],
                    'url' => $item['url']
                ];
            }
        }

        if (empty($message)) {
            $updateData['gallery'] = $finalGallery;
            if (!empty($finalGallery) && !empty($finalGallery[0]['url'])) {
                $updateData['thumbnail'] = $finalGallery[0]['url'];
                $updateData['thumbnail_type'] = $finalGallery[0]['type'] ?? 'image';
            }
            $originalGallery = normalize_gallery_items($_SESSION[$sessionOriginalKey] ?? ($project['gallery'] ?? []));
            $originalUrls = [];
            foreach ($originalGallery as $item) {
                if (!empty($item['url'])) {
                    $originalUrls[] = $item['url'];
                }
            }
            $finalUrls = [];
            foreach ($finalGallery as $item) {
                if (!empty($item['url'])) {
                    $finalUrls[] = $item['url'];
                }
            }
            $removed = array_diff($originalUrls, $finalUrls);
            foreach ($removed as $url) {
                if (strpos($url, 'content/images/') === 0 || strpos($url, 'content/videos/') === 0) {
                    $path = __DIR__ . '/../' . $url;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
        }

        if (empty($message)) {
            try {
                $update = ['$set' => $updateData];
                if (empty($finalGallery)) {
                    $update['$unset'] = [
                        'thumbnail' => '',
                        'thumbnail_type' => ''
                    ];
                }
                $db->projects->updateOne(['_id' => $projectObjectId], $update);

                $project = $db->projects->findOne(['_id' => $projectObjectId]);
                $_SESSION[$sessionGalleryKey] = normalize_gallery_items($project['gallery'] ?? []);
                $_SESSION[$sessionOriginalKey] = normalize_gallery_items($project['gallery'] ?? []);
                $workingGallery = $_SESSION[$sessionGalleryKey];
                $message = "✅ Projekt erfolgreich aktualisiert!";
            } catch (Exception $e) {
                $message = "❌ Datenbankfehler: " . $e->getMessage();
            }
        }
    } elseif (empty($message)) {
        $message = "❌ Bitte geben Sie mindestens einen Titel an.";
    }
}

$debugFlag = isset($_GET['debug']) && $_GET['debug'] === '1';
$editActionUrl = 'index.php?page=edit_project&id=' . urlencode((string)$projectObjectId);
if ($debugFlag) {
    $editActionUrl .= '&debug=1';
}

if ($isAjax) {
    echo render_media_manager($workingGallery, $editActionUrl, $message, true);
    exit;
}
?>

<head>
    <link rel="stylesheet" href="style/edit_project.css">
</head>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Projekt bearbeiten</h1>
        <a href="index.php?page=project_detail&id=<?php echo (string)$projectObjectId; ?>">Zurück zum Projekt</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" enctype="multipart/form-data">
        <label for="title">Projekttitel</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($project['description']); ?></textarea>

        <label for="tags">Tags (kommagetrennt)</label>
        <?php
            $tags = [];
            if (isset($project['tags']) && is_array($project['tags'])) {
                $tags = $project['tags'];
            }
        ?>
        <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', $tags)); ?>" placeholder="z.B. Coding, Musik, Gym">

        <button type="submit" name="update_project">Änderungen speichern</button>
    </form>

    <section id="media-upload" class="media-manager">
        <?php echo render_media_manager($workingGallery, $editActionUrl, $message); ?>
    </section>
</div>

<script>
    (function () {
        const mediaSection = document.getElementById('media-upload');
        if (!mediaSection) return;

        async function submitMediaForm(form, submitter) {
            const formData = new FormData(form);
            formData.set('ajax', '1');
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value || '1');
            }
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'fetch'
                }
            });
            const html = await response.text();
            mediaSection.innerHTML = html;
        }

        mediaSection.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.ajax !== 'true') {
                return;
            }
            event.preventDefault();
            submitMediaForm(form, event.submitter).catch(() => {});
        });

        mediaSection.addEventListener('change', function (event) {
            const target = event.target;
            if (!target || target.id !== 'gallery_files') {
                return;
            }
            const form = target.closest('form');
            if (!form || form.dataset.ajax !== 'true') {
                return;
            }
            if (!target.files || target.files.length === 0) {
                return;
            }
            submitMediaForm(form).catch(() => {});
        });
    })();
</script>
