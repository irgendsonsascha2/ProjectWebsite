<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$ok = false;
$message = '';
$messageClass = 'alert';

if ($token === '') {
    $message = '❌ Ungültiger Link.';
    $messageClass = 'alert alert--error';
} else {
    $now = new UTCDateTime();
    $req = $db->registration_code_requests->findOne(['token' => $token]);
    if (!$req) {
        $message = '❌ Link ungültig oder abgelaufen.';
        $messageClass = 'alert alert--error';
    } else {
        $expiresAt = $req['expires_at'] ?? null;
        if ($expiresAt instanceof UTCDateTime && $expiresAt->toDateTime() < (new DateTimeImmutable('now'))) {
            $message = '❌ Link abgelaufen. Bitte fordere einen neuen Registrierungscode an.';
            $messageClass = 'alert alert--error';
        } elseif (!empty($req['verified_at'])) {
            $message = '✅ E-Mail wurde bereits bestätigt. Ein Admin prüft deine Anfrage.';
            $messageClass = 'alert alert--success';
            $ok = true;
        } else {
            $db->registration_code_requests->updateOne(
                ['_id' => $req['_id']],
                ['$set' => ['verified_at' => $now]]
            );
            $message = '✅ Danke! Deine E-Mail ist bestätigt. Ein Admin prüft deine Anfrage.';
            $messageClass = 'alert alert--success';
            $ok = true;
        }
    }
}
?>

<div class="container">
    <h1>E-Mail bestätigen</h1>
    <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <p class="field-hint" style="margin-top: 1rem;">
        <a href="index.php?page=login">Zum Login</a>
    </p>
</div>

