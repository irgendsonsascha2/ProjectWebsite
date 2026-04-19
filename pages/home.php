<?php
/** Eigenen Namen hier eintragen (erscheint unter dem Profilfoto). */
$displayName = 'Sascha Fähling';

$base = __DIR__ . '/../img/';
$portraitSrc = 'img/placeholder.svg';
foreach (['portrait.jpg', 'portrait.png', 'portrait.webp'] as $f) {
    if (is_file($base . $f)) {
        $portraitSrc = 'img/' . $f;
        break;
    }
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
                <p class="home-kicker">Softwareentwicklung mit C# / .NET</p>
            </div>
        </div>
    </header>

    <div class="home-copy">
        <p class="home-lead">
            Softwareentwickler mit mehrjähriger Praxiserfahrung im Rahmen eines dualen Informatikstudiums (B.Sc.).
            Schwerpunkt auf datenbankgestützter Systementwicklung und Weiterentwicklung unternehmensinterner
            CRM- und ERP-Softwarelösungen.
        </p>
        <p class="home-body">
            Neben dem Beruf entwickle ich diese Website und weitere eigene Projekte, betreibe einen Heimserver,
            produziere Musik mit FL Studio und trainiere Krafttraining im Gym. Vertiefende Arbeiten und Medien
            sind in der Projektgalerie zusammengefasst.
        </p>
        <a class="home-cta" href="index.php?page=project_grid">Zur Projektgalerie</a>
    </div>
</section>
