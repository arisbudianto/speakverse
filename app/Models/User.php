<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Atribut yang boleh diisi melalui mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'google_avatar',
        'nip',
        'school',
        'major',
        'grade',
        'parallel',

        // Gamification
        'xp',
        'coins',
        'level',
        'current_streak',
        'longest_streak',
        'last_activity_date',
    ];

    /**
     * Atribut yang disembunyikan ketika model diserialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast atribut model.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Gamification
            'xp' => 'integer',
            'coins' => 'integer',
            'level' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_activity_date' => 'date',
        ];
    }

    /**
     * Mengecek apakah user adalah administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeacherClassAssignment::class);
    }

    /**
     * Seluruh hasil assessment milik pengguna.
     */
    public function assessmentSubmissions(): HasMany
    {
        return $this->hasMany(
            AssessmentSubmission::class
        );
    }

    /**
     * Seluruh hasil Vocabulary Pretest milik pengguna.
     */
    public function vocabularyPretestResults(): HasMany
    {
        return $this->hasMany(
            VocabularyPretestResult::class
        );
    }

    /**
     * Seluruh progress lesson milik pengguna.
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(
            UserLessonProgress::class
        );
    }

    /**
     * Badge / achievement yang sudah diperoleh pengguna.
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(
            Badge::class,
            'user_badges'
        )
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /**
     * Riwayat XP dan SpeakCoins pengguna.
     */
    public function gamificationTransactions(): HasMany
    {
        return $this->hasMany(
            GamificationTransaction::class
        );
    }

    /**
     * Reward yang sudah dibeli / dimiliki pengguna.
     */
    public function rewardItems(): BelongsToMany
    {
        return $this->belongsToMany(
            RewardItem::class,
            'user_reward_items'
        )
            ->withPivot([
                'quantity',
                'acquired_at',
            ])
            ->withTimestamps();
    }
}