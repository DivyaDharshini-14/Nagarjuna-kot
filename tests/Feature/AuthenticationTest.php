<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_active_user_can_login_and_logout_on_the_web(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
            'is_active' => false,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_cannot_login_when_branch_does_not_belong_to_company(): void
    {
        $company = Company::create([
            'name' => 'Company A',
            'code' => 'COMP-A',
            'is_active' => true,
        ]);
        $otherCompany = Company::create([
            'name' => 'Company B',
            'code' => 'COMP-B',
            'is_active' => true,
        ]);
        $branch = Branch::create([
            'company_id' => $otherCompany->id,
            'name' => 'Wrong Branch',
            'code' => 'WRONG',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'password' => Hash::make('correct-password'),
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
