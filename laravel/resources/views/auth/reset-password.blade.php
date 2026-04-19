<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Neues Passwort setzen</title>
</head>
<body>
<h1>Neues Passwort</h1>
@if ($errors->any())
    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif
<form method="POST" action="{{ route('password.store') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <input type="hidden" name="email" value="{{ old('email', $request->email) }}">
    <p>E-Mail: <strong>{{ old('email', $request->email) }}</strong></p>
    <label>Neues Passwort <input type="password" name="password" required autocomplete="new-password"></label><br><br>
    <label>Wiederholen <input type="password" name="password_confirmation" required autocomplete="new-password"></label><br><br>
    <button type="submit">Speichern</button>
</form>
</body>
</html>
