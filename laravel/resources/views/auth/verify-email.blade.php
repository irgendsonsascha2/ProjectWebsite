<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Mail bestätigen</title>
</head>
<body>
<h1>E-Mail-Adresse bestätigen</h1>
<p>Bitte den Link in der E-Mail anklicken. Du kannst die klassische Website weiter nutzen.</p>
@if (session('status') === 'verification-link-sent')
    <p>Ein neuer Link wurde gesendet.</p>
@endif
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit">Bestätigungs-Link erneut senden</button>
</form>
<p><a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Abmelden</a></p>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
</body>
</html>
