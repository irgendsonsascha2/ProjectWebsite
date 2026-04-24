<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

use MongoDB\BSON\UTCDateTime;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?page=login');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren.</p>");
}

function admin_site_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    // /pages/admin/registration_requests.php -> base is ''
    $scriptDir = (string)dirname($_SERVER['SCRIPT_NAME'] ?? '/pages/admin/registration_requests.php');
    $basePath = preg_replace('#/pages/admin$#', '', $scriptDir);
    $basePath = rtrim((string)$basePath, '/');
    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

function send_plain_mail(string $to, string $subject, string $body): bool {
    $from = (string)(getenv('MAIL_FROM_EMAIL') ?: 'no-reply@localhost');
    $from = trim($from) !== '' ? trim($from) : 'no-reply@localhost';
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=utf-8';
    $headers[] = 'From: ' . $from;
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function generate_unique_code($db, int $maxAttempts = 10): ?string {
    try {
        $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    } catch (Exception $e) {
        // ignore
    }
    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = strtoupper(bin2hex(random_bytes(8)));
        try {
            $db->registration_codes->insertOne([
                'code' => $candidate,
                'role' => 'viewer',
                'is_used' => false,
                'created_at' => new UTCDateTime()
            ]);
            return $candidate;
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $wr = $e->getWriteResult();
            $wes = $wr ? $wr->getWriteErrors() : [];
            foreach ($wes as $we) {
                if (method_exists($we, 'getCode') && (int)$we->getCode() === 11000) {
                    continue 2;
                }
            }
            throw $e;
        }
    }
    return null;
}

$message = '';
$messageClass = 'alert';

if (isset($_POST['approve_request_id'])) {
    $id = (string)($_POST['approve_request_id'] ?? '');
    try {
        $oid = new \MongoDB\BSON\ObjectId($id);
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
            $code = generate_unique_code($db);
            if ($code === null) {
                $message = '❌ Konnte keinen Code erzeugen. Bitte erneut versuchen.';
                $messageClass = 'alert alert--error';
            } else {
                $base = admin_site_base_url();
                $link = $base . '/index.php?page=register&reg_token=' . rawurlencode($code) . '#register-section';
                $email = (string)($req['email'] ?? '');
                $body = "Hallo!\n\nDein Registrierungscode ist:\n\n{$code}\n\nDirekt-Link:\n{$link}\n\n";
                $mailOk = send_plain_mail($email, 'Dein Registrierungscode', $body);
                if (!$mailOk) {
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

$verifiedPending = iterator_to_array(
    $db->registration_code_requests->find(
        ['verified_at' => ['$ne' => null], 'approved_at' => null],
        ['sort' => ['verified_at' => -1]]
    )
);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Registrierungsanfragen</title>
    <?php require_once __DIR__ . '/../../includes/vite_assets.php'; vite_react_assets('src/main.tsx'); ?>
</head>
<body class="admin-page">
    <div class="container">
        <div class="page-header">
            <h1>Registrierungsanfragen</h1>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="registration_requests.php">Registrierungsanfragen</a>
            <a href="roles.php">Rollen</a>
            <a href="permissions.php">Berechtigungen</a>
            <a href="../../index.php">Zur Hauptseite</a>
        </div>

        <hr>

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
                                <form method="POST" onsubmit="return confirm('Anfrage freigeben und Code senden?');">
                                    <input type="hidden" name="approve_request_id" value="<?php echo htmlspecialchars($rid, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit">Freigeben + Code senden</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

