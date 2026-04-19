<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort vergessen</title>
</head>
<body>
<h1>Passwort-Link anfordern</h1>
@if (session('status'))
    <p>{{ session('status') }}</p>
@endif
@if ($errors->any())
    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <label>E-Mail <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
    <button type="submit">Link senden</button>
</form>
<p><a href="{{ route('login') }}">Zurück zum Login</a></p>
</body>
</html>
