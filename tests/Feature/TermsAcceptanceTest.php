<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nobody signs in without agreeing to the terms first.
 *
 * The checkbox on the form carries `required`, which a browser enforces and
 * anyone can switch off from the developer console. What actually holds is the
 * rule in LoginController, so that is what these tests exercise: a post with no
 * agreement is refused even when the password is perfectly correct.
 */
class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Dela Cruz, Juan M.',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'correct-horse-battery',
            'role' => 'employee',
            'status' => 'active',
            'college_id' => College::firstOrCreate(
                ['code' => 'CAS'], ['name' => 'College of Arts and Sciences'])->id,
        ]);
    }

    // ------------------------------------------------------------------
    // The gate
    // ------------------------------------------------------------------

    public function test_a_correct_password_is_not_enough_without_the_agreement(): void
    {
        $user = $this->employee();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_an_unticked_box_is_refused(): void
    {
        $user = $this->employee();

        // An unchecked box sends nothing at all, but a hand-built post can
        // still send a falsey value.
        foreach (['0', '', 'false', 'no'] as $value) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'correct-horse-battery',
                'terms' => $value,
            ])->assertSessionHasErrors('terms');

            $this->assertGuest();
        }
    }

    public function test_the_refusal_says_what_is_missing(): void
    {
        $user = $this->employee();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors([
            'terms' => 'You must read and accept the Terms and Conditions before signing in.',
        ]);
    }

    public function test_agreeing_lets_the_sign_in_through(): void
    {
        $user = $this->employee();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Auth::attempt matches every key it is handed against a column, so the
     * agreement must not travel with the credentials.
     */
    public function test_the_agreement_is_not_treated_as_a_credential(): void
    {
        $user = $this->employee();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'terms' => 'on',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------------------------
    // Reading what you are agreeing to
    // ------------------------------------------------------------------

    public function test_the_terms_are_readable_without_signing_in(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('Data Privacy Act');
    }

    public function test_the_sign_in_screen_offers_the_agreement_and_a_way_to_read_it(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('I agree with the')
            ->assertSee('name="terms"', false)
            ->assertSee(route('terms'));
    }

    /**
     * The dialog on the sign-in screen and the standalone page include one
     * partial, so a user cannot be shown different terms in the two places.
     */
    public function test_both_places_show_the_same_terms(): void
    {
        $marker = 'is the personal information controller for these records';

        $this->get('/login')->assertSee($marker);
        $this->get(route('terms'))->assertSee($marker);
    }

    /** What the sheet actually collects has to be named, not implied. */
    public function test_the_terms_name_what_hr_collects(): void
    {
        $this->get(route('terms'))
            ->assertSee('Personal Data Sheet')
            ->assertSee('Republic Act No. 10173')
            ->assertSee('Human Resource Management Office');
    }
}
