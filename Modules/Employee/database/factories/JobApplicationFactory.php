<?php

namespace Modules\Employee\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
use Modules\Employee\Models\JobApplication;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'job_title' => fake()->jobTitle(),
            'status' => ApplicationStatus::Submitted,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'father_name' => fake()->firstNameMale(),
            'nationality' => Nationality::Iranian,
            'national_code' => self::nationalCode(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years'),
            'gender' => Gender::Female,
            'mobile' => '09'.fake()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'postal_code' => fake()->numerify('##########'),
            'address' => fake()->address(),
            'education' => [[
                'level' => EducationLevel::Bachelor->value, 'major' => 'ریاضی', 'orientation' => null,
                'average' => 17.5, 'rank' => null, 'graduation_date' => '2015-06-21',
                'university_type' => UniversityType::State->value, 'university' => 'دانشگاه تهران',
            ]],
            'work_history' => [],
        ];
    }

    public function closed(ApplicationStatus $status = ApplicationStatus::Rejected): static
    {
        return $this->state(['status' => $status]);
    }

    /**
     * A random valid Iranian national code.
     */
    public static function nationalCode(): string
    {
        do {
            $code = fake()->numerify('#########');
        } while (preg_match('/^(\d)\1{8}$/', $code));

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $remainder = $sum % 11;

        return $code.($remainder < 2 ? $remainder : 11 - $remainder);
    }
}
