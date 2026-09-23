<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class UserFinancialAccessTest extends TestCase
{
    public function test_financial_manager_role_is_recognized_regardless_of_casing(): void
    {
        $user = $this->userWithRole('financial manager');

        $this->assertTrue($user->isFinancial());
    }

    public function test_operation_role_is_not_treated_as_financial(): void
    {
        $user = $this->userWithRole('Operation');

        $this->assertFalse($user->isFinancial());
    }

    protected function userWithRole(string $roleName): User
    {
        $user = new User;
        $role = new \stdClass;
        $role->name = $roleName;
        $user->setRelation('roles', collect([$role]));
        $user->setRelation('employee', null);
        $user->setRelation('signature', null);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
    }
}
