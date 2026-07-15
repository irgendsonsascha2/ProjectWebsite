# Deployment-Plan: externes Hosting und CI/CD

## Empfehlung

Für den aktuellen Stack ist ein kleiner Hetzner-Cloud-Server in Deutschland plus MongoDB Atlas die pragmatischste Lösung.

- **Web/App:** Hetzner Cloud, Ubuntu LTS, mindestens 2 vCPU / 4 GB RAM
- **Datenbank:** MongoDB Atlas in einer EU-/Deutschland-Region; Produktionszugriff nur von der festen Server-IP
- **Webserver:** Caddy oder nginx mit PHP-FPM 8.4
- **Mail:** Transaktionaler SMTP-Anbieter mit TLS
- **DNS/TLS:** eigene Domain; TLS über Caddy oder Let's Encrypt
- **Medien:** zunächst persistentes Server-Volume für `content/images` und `content/videos`
- **Backups:** Atlas-Backup plus tägliches verschlüsseltes Medien-Backup in getrennten Object Storage

Ein reines PaaS ist für diese Anwendung derzeit unpassend: Die klassische PHP-App und Laravel laufen gemeinsam, Uploads werden lokal gespeichert und einzelne Uploads dürfen sehr groß sein. Ein VPS bildet diese Voraussetzungen ohne vorherigen Storage-Umbau ab.

## Deploy-Regel

- Jeder Pull Request und jeder Branch-Push führt `.github/workflows/ci.yml` aus.
- Nur ein erfolgreicher CI-Lauf für `main` startet das Produktions-Deployment.
- Ein gemergter Pull Request ist ein Push auf `main` und wird daher automatisch deployed.
- Pull-Request-Code wird nicht vor dem Merge in Produktion deployed.
- Ein manueller Produktionslauf ist über `workflow_dispatch` möglich.
- Das Deployment verwendet exakt den von CI geprüften Commit-SHA.

## Einmalige Einrichtung

1. Hetzner-Server und optional separates Volume anlegen.
2. Nicht-root Deploy-Benutzer, SSH-Key, Firewall (22 eingeschränkt, 80/443 öffentlich) und automatische Security-Updates konfigurieren.
3. PHP 8.4 mit MongoDB-Erweiterung, Composer, Node.js 20+, npm, Git, Caddy/nginx und PHP-FPM installieren.
4. Repository nach `DEPLOY_PATH` klonen; serverseitige `.env.local` und `laravel/.env` anlegen.
5. Runtime-Daten außerhalb des Git-Checkout anlegen, zum Beispiel unter `/var/lib/projectwebsite/`; `content/images`, `content/videos`, `content/tmp` und `logs` als Symlinks dorthin einbinden und für PHP schreibbar machen.
6. MongoDB-Atlas-Cluster anlegen, Netzwerkzugriff auf die Server-IP begrenzen, DB-Benutzer/Rollen einrichten und die vorhandenen Daten importieren.
7. GitHub-Environment `production` anlegen und auf Branch `main` begrenzen.
8. GitHub-Environment-Secrets setzen: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `DEPLOY_PATH`.
9. GitHub-Environment-Variablen setzen: `PRODUCTION_URL`, optional `PHP_FPM_SERVICE=php8.4-fpm`.
10. Dem Deploy-Benutzer ausschließlich den Reload des angegebenen PHP-FPM-Service per sudo erlauben.

## Erstübertragung der Daten

1. Vor dem Export Schreibzugriffe lokal kurz sperren.
2. MongoDB mit `mongodump` exportieren und in Atlas mit `mongorestore` importieren.
3. `content/images` und `content/videos` per `rsync` auf das persistente Server-Volume kopieren.
4. DB-Baseline und Laravel-Migrationen ausführen.
5. `make deploy-check` und den Smoke-Test aus `docs/smoke_test.md` durchführen.
6. Erst danach DNS auf den neuen Server umstellen.

## Betrieb

- Täglich Medien und Konfiguration verschlüsselt extern sichern; Restore regelmäßig testen.
- Atlas-Backups aktivieren. Der Free-Tier allein ersetzt kein Produktionsbackup.
- Uptime, Zertifikatsablauf, Speicherplatz, PHP-Fehler und Backup-Erfolg überwachen.
- Deployments werden serialisiert; ein fehlgeschlagenes Deployment stoppt vor dem Service-Reload.
- Nächster Architektur-Schritt bei wachsendem Medienvolumen: Medienzugriff auf S3-kompatiblen Object Storage umstellen. Das braucht zuerst eine Storage-Abstraktion im PHP-Code und ist nicht nur eine Deploy-Konfiguration.
