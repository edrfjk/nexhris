<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Everyone can manage their own account. Nobody can promote themselves or
 * move themselves into another college, because the college decides who
 * approves their leave.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public static function everyRole(): array
    {
        return [
            'employee' => ['employee'],
            'dean' => ['dean'],
            'campus director' => ['campus_director'],
            'hr administrator' => ['admin'],
        ];
    }

    private function person(string $role = 'employee'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'password' => Hash::make('original-password'),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('everyRole')]
    public function test_every_role_reaches_their_profile_from_the_sidebar(string $role): void
    {
        $user = $this->person($role);
        $home = $role === 'employee' ? route('employee.dashboard') : route('admin.dashboard');

        $this->actingAs($user)->get($home)->assertOk()
            ->assertSee(route('profile.edit'), false);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_a_person_updates_their_own_details(): void
    {
        $user = $this->person('campus_director');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'contact_number' => '09171234567',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertSame('09171234567', $user->contact_number);
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        $taken = $this->person();
        $user = $this->person('dean');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $taken->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertNotSame($taken->email, $user->fresh()->email);
    }

    public function test_nobody_can_promote_themselves_or_move_college(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $cte = College::where('code', 'CTE')->firstOrFail();

        $user = $this->person('employee');
        $user->update(['college_id' => $cas->id, 'employee_number' => 'EMP-9001']);

        // The college picks the approving Dean, so self-service edits here
        // would let someone choose who signs their leave.
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'college_id' => $cte->id,
            'employee_number' => 'EMP-0001',
            'position' => 'Campus Director',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('employee', $user->role);
        $this->assertSame($cas->id, $user->college_id);
        $this->assertSame('EMP-9001', $user->employee_number);
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $user = $this->person('campus_director');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'not-the-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_a_password_change_with_the_current_one_succeeds(): void
    {
        $user = $this->person('dean');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'original-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_mismatched_new_passwords_are_rejected(): void
    {
        $user = $this->person();

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'original-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_a_person_replaces_their_own_photo(): void
    {
        Storage::fake('public');
        $user = $this->person('campus_director');

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertRedirect();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_guest_cannot_open_a_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_hr_managed_fields_as_read_only(): void
    {
        $cas = College::where('code', 'CAS')->firstOrFail();
        $user = $this->person('campus_director');
        $user->update(['college_id' => $cas->id, 'employee_number' => 'EMP-7788']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Managed by HR')
            ->assertSee('EMP-7788')
            ->assertSee('College of Arts and Sciences');
    }

    public function test_hr_is_not_told_their_own_profile_is_managed_by_hr(): void
    {
        // They are HR. The panel names who to ask about fields you cannot
        // edit, which is nobody in their case.
        $this->actingAs($this->person('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Managed by HR')
            ->assertDontSee('Ask HR if any of them is wrong');
    }

    public function test_hr_is_not_offered_personal_record_links(): void
    {
        $this->actingAs($this->person('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('My Personal Data Sheet')
            ->assertDontSee('My Leave Ledger');
    }

    public function test_hr_still_changes_their_own_password(): void
    {
        $hr = $this->person('admin');

        $this->actingAs($hr)->put(route('profile.password.update'), [
            'current_password' => 'original-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-password', $hr->fresh()->password));
    }
}
