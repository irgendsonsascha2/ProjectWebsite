<?php
/**
 * Gemeinsamer Hilfetext für 2FA (Modal via HelpButton).
 * In verstecktem Container mit id="two-factor-help-content" einbinden.
 */
?>
<div class="two-factor-help-modal">
    <p>
        Die Zwei-Faktor-Authentifizierung schützt dein Konto zusätzlich zum Passwort.
        Du richtest eine <strong>Authenticator-App</strong> ein (z.&nbsp;B. Aegis, Google Authenticator, Bitwarden).
        Die App erzeugt alle 30&nbsp;Sekunden einen neuen <strong>6-stelligen Code</strong>.
    </p>
    <p><strong>Optional:</strong> Ohne 2FA funktioniert der Login wie bisher — nur mit E-Mail/Username und Passwort.</p>
    <p><strong>Login mit 2FA:</strong></p>
    <ol class="two-factor-steps">
        <li>Passwort wie gewohnt auf der Anmeldeseite eingeben.</li>
        <li>Code aus der Authenticator-App oder einen Backup-Code eingeben.</li>
    </ol>
    <p class="field-hint">
        Es gibt <strong>keinen</strong> zweiten Faktor per E-Mail beim Login.
        Backup-Codes sind bei der Einrichtung verfügbar; jeder Code ist nur <strong>einmal</strong> nutzbar.
    </p>
</div>
