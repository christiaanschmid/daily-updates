<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\Desktop;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    /**
     * The chosen timezone, or an empty string to use the default.
     */
    public string $timezone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->timezone = Auth::user()->timezone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        $user->fill([...$validated, 'timezone' => $validated['timezone'] ?: null]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    /**
     * Every timezone, grouped by region for the picker.
     *
     * @return array<string, list<string>>
     */
    #[Computed]
    public function timezones(): array
    {
        $groups = [];

        foreach (timezone_identifiers_list() as $timezone) {
            // "America/Phoenix" goes under "America"; "UTC" has no region.
            $region = explode('/', $timezone, 2)[0];
            $groups[$region === $timezone ? 'Other' : $region][] = $timezone;
        }

        return $groups;
    }

    /**
     * What an unset timezone means: the Mac's own in the desktop app, the app's default on the web.
     */
    #[Computed]
    public function defaultTimezoneLabel(): string
    {
        return Desktop::running()
            ? __('Same as this Mac (:timezone)', ['timezone' => Desktop::systemTimezone() ?? config('app.default_timezone')])
            : __('Default (:timezone)', ['timezone' => config('app.default_timezone')]);
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        $user = Auth::user();

        // Deleting the account needs its password, which the desktop app's user never set.
        if (Desktop::running()) {
            return false;
        }

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }
}
