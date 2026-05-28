<?php
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/registration_codes.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';

use MongoDB\BSON\UTCDateTime;

admin_require_manage_users();

$message = '';
$messageClass = 'alert';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

if (isset($_POST['approve_request_id'])) {
    $reauth = admin_reauth_require_fresh_or_post();
    if (! $reauth['ok']) {
        $message = '❌ '.$reauth['error'];
        $messageClass = 'alert alert--error';
    } else {
        $adminReauthFresh = admin_reauth_is_fresh();
        $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
    $idHex = input_object_id_hex(req_post_string('approve_request_id', '', 24, false));
    try {
        if ($idHex === null) {
            throw new InvalidArgumentException('invalid id');
        }
        $oid = new \MongoDB\BSON\ObjectId($idHex);
        $req = $db->registration_code_requests->findOne(['_id' => $oid]);
        if (!$req) {
            $message = '❌ Anfrage nicht gefunden.';
            $messageClass = 'alert alert--error';
        } elseif (empty($req['verified_at'])) {
            $message = '❌ Anfrage ist noch nicht verifiziert.';
            $messageClass = 'alert alert--error';
        } elseif (!empty($req['approved_at'])) {
            $message = 'ℹ️ Anfrage wurde bereits freigegeben.';
            $messageClass = 'alert';
        } else {
            $code = registration_code_create($db, 'viewer');
            if ($code === null) {
                $message = '❌ Konnte keinen Code erzeugen. Bitte erneut versuchen.';
                $messageClass = 'alert alert--error';
            } else {
                $link = registration_code_register_link($code);
                $email = (string)($req['email'] ?? '');
                $body = "Hallo!\n\nDein Registrierungscode ist:\n\n{$code}\n\nDirekt-Link:\n{$link}\n\n";
                $mailOk = send_plain_mail($email, 'Dein Registrierungscode', $body);
                if (!$mailOk) {
                    // Mail failed: keep the request unapproved and delete the unused code again,
                    // so we don't accumulate "dead" unused codes.
                    try {
                        $db->registration_codes->deleteOne(['code' => $code]);
                    } catch (Exception $e) {
                        // ignore cleanup failures
                    }
                    $message = '❌ Code erzeugt, aber E-Mail konnte nicht gesendet werden (Server-Mail nicht konfiguriert?).';
                    $messageClass = 'alert alert--error';
                } else {
                    $db->registration_code_requests->updateOne(
                        ['_id' => $oid],
                        ['$set' => ['approved_at' => new UTCDateTime(), 'code' => $code]]
                    );
                    $message = '✅ Freigegeben und Code per E-Mail gesendet.';
                    $messageClass = 'alert alert--success';
                }
            }
        }
    } catch (Exception $e) {
        $message = '❌ Ungültige Anfrage-ID.';
        $messageClass = 'alert alert--error';
    }
    }
}

$verifiedPending = iterator_to_array(
    $db->registration_code_requests->find(
        ['verified_at' => ['$ne' => null], 'approved_at' => null],
        ['sort' => ['verified_at' => -1]]
    )
);
?>
<?php admin_render_page('Registrierungsanfragen', 'registration_requests', function () use ($message, $messageClass, $verifiedPending, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) { ?>
    <div class="page-header">
        <h1>Registrierungsanfragen</h1>
    </div>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <h2>Verifizierte Anfragen (warte auf Freigabe)</h2>

    <?php if (count($verifiedPending) === 0): ?>
        <p class="muted">Keine offenen Anfragen.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>E-Mail</th>
                    <th>Verifiziert</th>
                    <th>IP</th>
                    <th>Aktion</th>
                </tr>
                <?php foreach ($verifiedPending as $r): ?>
                    <?php
                        $rid = (string)($r['_id'] ?? '');
                        $email = (string)($r['email'] ?? '');
                        $ip = (string)($r['requested_ip'] ?? '');
                        $ver = $r['verified_at'] instanceof UTCDateTime ? $r['verified_at']->toDateTime()->format('d.m.Y H:i') : '';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($adminReauthFresh): ?>
                            <form method="POST" action="registration_requests.php" data-confirm-submit="Anfrage freigeben und Code senden?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="approve_request_id" value="<?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit">Freigeben + Code senden</button>
                            </form>
                            <?php else: ?>
                            <button type="button" class="button-primary" data-dialog-open="approve-request-dialog" data-approve-request-id="<?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?>">Freigeben + Code senden…</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <dialog id="approve-request-dialog">
        <div class="dialog-card">
            <div class="dialog-header">
                <h2>Registrierung freigeben</h2>
                <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
            </div>
            <form method="POST" action="registration_requests.php" id="approve-request-form" data-dialog-close-on-submit data-confirm-submit="Anfrage freigeben und Code senden?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="approve_request_id" id="approve_request_id" value="">
                <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                <button type="submit">Freigeben + Code senden</button>
            </form>
        </div>
    </dialog>
<?php }, ['admin'], ['admin-reauth-approve.js']); ?>
