<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;



class User extends Authenticatable implements FilamentUser

{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'smtp_username',
        'smtp_password',
        'signature_image', 
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'password' => 'hashed',
        ];
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function signature()
    {
        return $this->hasOne(UserSignature::class);
    }


    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return !$this->hasRole('Telemedicine Doctor'); // ❌ Block doctors from Admin Panel
        }

        if ($panel->getId() === 'doctor') {
            return $this->hasRole('Telemedicine Doctor'); // ✅ Allow only doctors to Doctor Panel
        }

        return true; // ❌ Block access to any other panels
    }

    public function getProfilePhotoUrlAttribute(): string
{
    return asset('/publiclogo.png'); // ✅ Use your logo.png as the default image
}

}
