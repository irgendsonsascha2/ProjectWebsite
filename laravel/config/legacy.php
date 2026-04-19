<?php

return [

    /*
    | Root-URL der alten PHP-App (dort liegen index.php und laravel_handoff.php).
    | Beispiel: http://127.0.0.1  oder  http://127.0.0.1:8080  (ohne abschließendes /)
    */
    /* Leer = Laravel zeigt eigene Login-/Register-Views; gesetzt = Umleitung zur klassischen Site (index.php). */
    'site_url' => rtrim((string) env('LEGACY_SITE_URL', ''), '/'),

    /*
    | Gemeinsames Geheimnis für HMAC — muss zur Auswertung in laravel_handoff.php passen
    | (wird aus laravel/.env gelesen).
    */
    'handoff_secret' => env('HANDOFF_SECRET', ''),

    /*
    | Ziel nach erfolgreicher Session: ?page=… für index.php
    */
    'after_login_page' => env('LEGACY_AFTER_LOGIN_PAGE', 'home'),

];
