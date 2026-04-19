<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
</head>
<body>
<p>Du bist in der Laravel-App eingeloggt.</p>
@php($legacy = rtrim((string) config('legacy.site_url', ''), '/'))
@if ($legacy !== '')
    <p><a href="{{ $legacy }}/index.php?page=home">Zur klassischen Website</a></p>
@endif
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Abmelden (Laravel)</button>
</form>
</body>
</html>
