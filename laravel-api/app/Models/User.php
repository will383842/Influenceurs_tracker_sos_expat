<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'contact_types', 'territories', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'last_login_at' => 'datetime',
        'is_active'     => 'boolean',
        'contact_types' => 'array',
        'territories'   => 'array',
    ];

    public function influenceurs()
    {
        return $this->hasMany(Influenceur::class, 'assigned_to');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function objectives()
    {
        return $this->hasMany(Objective::class);
    }

    public function aiResearchSessions()
    {
        return $this->hasMany(AiResearchSession::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isResearcher(): bool
    {
        return $this->role === 'researcher';
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isAdmin()) return true;

        return match ($module) {
            'ai_research'    => in_array($this->role, ['admin', 'manager']),
            'content_engine' => in_array($this->role, ['admin', 'manager']),
            'templates'      => true,
            'team'           => $this->isAdmin(),
            'objectives'     => true,
            default          => true,
        };
    }
}
