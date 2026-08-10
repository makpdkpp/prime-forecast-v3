<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\ConditionalSoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, ConditionalSoftDeletes;

    protected $table = 'user';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'role_id',
        'nname',
        'surename',
        'avatar_path',
        'position_id',
        'is_active',
        'reset_token',
        'token_expiry',
        'two_factor_enabled',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'reset_token',
        'two_factor_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'token_expiry' => 'datetime',
        'is_active' => 'boolean',
        'role_id' => 'integer',
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(RoleCatalog::class, 'role_id', 'role_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled()
    {
        return $this->two_factor_enabled;
    }

    public function verifyCurrentPassword(string $plainTextPassword): bool
    {
        $stored = (string) $this->password;

        if ($stored === '') {
            return false;
        }

        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            $isLegacyMd5 = hash_equals(strtolower($stored), md5($plainTextPassword));

            if ($isLegacyMd5) {
                $this->password = \Illuminate\Support\Facades\Hash::make($plainTextPassword);
                $this->save();
            }

            return $isLegacyMd5;
        }

        return \Illuminate\Support\Facades\Hash::check($plainTextPassword, $stored);
    }

    /**
     * Generate a new 2FA code
     */
    public function generateTwoFactorCode()
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->two_factor_code = \Illuminate\Support\Facades\Hash::make($code);
        $this->two_factor_expires_at = now()->addMinutes(5);
        $this->save();
        return $code;
    }

    /**
     * Verify 2FA code
     */
    public function verifyTwoFactorCode($code)
    {
        if (!$this->two_factor_code || !$this->two_factor_expires_at) {
            return false;
        }
        
        if (now()->isAfter($this->two_factor_expires_at)) {
            return false;
        }
        
        return \Illuminate\Support\Facades\Hash::check($code, $this->two_factor_code);
    }

    /**
     * Reset 2FA code
     */
    public function resetTwoFactorCode()
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    /**
     * Get masked email for display
     */
    public function getMaskedEmailAttribute()
    {
        $email = $this->email;
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        if (strlen($name) <= 3) {
            return $name[0] . '***@' . $domain;
        }
        
        return substr($name, 0, 4) . '***@' . $domain;
    }
    
    /**
     * Get the forecast targets for the user.
     */
    public function forecastTargets()
    {
        return $this->hasMany(UserForecastTarget::class, 'user_id', 'user_id');
    }
    
    /**
     * Get forecast target for a specific year.
     */
    public function getForecastTargetForYear($year)
    {
        return $this->forecastTargets()->where('fiscal_year', $year)->first();
    }
}
