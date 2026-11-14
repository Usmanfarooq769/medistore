<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use  HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', 'is_active', 'branch_id', 'manager_pin', 'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'manager_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'permissions'       => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Effective permission: admin = all, else per-user override, else role default. */
    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) return true;
        $overrides = (array) ($this->permissions ?? []);
        if (array_key_exists($key, $overrides)) return (bool) $overrides[$key];
        $defaults = config('permissions.roles.' . $this->role, []);
        return in_array('*', $defaults, true) || in_array($key, $defaults, true);
    }

    /** All effective permissions as [key => bool] (used by the SPA to hide buttons). */
    public function effectivePermissions(): array
    {
        $out = [];
        foreach (array_keys(config('permissions.labels', [])) as $key) $out[$key] = $this->hasPermission($key);
        return $out;
    }

    public function setManagerPin(?string $pin): void
    {
        $this->manager_pin = $pin ? \Illuminate\Support\Facades\Hash::make($pin) : null;
    }
}
