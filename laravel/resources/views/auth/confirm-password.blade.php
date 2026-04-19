<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Passwort bestätigen</title>
</head>
<body>
<h1>Passwort bestätigen</h1>
<form method="POST" action="{{ route('password.confirm') }}">
    @csrf
    <input type="password" name="password" required placeholder="Aktuelles Passwort" autocomplete="current-password">
    <button type="submit">Weiter</button>
</form>
</body>
</html>
