<?php

namespace App\Filament\My\Pages;

use App\Models\CompanyRole;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in user's profile and settings: their company roles, notification choices,
 * password and sign out.
 *
 * Notification choices are a preview until there is a table to keep them in.
 */
class Profile extends Page
{
    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.profile';

    protected static ?string $slug = 'profile';

    protected static ?string $title = 'Profile';

    /**
     * @var list<array{key: string, label: string, hint: string}>
     */
    public const array NOTIFICATIONS = [
        ['key' => 'approval_waiting', 'label' => 'An RFP is waiting for my approval', 'hint' => 'As soon as it reaches your step'],
        ['key' => 'decision', 'label' => 'My RFP was approved, returned or rejected', 'hint' => 'With the approver’s remarks'],
        ['key' => 'paid', 'label' => 'My RFP was paid', 'hint' => 'When Treasury releases the payment'],
        ['key' => 'mention', 'label' => 'Someone @mentions me in a conversation', 'hint' => 'From the chat on any RFP'],
    ];

    /** @var array{current: string, password: string, password_confirmation: string} */
    public array $passwordForm = ['current' => '', 'password' => '', 'password_confirmation' => ''];

    /**
     * The user's roles, one row per company, home company first.
     *
     * @return Collection<int, array{company: string, home: bool, roles: list<string>}>
     */
    public function roles(): Collection
    {
        $user = $this->user();

        return $user->companyRoles()->with('company')->get()
            ->groupBy('company_id')
            ->map(fn (Collection $assignments): array => [
                'company' => $assignments->first()->company?->name ?? 'Unknown company',
                'home' => $assignments->first()->company_id === $user->company_id,
                'roles' => $assignments->map(fn (CompanyRole $assignment): string => $assignment->role->value)->unique()->sort()->values()->all(),
            ])
            ->sortBy([['home', 'desc'], ['company', 'asc']])
            ->values();
    }

    /**
     * Browser and system of this session, from the user agent.
     */
    public function thisDevice(): ?string
    {
        $agent = (string) request()->userAgent();

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $system = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $browser && $system ? "{$browser} on {$system}" : ($browser ?? $system);
    }

    /**
     * Returns whether it worked, so the sheet can close.
     */
    public function updatePassword(): bool
    {
        try {
            $this->validate([
                'passwordForm.current' => ['required', 'current_password:'.Filament::getAuthGuard()],
                'passwordForm.password' => ['required', 'confirmed', 'different:passwordForm.current', Password::defaults()],
            ], attributes: [
                'passwordForm.current' => 'current password',
                'passwordForm.password' => 'new password',
            ]);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return false;
        }

        $user = $this->user();
        $user->update(['password' => $this->passwordForm['password']]);

        // Keep this session signed in (AuthenticateSession compares the stored hash).
        session()->put(['password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword()]);

        $this->reset('passwordForm');

        Notification::make()->title('Password updated')->success()->send();

        return true;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->user()->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('') ?: '?';
    }

    public function user(): User
    {
        return auth()->user();
    }
}
