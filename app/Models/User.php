<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Ems\FunctionalArea;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status_id', 'managed_domain'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasOneTimePasswords;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function status()
    {
        return $this->belongsTo(GlobalStatus::class, 'status_id');
    }

    public function functionalAreas()
    {
        return $this->belongsToMany(FunctionalArea::class, 'functional_area_user');
    }

    /**
     * Two-letter initials used for avatars across Material Planning.
     */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name));
        $first = mb_substr($words[0] ?? '', 0, 1);
        $second = mb_substr($words[1] ?? '', 0, 1);
        return mb_strtoupper($first . $second);
    }
}
