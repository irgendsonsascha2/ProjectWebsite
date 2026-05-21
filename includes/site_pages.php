<?php

use MongoDB\BSON\UTCDateTime;

if (!function_exists('site_page_legal_keys')) {
    /** @return string[] */
    function site_page_legal_keys(): array
    {
        return ['impressum', 'datenschutz', 'nutzungsbedingungen'];
    }
}

if (!function_exists('site_page_is_legal_key')) {
    function site_page_is_legal_key(string $key): bool
    {
        return in_array($key, site_page_legal_keys(), true);
    }
}

if (!function_exists('site_page_can_edit_content')) {
    function site_page_can_edit_content(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        return in_array($role, ['admin', 'content_manager'], true);
    }
}

if (!function_exists('site_page_home_profile_defaults')) {
    /**
     * @return array<string, string>
     */
    function site_page_home_profile_defaults(): array
    {
        return [
            'display_name' => 'Dein Name',
            'kicker' => 'Deine Position / Spezialisierung',
            'lead' => "Kurze Beschreibung.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            'body' => "Langer Text.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.\n\nSed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris.",
            'portrait_url' => '',
        ];
    }
}

if (!function_exists('site_page_legal_defaults')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function site_page_legal_defaults(): array
    {
        return [
            'impressum' => [
                'title' => 'Impressum',
                'intro' => '',
                'provider_name' => 'Max Mustermann',
                'provider_address' => 'Musterstraße 1, 12345 Musterstadt, Deutschland',
                'contact_email' => 'kontakt@beispiel.de',
                'sections' => [
                    [
                        'heading' => 'Haftung für Inhalte',
                        'body' => "Als Diensteanbieter bin ich gemäß den allgemeinen Gesetzen für eigene Inhalte auf diesen Seiten verantwortlich.\n"
                            . "Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden.\n"
                            . "Die Inhalte dieser Website stellen keine Anleitung, Beratung oder Aufforderung zu bestimmten Handlungen dar.\n"
                            . "Eine Nachahmung oder Anwendung von dargestellten Inhalten, Methoden oder Aktivitäten erfolgt ausschließlich auf eigenes Risiko.\n"
                            . "Dies gilt insbesondere für Versuche/Experimente und Tätigkeiten mit erhöhtem Gefahrenpotenzial (z. B. der Umgang mit Chemikalien, elektrischen Anlagen, Werkzeugen oder Maschinen).\n"
                            . "Eine Haftung für Schäden materieller oder immaterieller Art, die durch die Nutzung oder Nachahmung der Inhalte entstehen, ist – soweit gesetzlich zulässig – ausgeschlossen.",
                    ],
                    [
                        'heading' => 'Nutzerkommentare / Inhalte Dritter',
                        'body' => "Soweit Nutzer dieser Plattform Kommentare oder sonstige Inhalte einstellen, geben diese Inhalte ausschließlich die Meinung des jeweiligen Nutzers wieder.\n"
                            . "Ich distanziere mich von den von Nutzern eingestellten Inhalten und übernehme keine Verantwortung oder Haftung für Inhalte, die von anderen Nutzern veröffentlicht oder kommentiert werden.\n"
                            . "Bei Bekanntwerden von Rechtsverletzungen werde ich derartige Inhalte im Rahmen der gesetzlichen Möglichkeiten prüfen und gegebenenfalls entfernen.",
                    ],
                    [
                        'heading' => 'Haftung für Links',
                        'body' => "Diese Website kann Links zu externen Websites Dritter enthalten, auf deren Inhalte ich keinen Einfluss habe.\n"
                            . "Für diese fremden Inhalte wird daher keine Gewähr übernommen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.",
                    ],
                    [
                        'heading' => 'Urheberrecht',
                        'body' => "Die durch den Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht.\n"
                            . "Beiträge Dritter sind als solche gekennzeichnet. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechts bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.",
                    ],
                    [
                        'heading' => 'Streitbeilegung',
                        'body' => "Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit.\n"
                            . "Ich bin nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.",
                    ],
                ],
            ],
            'datenschutz' => [
                'title' => 'Datenschutz',
                'intro' => '',
                'provider_name' => '',
                'provider_address' => '',
                'contact_email' => '',
                'sections' => [
                    [
                        'heading' => 'Welche Daten verarbeiten wir?',
                        'body' => "- Bei der Registrierung: E-Mail-Adresse, Username, Passwort (wird gehasht gespeichert).\n"
                            . "- Bei der Anfrage eines Registrierungscodes: E-Mail-Adresse sowie technische Metadaten (IP-Adresse, User-Agent) zur Missbrauchsprävention und Bearbeitung.\n"
                            . "- Bei Nutzung der Website: Nach Login/Registrierung Session/Cookies zur Anmeldung und Bedienbarkeit.\n"
                            . "- Bei optional aktivierter Zwei-Faktor-Authentifizierung (2FA): siehe Abschnitt „Zwei-Faktor-Authentifizierung (optional)“ unten.",
                    ],
                    [
                        'heading' => 'Wofür werden die Daten genutzt?',
                        'body' => "- Konto anlegen und Login ermöglichen\n"
                            . "- Invite-/Registrierungscode-Anfragen verifizieren und administrativ freigeben\n"
                            . "- Sicherheit und Stabilität (z. B. Rate-Limits, Fehleranalyse)\n"
                            . "- optional: Zwei-Faktor-Authentifizierung zur Erhöhung der Kontosicherheit",
                    ],
                    [
                        'heading' => 'Zwei-Faktor-Authentifizierung (optional)',
                        'body' => "Die Nutzung der Zwei-Faktor-Authentifizierung (2FA) ist freiwillig. Der Login ist grundsätzlich mit Benutzername bzw. E-Mail und Passwort möglich. 2FA kann im Konto aktiviert und wieder deaktiviert werden.\n\n"
                            . "Verfahren: Es wird TOTP (zeitbasiertes Einmalpasswort, 6-stellig, 30 Sekunden Gültigkeit) verwendet. Du richtest 2FA mit einer Authenticator-App auf deinem eigenen Gerät ein (z. B. Google Authenticator, Aegis oder vergleichbare Apps). Der Betreiber dieser Website sendet beim Login keine 2FA-Codes per E-Mail oder SMS.\n\n"
                            . "Verarbeitete Daten bei aktivierter 2FA:\n"
                            . "- Status, ob 2FA aktiv ist\n"
                            . "- TOTP-Geheimnis (technisch als Base32-Zeichenkette auf dem Server gespeichert; für die Prüfung der Codes erforderlich, nicht gehasht)\n"
                            . "- Zeitpunkt der erfolgreichen Einrichtung\n"
                            . "- Backup-Codes ausschließlich als Passwort-Hashes (Klartext der Codes nur einmalig bei der Einrichtung bzw. Neuerzeugung angezeigt)\n\n"
                            . "Beim Login mit aktivierter 2FA: Nach erfolgreicher Passwortprüfung wird ein kurzlebiger Anmelde-Zwischenzustand in der Session gespeichert (typisch wenige Minuten), bis du einen gültigen TOTP- oder Backup-Code eingibst. Die eingegebenen Codes werden nicht dauerhaft gespeichert.\n\n"
                            . "Zweck: Erhöhung der Sicherheit deines Kontos.\n\n"
                            . "Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einer sicheren Kontoverwaltung); soweit die Nutzung des Kontos als Nutzungsverhältnis gilt, zusätzlich Art. 6 Abs. 1 lit. b DSGVO.\n\n"
                            . "Speicherdauer: Die genannten 2FA-Daten werden gespeichert, bis du 2FA deaktivierst oder dein Konto gelöscht wird.\n\n"
                            . "Empfänger: Das TOTP-Geheimnis wird nicht an Dritte weitergegeben. Authenticator-Apps wählst du selbst; sie sind nicht Bestandteil dieser Website und unterliegen der Verantwortung des jeweiligen App-Anbieters auf deinem Gerät.\n\n"
                            . "Betroffenenrechte: Du hast die Rechte nach der DSGVO (z. B. Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch). 2FA kannst du im Konto deaktivieren. Speichere Backup-Codes sicher; ohne sie und ohne Zugang zum Authenticator-Gerät kann der Login eingeschränkt sein.",
                    ],
                    [
                        'heading' => 'Kontakt',
                        'body' => 'Bei Fragen zum Datenschutz wende dich an die Kontaktdaten im Impressum.',
                    ],
                ],
            ],
            'nutzungsbedingungen' => [
                'title' => 'Nutzungsbedingungen / Verantwortung für Inhalte',
                'intro' => '',
                'provider_name' => '',
                'provider_address' => '',
                'contact_email' => '',
                'sections' => [
                    [
                        'heading' => 'Verantwortung für eigene Inhalte',
                        'body' => "Wenn du Inhalte auf dieser Plattform hochlädst oder veröffentlichst (z. B. Kommentare, Texte, Bilder, Videos),\n"
                            . "trägst du die Verantwortung für diese Inhalte selbst. Du stellst sicher, dass deine Inhalte keine Rechte Dritter verletzen\n"
                            . "(z. B. Urheberrechte, Persönlichkeitsrechte, Markenrechte) und keine rechtswidrigen Inhalte enthalten.",
                    ],
                    [
                        'heading' => 'Kommentare',
                        'body' => 'Kommentare sind Inhalte des jeweiligen Nutzers. Bitte poste nur Inhalte, die du selbst verantworten kannst.',
                    ],
                    [
                        'heading' => 'Moderation / Entfernung',
                        'body' => 'Ich behalte mir vor, Inhalte zu prüfen und bei Verstößen oder bei Verdacht auf Rechtsverletzungen zu entfernen oder zu sperren.',
                    ],
                    [
                        'heading' => 'Zwei-Faktor-Authentifizierung (optional)',
                        'body' => "Du kannst freiwillig eine Zwei-Faktor-Authentifizierung (2FA) per Authenticator-App aktivieren.\n\n"
                            . "Du bist dafür verantwortlich, dein Authenticator-Gerät und die bei der Einrichtung angezeigten Backup-Codes sicher aufzubewahren. Jeder Backup-Code ist nur einmal verwendbar.\n\n"
                            . "Bei Verlust des Authenticator-Geräts ohne nutzbare Backup-Codes kann der Zugang zum Konto eingeschränkt sein. Support oder Wiederherstellung erfolgen nur im Rahmen der im Impressum genannten Kontaktmöglichkeiten und nach Prüfung des Einzelfalls.",
                    ],
                    [
                        'heading' => 'Kontakt',
                        'body' => 'Bei Fragen wende dich an die Kontaktdaten im Impressum.',
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('site_page_collection_validator')) {
    /** @return array<string, mixed> */
    function site_page_collection_validator(): array
    {
        return [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['_id', 'page_kind', 'created_at', 'updated_at'],
                'properties' => [
                    '_id' => ['bsonType' => 'string'],
                    'page_kind' => ['enum' => ['home_profile', 'legal', 'settings']],
                    'display_name' => ['bsonType' => 'string'],
                    'kicker' => ['bsonType' => 'string'],
                    'lead' => ['bsonType' => 'string'],
                    'body' => ['bsonType' => 'string'],
                    'portrait_url' => ['bsonType' => 'string'],
                    'title' => ['bsonType' => 'string'],
                    'intro' => ['bsonType' => 'string'],
                    'provider_name' => ['bsonType' => 'string'],
                    'provider_address' => ['bsonType' => 'string'],
                    'contact_email' => ['bsonType' => 'string'],
                    'sections' => [
                        'bsonType' => 'array',
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['heading', 'body'],
                            'properties' => [
                                'heading' => ['bsonType' => 'string'],
                                'body' => ['bsonType' => 'string'],
                            ],
                        ],
                    ],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                    'updated_by' => ['bsonType' => 'string'],
                ],
                'additionalProperties' => true,
            ],
        ];
    }
}

if (!function_exists('site_page_ensure_collection')) {
    function site_page_ensure_collection($db): void
    {
        foreach ($db->listCollections() as $collectionInfo) {
            if ($collectionInfo->getName() === 'site_pages') {
                return;
            }
        }
        $db->createCollection('site_pages', [
            'validator' => site_page_collection_validator(),
        ]);
    }
}

if (!function_exists('site_page_build_legal_document')) {
    /**
     * @return array<string, mixed>
     */
    function site_page_build_legal_document(string $key, UTCDateTime $now): array
    {
        if (!site_page_is_legal_key($key)) {
            throw new InvalidArgumentException('Unbekannte Rechtsseite: ' . $key);
        }
        $defaults = site_page_legal_defaults()[$key];

        return [
            '_id' => $key,
            'page_kind' => 'legal',
            'title' => $defaults['title'],
            'intro' => $defaults['intro'],
            'provider_name' => $defaults['provider_name'],
            'provider_address' => $defaults['provider_address'],
            'contact_email' => $defaults['contact_email'],
            'sections' => site_page_normalize_sections($defaults['sections'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
            'updated_by' => '',
        ];
    }
}

if (!function_exists('site_page_seed_legal_page')) {
    /**
     * Legt/ersetzt einen Rechtstext-Datensatz mit Platzhalter-Inhalten.
     *
     * @return 'inserted'|'replaced'|'skipped'
     */
    function site_page_seed_legal_page($db, string $key, bool $onlyIfMissing = false): string
    {
        site_page_ensure_collection($db);
        if (!site_page_is_legal_key($key)) {
            throw new InvalidArgumentException('Unbekannte Rechtsseite: ' . $key);
        }
        $existing = $db->site_pages->findOne(['_id' => $key]);
        if ($onlyIfMissing && $existing) {
            return 'skipped';
        }
        $now = new UTCDateTime();
        $doc = site_page_build_legal_document($key, $now);
        if ($existing) {
            unset($doc['created_at']);
            $doc['created_at'] = $existing['created_at'] ?? $now;
        }
        $db->site_pages->replaceOne(['_id' => $key], $doc, ['upsert' => true]);

        return $existing ? 'replaced' : 'inserted';
    }
}

if (!function_exists('site_page_seed_home_profile')) {
    /**
     * Legt/ersetzt den Startseiten-Datensatz mit Platzhalter-Inhalten.
     *
     * @return 'inserted'|'replaced'|'skipped'
     */
    function site_page_seed_home_profile($db, bool $onlyIfMissing = false): string
    {
        site_page_ensure_collection($db);
        $existing = $db->site_pages->findOne(['_id' => 'home_profile']);
        if ($onlyIfMissing && $existing) {
            return 'skipped';
        }
        $hadExisting = (bool) $existing;
        $defaults = site_page_home_profile_defaults();
        $now = new UTCDateTime();
        $createdAt = $now;
        if ($hadExisting) {
            $existingArr = iterator_to_array($existing);
            $createdAt = $existingArr['created_at'] ?? $now;
        }
        $doc = [
            '_id' => 'home_profile',
            'page_kind' => 'home_profile',
            'display_name' => $defaults['display_name'],
            'kicker' => $defaults['kicker'],
            'lead' => $defaults['lead'],
            'body' => $defaults['body'],
            'portrait_url' => $defaults['portrait_url'],
            'created_at' => $createdAt,
            'updated_at' => $now,
            'updated_by' => '',
        ];
        $db->site_pages->replaceOne(['_id' => 'home_profile'], $doc, ['upsert' => true]);

        return $hadExisting ? 'replaced' : 'inserted';
    }
}

if (!function_exists('site_page_normalize_sections')) {
    /**
     * @param mixed $sections
     * @return array<int, array{heading: string, body: string}>
     */
    function site_page_normalize_sections($sections): array
    {
        if (!is_array($sections)) {
            return [];
        }
        $normalized = [];
        foreach ($sections as $section) {
            if ($section instanceof Traversable) {
                $section = iterator_to_array($section);
            }
            if (!is_array($section)) {
                continue;
            }
            $heading = trim((string) ($section['heading'] ?? ''));
            $body = trim((string) ($section['body'] ?? ''));
            if ($heading === '' && $body === '') {
                continue;
            }
            $normalized[] = [
                'heading' => $heading,
                'body' => $body,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('site_page_merge_legal')) {
    /**
     * @param array<string, mixed>|null $doc
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    function site_page_merge_legal(?array $doc, array $defaults): array
    {
        $doc = $doc ?? [];
        $sections = site_page_normalize_sections($doc['sections'] ?? null);
        if ($sections === []) {
            $sections = site_page_normalize_sections($defaults['sections'] ?? []);
        }

        return [
            'title' => trim((string) ($doc['title'] ?? $defaults['title'] ?? '')),
            'intro' => (string) ($doc['intro'] ?? $defaults['intro'] ?? ''),
            'provider_name' => trim((string) ($doc['provider_name'] ?? $defaults['provider_name'] ?? '')),
            'provider_address' => trim((string) ($doc['provider_address'] ?? $defaults['provider_address'] ?? '')),
            'contact_email' => trim((string) ($doc['contact_email'] ?? $defaults['contact_email'] ?? '')),
            'sections' => $sections,
        ];
    }
}

if (!function_exists('site_page_load_legal')) {
    /**
     * @return array<string, mixed>
     */
    function site_page_load_legal(string $key, $db = null): array
    {
        if (!site_page_is_legal_key($key)) {
            throw new InvalidArgumentException('Unbekannte Rechtsseite: ' . $key);
        }
        $defaults = site_page_legal_defaults()[$key];
        $doc = null;
        try {
            if ($db === null && isset($GLOBALS['db'])) {
                $db = $GLOBALS['db'];
            }
            if ($db !== null) {
                $found = $db->site_pages->findOne(['_id' => $key]);
                if ($found) {
                    $doc = is_array($found) ? $found : iterator_to_array($found);
                }
            }
        } catch (Exception $e) {
            $doc = null;
        }

        return site_page_merge_legal($doc, $defaults);
    }
}

if (!function_exists('site_page_render_section_body')) {
    function site_page_render_section_body(string $body): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $listItems = [];
        $blocks = [];
        $buffer = [];

        $flushParagraph = function () use (&$buffer, &$blocks): void {
            if ($buffer === []) {
                return;
            }
            $text = trim(implode("\n", $buffer));
            if ($text !== '') {
                $blocks[] = '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
            $buffer = [];
        };

        $flushList = function () use (&$listItems, &$blocks): void {
            if ($listItems === []) {
                return;
            }
            $html = '<ul>';
            foreach ($listItems as $item) {
                $html .= '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
            $blocks[] = $html;
            $listItems = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushParagraph();
                $flushList();
                continue;
            }
            if (strpos($trimmed, '- ') === 0) {
                $flushParagraph();
                $listItems[] = substr($trimmed, 2);
                continue;
            }
            $flushList();
            $buffer[] = $trimmed;
        }
        $flushParagraph();
        $flushList();

        if ($blocks === []) {
            return '';
        }

        return implode("\n", $blocks);
    }
}

if (!function_exists('site_page_render_legal')) {
    /**
     * @param array<string, mixed> $page
     */
    function site_page_render_legal(string $key, array $page): void
    {
        $title = trim((string) ($page['title'] ?? ''));
        if ($title === '') {
            $title = ucfirst($key);
        }
        $intro = trim((string) ($page['intro'] ?? ''));
        $providerName = trim((string) ($page['provider_name'] ?? ''));
        $providerAddress = trim((string) ($page['provider_address'] ?? ''));
        $contactEmail = trim((string) ($page['contact_email'] ?? ''));
        $sections = site_page_normalize_sections($page['sections'] ?? []);
        $showProvider = $providerName !== '' || $providerAddress !== '';
        $showContact = $contactEmail !== '';
        ?>
        <section class="card" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
            <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>

            <?php if ($intro !== ''): ?>
                <p class="field-hint"><?php echo nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>

            <?php if ($showProvider): ?>
                <h2>Diensteanbieter</h2>
                <p>
                    <?php if ($providerName !== ''): ?>
                        <strong>Name:</strong> <?php echo htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if ($providerAddress !== ''): ?>
                        <strong>Anschrift:</strong> <?php echo htmlspecialchars($providerAddress, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($showContact): ?>
                <h2>Kontakt</h2>
                <p>
                    <strong>E-Mail:</strong>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php foreach ($sections as $section): ?>
                <?php
                    $heading = trim((string) ($section['heading'] ?? ''));
                    $body = trim((string) ($section['body'] ?? ''));
                    if ($heading === '' && $body === '') {
                        continue;
                    }
                    $bodyHtml = site_page_render_section_body($body);
                ?>
                <?php if ($heading !== ''): ?>
                    <h2><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php endif; ?>
                <?php if ($bodyHtml !== ''): ?>
                    <?php echo $bodyHtml; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
        <?php
    }
}

if (!function_exists('site_page_render_legal_edit_fab')) {
    function site_page_render_legal_edit_fab(string $key): void
    {
        if (!site_page_can_edit_content()) {
            return;
        }
        $href = 'pages/admin/legal_page_edit.php?key=' . rawurlencode($key);
        $labels = [
            'impressum' => 'Impressum bearbeiten',
            'datenschutz' => 'Datenschutz bearbeiten',
            'nutzungsbedingungen' => 'Nutzungsbedingungen bearbeiten',
        ];
        $label = $labels[$key] ?? 'Seite bearbeiten';
        ?>
        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="fab fab-edit" title="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo svg_icon_pencil(22); ?>
        </a>
        <?php
    }
}
