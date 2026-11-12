<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $first = fake()->firstNameFemale();
        $last = fake()->lastName();

        return [
            'first_name' => $first,
            'last_name' => $last,
            'name' => $first.' '.$last,
            'email' => fake()->safeEmail(),
            'status' => Tenant::STATUS_ACTIVE,
        ];
    }
}
