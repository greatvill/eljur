<?php

namespace Tests\Feature\User;

use App\Models\Role;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Base\Filterable;
use Tests\Feature\Base\AuthTestCase;

class UserTest extends AuthTestCase
{
    use Filterable;
    use DatabaseTransactions;

    public function testFilterById()
    {
        $this->clearData();
        for ($i = 1; $i <= 3; $i++) {
            factory(User::class)->create(['id' => $i]);
        }
        $this->assertFilter(route('user.list'), ['id' => [1, 2]], [1, 2]);
    }

    public function testFilterByRole()
    {
        $this->clearData();
        factory(User::class)->state(Role::CODE_STUDENT)->create(['id' => 1]);
        factory(User::class)->state(Role::CODE_ADMIN)->create(['id' => 2]);
        factory(User::class)->state(Role::CODE_STUDENT)->create(['id' => 3]);
        $this->assertFilter(route('user.list'), ['role' => Role::CODE_STUDENT], [1, 3]);
    }

    private function clearData()
    {
        User::query()->delete();
    }
}
