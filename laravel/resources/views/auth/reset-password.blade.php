<x-guest-layout>
    <h1>Neues Passwort</h1>

    <p class="field-hint" style="margin-bottom:1rem;">
        Wähle ein neues Passwort für <strong>{{ old('email', $request->email) }}</strong>.
    </p>

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

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

        <input
            type="password"
            name="password"
            placeholder="Neues Passwort"
            required
            autofocus
            autocomplete="new-password"
        >

        <input
            type="password"
            name="password_confirmation"
            placeholder="Passwort wiederholen"
            required
            autocomplete="new-password"
        >

        <button type="submit">Speichern</button>
    </form>

    <hr class="account-divider">

    <p class="field-hint">
        <a href="{{ route('login') }}">Zurück zum Login</a>
    </p>
</x-guest-layout>
