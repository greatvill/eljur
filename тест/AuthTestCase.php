<?php

namespace Tests\Feature\Base;

use App\User;
use Tests\TestCase;

class AuthTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $user = factory(User::class)->make();
        $this->actingAs($user, 'api');
    }
}
