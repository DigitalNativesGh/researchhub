<?php

namespace App\Models;

use App\Enums\DegreeLevel;
use App\Enums\WorkflowStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'academic' => 'Academic/Lecturer',
        'independent' => 'Independent Researcher',
        'student' => 'Student',
        'industry' => 'Industry Practitioner',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'timezone', 'institution', 'field_of_study', 'bio',
        'avatar_path', 'email_alerts', 'push_notifications', 'password_changed_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'email_alerts' => 'boolean',
            'push_notifications' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** A successful payment for this research type that has not been used for a project yet. */
    public function paymentCreditFor(DegreeLevel $level): ?Payment
    {
        return $this->payments()->unused()->where('degree_level', $level->value)->oldest('id')->first();
    }

    /** Paid for (or free at the current price), so the details step of the wizard is open. */
    public function canStartProject(DegreeLevel $level): bool
    {
        return Payment::priceFor($level) <= 0 || $this->paymentCreditFor($level) !== null;
    }

    public function aiCalls(): HasMany
    {
        return $this->hasMany(AiCall::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Researcher';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^(dr|prof|mr|mrs|ms)\.?\s+/i', '', $this->name)));

        return Str::upper(collect($parts)->filter()->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('')) ?: 'RH';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('profile.photo', ['v' => $this->updated_at?->timestamp]) : null;
    }

    public function firstName(): string
    {
        $name = trim($this->name);

        if (preg_match('/^(dr|prof)\.?\s+(\S+)/i', $name, $m)) {
            return Str::ucfirst(Str::lower($m[1])).'. '.$m[2];
        }

        return Str::before($name, ' ') ?: $name;
    }

    public function projectsNeedingAttentionCount(): int
    {
        return once(fn () => $this->projects()
            ->where('workflow_status', WorkflowStatus::Stage1Delivered->value)
            ->count());
    }

    public function deleteAvatar(): void
    {
        if ($this->avatar_path) {
            Storage::disk(config('researchhub.disk'))->delete($this->avatar_path);
        }
    }
}
