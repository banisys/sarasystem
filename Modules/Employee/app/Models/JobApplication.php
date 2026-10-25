<?php

namespace Modules\Employee\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Database\Factories\JobApplicationFactory;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\MilitaryStatus;
use Modules\Employee\Enums\Nationality;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An application sent through the public «همکاری با ما» form (ADR 0014).
 * No user is created until the applicant is hired. A national code has at
 * most one open application (closed_at null) in the whole installation.
 *
 * @property array<int, array<string, mixed>> $education
 * @property array<int, array<string, mixed>> $work_history
 */
#[Fillable([
    'tenant_id', 'job_position_id', 'job_title', 'status',
    'first_name', 'last_name', 'father_name', 'nationality', 'national_code', 'birth_date', 'gender', 'military_status',
    'mobile', 'phone', 'email', 'postal_code', 'address', 'education', 'work_history',
])]
class JobApplication extends Model implements HasMedia
{
    /** @use HasFactory<JobApplicationFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    public const RESUME_COLLECTION = 'resume';

    protected $table = 'employee_job_applications';

    protected static function newFactory(): JobApplicationFactory
    {
        return JobApplicationFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (JobApplication $application): void {
            if (! $application->status->isClosed()) {
                $application->closed_at = null;
            } elseif ($application->closed_at === null) {
                $application->closed_at = now();
            }
        });
    }

    /**
     * The open application of a national code, in any tenant.
     */
    public static function openFor(string $nationalCode): ?self
    {
        return static::withoutTenantScope()->open()->where('national_code', $nationalCode)->first();
    }

    /**
     * Only the private disk: résumés are personal data, downloaded through
     * the admin panel.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::RESUME_COLLECTION)
            ->useDisk('local')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * @param  Builder<JobApplication>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * @return BelongsTo<JobPosition, $this>
     */
    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'closed_at' => 'datetime',
            'nationality' => Nationality::class,
            'gender' => Gender::class,
            'military_status' => MilitaryStatus::class,
            'birth_date' => 'date',
            'education' => 'array',
            'work_history' => 'array',
        ];
    }
}
