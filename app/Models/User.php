<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'contact_number',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function tenant()
    {
        return $this->hasOne(Tenant::class);
    }

    // Overrides Notifiable's default notifications() relation — we use our own
    // custom `notifications` table (per ERD) rather than Laravel's built-in one.
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function aiSummaries()
    {
        return $this->hasMany(AiSummary::class, 'generated_by');
    }

    public function isProprietor(): bool
    {
        return $this->role === 'proprietor';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }
}
