<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): int
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user);
        return DB::table('customers')->insertGetId(['user_id' => $user->getKey(), 'first_name' => 'Test', 'last_name' => 'Shopper', 'email' => $user->email, 'date_registered' => now()->toDateString()]);
    }

    public function test_profile_saves_only_validated_fields(): void
    {
        $customer = $this->customer();
        Volt::test('customer.account.profile')->set('form', [
            'first_name' => 'Updated', 'last_name' => 'Shopper', 'email' => auth()->user()->email,
            'phone' => '09170000000', 'address' => 'Test address', 'user_id' => null,
        ])->call('saveProfile')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['customer_id' => $customer, 'user_id' => auth()->id(), 'first_name' => 'Updated']);
    }

    public function test_customer_cannot_read_internal_notes_or_reply_to_a_stale_closed_ticket(): void
    {
        $customer = $this->customer();
        $ticket = DB::table('tickets')->insertGetId(['customer_id' => $customer, 'ticket_number' => 'AUDIT-1', 'subject' => 'Audit ticket', 'issue_description' => 'Synthetic test ticket', 'status' => 'open', 'priority' => 'medium', 'category' => 'general', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([false, true] as $internal) DB::table('ticket_notes')->insert(['ticket_id' => $ticket, 'note_text' => $internal ? 'STAFF-PRIVATE-NOTE' : 'PUBLIC-REPLY', 'created_by' => auth()->id(), 'internal' => $internal, 'created_at' => now(), 'updated_at' => now()]);
        $component = Volt::test('customer.support.index')->call('selectTicketForUpdate', $ticket)->assertSee('PUBLIC-REPLY')->assertDontSee('STAFF-PRIVATE-NOTE');
        DB::table('tickets')->where('ticket_id', $ticket)->update(['status' => 'closed']);
        $component->set('newMessage', 'Must not be recorded')->call('addMessageToTicket');
        $this->assertDatabaseCount('ticket_notes', 2);
    }

    public function test_journal_rejects_client_injected_negative_lines(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $account = DB::table('chart_of_accounts')->insertGetId(['account_code' => 'AUDIT', 'account_name' => 'Audit cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        Volt::test('finance.accounts')->set('entryDate', '2026-10-15')->set('journalDescription', 'Synthetic journal test')
            ->set('journalItems', [['account_id' => $account, 'debit' => -100, 'credit' => 0], ['account_id' => $account, 'debit' => 0, 'credit' => -100]])
            ->call('saveJournalEntry')->assertHasErrors(['journalItems.0.debit', 'journalItems.1.credit']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_custom_admin_login_requires_two_factor_and_can_complete_recovery_challenge(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['audit-recovery-code']))]);
        Volt::test('auth.adminlogin')->set('username', $user->username)->set('password', 'password')->call('login')->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertEquals($user->getKey(), session('login.id'));
        $this->post('/two-factor-challenge', ['recovery_code' => 'audit-recovery-code'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_livewire_rechecks_portal_access_after_role_changes(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get('/finance/accounts')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        $admin->update(['role' => 'customer']);
        $this->actingAs($admin->fresh())->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'showNewAccountModal', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertForbidden();
    }
}
