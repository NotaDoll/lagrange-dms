<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = ['tenant_id', 'category', 'description', 'status'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
