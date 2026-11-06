<?php

namespace Tests\Unit;

use App\Helpers\ValidateAccessEmployee;
use App\Models\Employee;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_level_two_user_can_access_employee_from_same_general_direction(): void
    {
        $user = new User([
            'level_id' => '2',
            'general_direction_id' => '16',
        ]);

        $employee = new Employee([
            'employee_number' => '12345',
            'general_direction_id' => '16',
        ]);

        $this->assertTrue(ValidateAccessEmployee::validateUser($user, $employee));
    }
}
