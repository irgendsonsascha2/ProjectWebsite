<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="registration_code" :value="__('Registrierungscode')" />
            <x-text-input id="registration_code" class="block mt-1 w-full" type="text" name="registration_code" :value="old('registration_code', $prefilled_code)" required autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('registration_code')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autocomplete="username" />
            <p class="mt-1 text-xs text-gray-500">3–20 Zeichen: a–z, 0–9, . _ -</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Passwort')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Passwort bestätigen')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-4">
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="content_responsibility_consent" value="1" required class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>
                    Ich habe die
                    <a class="underline" href="{{ (config('legacy.site_url') ? config('legacy.site_url') : '') }}/index.php?page=nutzungsbedingungen" target="_blank" rel="noopener noreferrer">
                        Nutzungsbedingungen
                    </a>
                    gelesen und übernehme die Verantwortung für Inhalte, die ich hochlade oder kommentiere.
                </span>
            </label>
            <x-input-error :messages="$errors->get('content_responsibility_consent')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Bereits registriert?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Konto erstellen') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
