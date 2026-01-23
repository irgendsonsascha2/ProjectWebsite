<?php
// Session starten und Hilfsfunktionen laden
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../vendor/autoload.php';

// --- HILFSFUNKTIONEN ---
if (!function_exists('can')) {
    function can($permission)
    {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}
$isLoggedIn = isset($_SESSION['user_id']);

// --- DATENBANK & PROJEKT LADEN ---
use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$client = new Client("mongodb://localhost:27017");
$db = $client->portfolio_db;

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
} catch (Exception $e) {
    echo "Ungültige Projekt-ID.";
    return;
}

$message = '';

// --- LOGIK: LIKE / DISLIKE ---
if ($isLoggedIn && can('like_dislike') && isset($_POST['interaction'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $type = $_POST['interaction']; // 'like' or 'dislike'

    // Bestehenden Vote entfernen, um Duplikate zu vermeiden
    $db->likes->deleteOne(['project_id' => $projectObjectId, 'user_id' => $userId]);

    // Neuen Vote einfügen
    if ($type === 'like' || $type === 'dislike') {
        $db->likes->insertOne([
            'project_id' => $projectObjectId,
            'user_id' => $userId,
            'type' => $type,
            'created_at' => new UTCDateTime()
        ]);
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- LOGIK: KOMMENTAR ---
if ($isLoggedIn && can('comment') && isset($_POST['submit_comment'])) {
    $userId = new ObjectId($_SESSION['user_id']);
    $commentText = trim($_POST['comment_text']);

    if (!empty($commentText)) {
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
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// --- DATEN FÜR DIE ANZEIGE LADEN ---
$likeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'like']);
$dislikeCount = $db->likes->countDocuments(['project_id' => $projectObjectId, 'type' => 'dislike']);

// Kommentare mit User-Infos laden
$comments = $db->comments->aggregate([
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
?>

<link rel="stylesheet" href="style/project_detail.css">

<article class="project-detail">
    <a href="index.php?page=project_grid" class="back-link">← Zurück zur Übersicht</a>

    <h1><?php echo htmlspecialchars($project['title']); ?></h1>

    <div class="gallery-grid" style="text-align: center;">
        <?php if (!empty($project['image'])): ?>
            <img src="<?php echo htmlspecialchars($project['image']); ?>" alt="Vorschaubild" style="max-width: 100%; border-radius: 0.5rem;">
        <?php endif; ?>
    </div>

    <p class="description"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
    <p>Gepostet am: <?php echo $project['created_at']->toDateTime()->format('d.m.Y'); ?></p>


    <!-- INTERACTION SECTION -->
    <?php if ($isLoggedIn && (can('like_dislike') || can('comment'))): ?>
        <section class="interaction-section">
            <h2>Interaktionen</h2>

            <!-- LIKES / DISLIKES -->
            <?php if (can('like_dislike')): ?>
                <form method="POST" class="interaction-buttons">
                    <button type="submit" name="interaction" value="like">👍 <?php echo $likeCount; ?></button>
                    <button type="submit" name="interaction" value="dislike">👎 <?php echo $dislikeCount; ?></button>
                    <button type="submit" name="interaction" value="clear">Vote zurücksetzen</button>
                </form>
            <?php endif; ?>

            <!-- KOMMENTAR-FORMULAR -->
            <?php if (can('comment')): ?>
                <h4>Dein Kommentar</h4>
                <form method="POST" class="comment-form">
                    <textarea name="comment_text" placeholder="Schreibe einen Kommentar..."><?php echo htmlspecialchars($userComment['text'] ?? ''); ?></textarea>
                    <button type="submit" name="submit_comment">Kommentar speichern</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>


    <!-- KOMMENTAR-LISTE -->
    <section class="comment-list">
        <h3>Kommentare</h3>
        <?php foreach ($comments as $comment): ?>
            <div class="comment">
                <p class="author"><?php echo htmlspecialchars($comment['user_info']['email']); ?></p>
                <p class="date"><?php echo $comment['updated_at']->toDateTime()->format('d.m.Y H:i'); ?></p>
                <p><?php echo nl2br(htmlspecialchars($comment['text'])); ?></p>
            </div>
        <?php endforeach; ?>
        <?php if (iterator_count($comments) === 0): ?>
            <p>Noch keine Kommentare vorhanden.</p>
        <?php endif; ?>
    </section>
</article>

<?php if ($canEdit): ?>
<a href="index.php?page=edit_project&id=<?php echo (string)$projectObjectId; ?>" class="fab" title="Projekt bearbeiten">+</a>
<?php endif; ?>