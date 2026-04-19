<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil</title>
</head>
<body>
<h1>Profil</h1>
@if (session('status'))
    <p>{{ session('status') }}</p>
@endif
@if ($errors->any())
    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif
<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')
    <label>Username <input type="text" name="username" value="{{ old('username', $user->username) }}" required></label><br><br>
    <label>E-Mail <input type="email" name="email" value="{{ old('email', $user->email) }}" required></label><br><br>
    <button type="submit">Speichern</button>
</form>

<hr>
<h2>Passwort ändern</h2>
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('put')
    <label>Aktuelles Passwort <input type="password" name="current_password" required autocomplete="current-password"></label><br><br>
    <label>Neues Passwort <input type="password" name="password" required autocomplete="new-password"></label><br><br>
    <label>Wiederholen <input type="password" name="password_confirmation" required autocomplete="new-password"></label><br><br>
    <button type="submit">Passwort ändern</button>
</form>

<p><a href="{{ route('dashboard') }}">Dashboard</a></p>
</body>
</html>
