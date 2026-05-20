<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Если таблица называется users — можно не указывать.
     */
    protected $table = 'users';

    /**
     * Массово заполняемые поля (под твою БД).
     */
    protected $fillable = [
        'username',
        'email',
        'phone',
        'first_name',
        'last_name',
        'password',
        'is_staff',
        'bonus_points',
        'fcm_token',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * Скрывать при отдаче JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Касты (типы).
     */
    protected $casts = [
        'is_staff' => 'boolean',
        'bonus_points' => 'integer',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        // если хочешь авто-хеширование при присваивании:
        'password' => 'hashed',
    ];

    /**
     * Удобный computed name (не обязательно)
     */
    public function getNameAttribute(): string
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : ($this->username ?? '');
    }

    public function favoriteDesserts(): BelongsToMany
    {
        return $this->belongsToMany(Dessert::class, 'dessert_favorites')
            ->withTimestamps();
    }
}
