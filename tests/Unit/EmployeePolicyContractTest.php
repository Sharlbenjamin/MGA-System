<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;
use PHPUnit\Framework\TestCase;

class EmployeePolicyContractTest extends TestCase
{
    public function test_admin_can_generate_contract(): void
    {
        $user = new User;
        $user->setRelation('roles', collect([(object) ['name' => 'admin']]));

        $employee = new Employee;

        $this->assertTrue((new EmployeePolicy)->generateContract($user, $employee));
    }

    public function test_non_admin_cannot_generate_contract(): void
    {
        $user = new User;
        $user->setRelation('roles', collect([(object) ['name' => 'operator']]));

        $employee = new Employee;

        $this->assertFalse((new EmployeePolicy)->generateContract($user, $employee));
    }
}
