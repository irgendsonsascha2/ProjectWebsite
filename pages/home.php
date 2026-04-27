<?php
function home_profile_default_portrait_src(): string
{
    return 'img/profile-placeholder.svg';
}

function is_safe_home_image_path(string $path): bool
{
    $path = trim($path);
    if ($path === '') {
        return false;
    }
    if (preg_match('/^https?:\\/\\//i', $path)) {
        return false;
    }
    if (strpos($path, '..') !== false) {
        return false;
    }
    return strpos($path, 'content/images/') === 0 || strpos($path, 'img/') === 0;
}

$displayName = 'Dein Name';
$kicker = 'Deine Position / Spezialisierung';
$lead = "Kurze Beschreibung.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.";
$body = "Langer Text.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.\n\nSed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris.";
$portraitSrc = home_profile_default_portrait_src();

try {
    if (isset($db)) {
        $doc = $db->site_pages->findOne(['_id' => 'home_profile']);
        if ($doc) {
            if (isset($doc['display_name']) && is_string($doc['display_name']) && trim($doc['display_name']) !== '') {
                $displayName = trim($doc['display_name']);
            }
            if (isset($doc['kicker']) && is_string($doc['kicker']) && trim($doc['kicker']) !== '') {
                $kicker = trim($doc['kicker']);
            }
            if (isset($doc['lead']) && is_string($doc['lead']) && trim($doc['lead']) !== '') {
                $lead = trim($doc['lead']);
            }
            if (isset($doc['body']) && is_string($doc['body']) && trim($doc['body']) !== '') {
                $body = trim($doc['body']);
            }
            if (isset($doc['portrait_url']) && is_string($doc['portrait_url']) && is_safe_home_image_path($doc['portrait_url'])) {
                $portraitSrc = trim($doc['portrait_url']);
            }
        }
    }
} catch (Exception $e) {
    // Fallback bleibt aktiv
}
?>

<section class="home-landing" aria-label="Kurzvorstellung">
    <header class="home-intro">
        <div class="home-visual">
            <figure class="home-portrait skeleton-host" data-skeleton-media>
                <span class="skeleton-panel skeleton-panel--circle" aria-hidden="true"></span>
                <img src="<?php echo htmlspecialchars($portraitSrc); ?>" alt="Profilfoto" width="280" height="280" loading="lazy" decoding="async">
            </figure>
        </div>
        <div class="home-heading-wrap">
            <div class="skeleton-text-block skeleton-text-block--center home-heading-skel" aria-hidden="true">
                <span class="skeleton-line skeleton-line--lg"></span>
                <span class="skeleton-line skeleton-line--short"></span>
            </div>
            <div class="home-heading-real">
                <h1 class="home-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="home-kicker"><?php echo htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </header>

    <div class="home-copy">
        <p class="home-lead">
            <?php echo nl2br(htmlspecialchars($lead, ENT_QUOTES, 'UTF-8')); ?>
        </p>
        <p class="home-body">
            <?php echo nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')); ?>
        </p>
        <a class="home-cta" href="index.php?page=project_grid">Zur Projektgalerie</a>
    </div>
</section>

<?php
// Floating edit button like project_detail (admin/content_manager only)
$isLoggedIn = isset($_SESSION['user_id']);
$role = (string)($_SESSION['role'] ?? '');
$canEditHome = $isLoggedIn && in_array($role, ['admin', 'content_manager'], true);
?>

<?php if ($canEditHome): ?>
  <a href="pages/admin/home_profile.php" class="fab fab-edit" title="Startseite bearbeiten" aria-label="Startseite bearbeiten">
    <?php echo svg_icon_pencil(22); ?>
  </a>
<?php endif; ?>
