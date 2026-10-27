<?php

namespace Modules\Student\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Models\Preregistration;

/**
 * @extends Factory<Preregistration>
 */
class PreregistrationFactory extends Factory
{
    protected $model = Preregistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'grade_title' => 'متوسطه اول - هفتم',
            'status' => PreregistrationStatus::Submitted,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'father_name' => fake()->firstNameMale(),
            'nationality' => Nationality::Iranian,
            'national_code' => self::nationalCode(),
            'birth_certificate_number' => fake()->numerify('######'),
            'birth_date' => fake()->dateTimeBetween('-15 years', '-7 years'),
            'gender' => Gender::Male,
            'mobile' => '09'.fake()->numerify('#########'),
            'phone' => '021'.fake()->numerify('########'),
            'city' => 'تهران',
            'address' => fake()->address(),
            'previous_school' => 'دبستان نمونه',
        ];
    }

    public function closed(PreregistrationStatus $status = PreregistrationStatus::Rejected): static
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
