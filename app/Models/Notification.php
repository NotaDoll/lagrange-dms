<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'reference', 'message', 'is_read'];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Where clicking the notification takes the user, or null when it is information only. */
    public function url(): ?string
    {
        return match ($this->type) {
            'rent_reminder' => route('tenant.payments.index'),
            default => null,
        };
    }

    /** Short text for the link, shown under the message. */
    public function linkLabel(): ?string
    {
        return match ($this->type) {
            'rent_reminder' => 'View my payments',
            default => null,
        };
    }
}
