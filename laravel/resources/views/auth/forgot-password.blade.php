<x-guest-layout>
    <h1>Passwort vergessen</h1>

    <p class="field-hint" style="margin-bottom:1rem;">
        Gib deine E-Mail-Adresse an. Wir senden dir einen Link zum Zurücksetzen deines Passworts.
    </p>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert">
            <div style="margin-bottom:0.5rem;"><b>❌ Bitte prüfe deine Eingabe:</b></div>
            <ul style="margin:0; padding-left:1.25rem;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <input
            type="email"
            name="email"
            placeholder="E-Mail Adresse"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="email"
        >

        <button type="submit">Link senden</button>
    </form>

    <hr class="account-divider">

    <p class="field-hint">
        <a href="{{ route('login') }}">Zurück zum Login</a>
    </p>
</x-guest-layout>
