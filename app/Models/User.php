<?php

// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status', 'last_login'];

    /**
     * Get the user's status based on email verification
     */
    public function getStatusAttribute(): string
    {
        return $this->email_verified_at ? 'active' : 'inactive';
    }

    /**
     * Get the user's last login (placeholder - you can implement actual last login tracking)
     */
    public function getLastLoginAttribute(): ?string
    {
        // This is a placeholder. In a real app, you'd track actual last login times
        return $this->updated_at?->toISOString();
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the user's cart
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Get the user's orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's addresses
     */
    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }
}
