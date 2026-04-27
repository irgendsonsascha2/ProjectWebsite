<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Passwort vergessen? Kein Problem. Gib deine E-Mail-Adresse an und wir senden dir einen Link zum Zurücksetzen.') }}
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input
                id="email"
                class="block mt-1 w-full"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="email"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a
                class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                href="{{ route('login') }}"
            >
                {{ __('Zurück zum Login') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Link senden') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
