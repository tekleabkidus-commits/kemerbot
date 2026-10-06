<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use Database\Factories\AdminFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements FilamentUser, HasAppAuthentication
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token', 'app_authentication_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'app_authentication_secret' => 'encrypted',
            'role' => AdminRole::class,
        ];
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // All roles may enter the panel; what they can do is enforced by policies.
        return true;
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class, 'created_by');
    }

    public function isOwner(): bool
    {
        return $this->role === AdminRole::Owner;
    }

    public function isViewer(): bool
    {
        return $this->role === AdminRole::Viewer;
    }
}
