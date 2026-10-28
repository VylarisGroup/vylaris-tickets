<?php

namespace Tests\Feature;

use App\Mail\PartyInvitationMail;
use App\Models\Organizer;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartyTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'i@v.ch']);
        $this->user = User::factory()->create();
        $this->organizer->users()->attach($this->user, ['role' => 'owner']);
    }

    private function party(array $extra = []): Party
    {
        return $this->organizer->parties()->create($extra + [
            'title' => 'Sommerfest', 'host_name' => 'Ina', 'starts_at' => now()->addDays(20), 'venue_name' => 'Garten', 'venue_address' => 'Weg 1, Bern',
            'allow_companions' => true, 'max_companions' => 2, 'allow_items' => true, 'allow_comments' => true,
        ]);
    }

    public function test_organizer_creates_a_party_with_cover_and_theme(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user)->get(route('organizer.parties.create', $this->organizer))->assertOk()->assertSee('Farbthema');

        $this->actingAs($this->user)->post(route('organizer.parties.store', $this->organizer), [
            'title' => 'Sommerfest', 'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'theme' => 'sunset', 'font' => 'serif',
            'allow_companions' => '1', 'max_companions' => 3, 'cover' => UploadedFile::fake()->image('c.jpg', 800, 400),
        ])->assertSessionHasNoErrors();

        $party = $this->organizer->parties()->firstOrFail();
        $this->assertSame('sunset', $party->theme);
        $this->assertSame(3, $party->max_companions);
        $this->assertFalse($party->allow_items);
        Storage::disk('public')->assertExists($party->cover_path);
        $this->actingAs($this->user)->get(route('organizer.parties.index', $this->organizer))->assertOk()->assertSee('Sommerfest');

        $this->actingAs($this->user)->post(route('organizer.parties.store', $this->organizer), ['title' => 'X', 'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->subDay()->format('Y-m-d\TH:i'), 'theme' => 'nope', 'font' => 'modern'])
            ->assertSessionHasErrors(['ends_at', 'theme']);
    }

    public function test_guest_list_import_skips_duplicates_and_bad_lines_and_sends_mails_once(): void
    {
        Mail::fake();
        $party = $this->party();
        $this->actingAs($this->user)->post(route('organizer.parties.guests.store', [$this->organizer, $party]), ['guests' => "Anna Muster; anna@example.ch\nBen, ben@example.ch\nCara ohne Mail\nanna@example.ch\nDoris; kaputt@\n\nlena.meier@example.ch"])
            ->assertSessionHas('status');

        $guests = $party->guests()->get();
        $this->assertCount(4, $guests);
        $this->assertSame('Lena Meier', $guests->firstWhere('email', 'lena.meier@example.ch')->name);
        $this->assertNull($guests->firstWhere('name', 'Cara ohne Mail')->email);
        $this->assertTrue($guests->every(fn ($g) => strlen($g->token) === 32));

        $this->actingAs($this->user)->post(route('organizer.parties.send', [$this->organizer, $party]))->assertSessionHas('status');
        Mail::assertSent(PartyInvitationMail::class, 3);
        $this->actingAs($this->user)->post(route('organizer.parties.send', [$this->organizer, $party]))->assertSessionHas('error_status');
        Mail::assertSent(PartyInvitationMail::class, 3);

        $this->actingAs($this->user)->post(route('organizer.parties.remind', [$this->organizer, $party]))->assertSessionHas('status');
        Mail::assertSent(PartyInvitationMail::class, 6);
    }

    public function test_guest_replies_via_personal_link_and_host_sees_counts(): void
    {
        $party = $this->party();
        $anna = $party->guests()->create(['name' => 'Anna', 'email' => 'a@example.ch']);
        $ben = $party->guests()->create(['name' => 'Ben']);

        $this->get(route('party.show', $anna->token))->assertOk()->assertSee('Sommerfest')->assertSee('Hallo Anna')->assertSee('Kommst du?')->assertSee('noindex', false);
        $this->get(route('party.show', 'unbekannt'))->assertNotFound();

        $this->post(route('party.rsvp', $anna->token), ['response' => 'yes', 'companions' => 2, 'companion_names' => ['Carl', 'Dora'], 'message' => 'Freue mich'])->assertSessionHas('status');
        $this->post(route('party.rsvp', $ben->token), ['response' => 'maybe', 'companions' => 2])->assertSessionHas('status');
        $this->post(route('party.rsvp', $ben->token), ['response' => 'yes', 'companions' => 5])->assertSessionHasErrors('companions');
        $this->post(route('party.rsvp', $ben->token), ['response' => 'yes', 'companions' => 1, 'companion_names' => ['']])->assertSessionHasErrors('companion_names');

        $this->assertSame(0, $ben->fresh()->companions); // nur Zusagen haben Begleitung
        $counts = $party->fresh()->counts();
        $this->assertSame(3, $counts['yes_people']);
        $this->assertSame(1, $counts['maybe']);

        $this->get(route('party.show', $ben->token))->assertSee('Anna'); // sieht, wer zugesagt hat
        $this->actingAs($this->user)->get(route('organizer.parties.show', [$this->organizer, $party]))->assertOk()->assertSee('Freue mich');
        $csv = $this->actingAs($this->user)->get(route('organizer.parties.guests.export', [$this->organizer, $party]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Anna;a@example.ch;Zugesagt;2;"Carl, Dora";"Freue mich"', $csv);
    }

    public function test_closed_deadline_blocks_replies(): void
    {
        $party = $this->party(['rsvp_deadline' => now()->subHour()]);
        $g = $party->guests()->create(['name' => 'Anna']);
        $this->post(route('party.rsvp', $g->token), ['response' => 'yes'])->assertSessionHas('error_status');
        $this->assertNull($g->fresh()->response);
    }

    public function test_items_are_claimed_once_and_comments_work(): void
    {
        $party = $this->party();
        $a = $party->guests()->create(['name' => 'Anna']);
        $b = $party->guests()->create(['name' => 'Ben']);
        $this->actingAs($this->user)->post(route('organizer.parties.items.store', [$this->organizer, $party]), ['label' => 'Salat'])->assertSessionHasNoErrors();
        $item = $party->items()->firstOrFail();

        $this->post(route('party.claim', [$a->token, $item->id]))->assertSessionHas('status');
        $this->post(route('party.claim', [$b->token, $item->id]))->assertSessionHas('error_status');
        $this->assertSame($a->id, $item->fresh()->guest_id);

        $this->delete(route('party.release', [$b->token, $item->id]))->assertNotFound(); // fremden Eintrag nicht freigeben
        $this->post(route('party.items.store', $b->token), ['label' => 'Kuchen'])->assertSessionHas('status');
        $mine = $party->items()->where('label', 'Kuchen')->firstOrFail();
        $this->delete(route('party.release', [$b->token, $mine->id]));
        $this->assertNull($party->items()->find($mine->id)); // eigener Eintrag verschwindet

        $this->post(route('party.comment', $a->token), ['body' => 'Bin gespannt!'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('organizer.parties.comments.store', [$this->organizer, $party]), ['body' => 'Willkommen'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('party.show', $b->token))->assertSee('Bin gespannt!')->assertSee('Willkommen')->assertSee('Gastgeber');

        $party->update(['allow_comments' => false, 'allow_items' => false]);
        $this->post(route('party.comment', $a->token), ['body' => 'x'])->assertNotFound();
        $this->post(route('party.claim', [$b->token, $item->id]))->assertNotFound();
    }

    public function test_calendar_file_and_isolation_between_organizers(): void
    {
        $party = $this->party();
        $g = $party->guests()->create(['name' => 'Anna']);
        $this->get(route('party.calendar', $g->token))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('SUMMARY:Sommerfest', false);

        $other = Organizer::create(['name' => 'Verein B', 'slug' => 'verein-b', 'contact_email' => 'b@v.ch']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'owner']);
        $this->actingAs($stranger)->get(route('organizer.parties.show', [$other, $party]))->assertNotFound();
        $this->actingAs($stranger)->get(route('organizer.parties.show', [$this->organizer, $party]))->assertForbidden();
        $this->actingAs($stranger)->post(route('organizer.parties.guests.store', [$this->organizer, $party]), ['guests' => 'X'])->assertForbidden();
    }

    public function test_questions_schedule_and_answers_are_saved_and_exported(): void
    {
        $this->actingAs($this->user)->post(route('organizer.parties.store', $this->organizer), [
            'title' => 'Fest', 'starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'theme' => 'ocean', 'font' => 'modern',
            'schedule_text' => "19:30 Essen\n18:00 Apéro", 'questions_text' => "Essen | Fleisch, Vegi\nAllergien", 'invite_message' => 'Bis bald!', 'auto_remind_days' => 3,
        ])->assertSessionHasNoErrors();
        $party = $this->organizer->parties()->firstOrFail();
        $this->assertSame('18:00', $party->schedule[0]['time']); // sortiert
        $this->assertCount(2, $party->questions);
        $this->assertSame(['Fleisch', 'Vegi'], $party->questions[0]['options']);
        $this->assertStringContainsString("18:00 Apéro", $party->scheduleText());

        $this->actingAs($this->user)->post(route('organizer.parties.store', $this->organizer), ['title' => 'X', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'theme' => 'ocean', 'font' => 'modern', 'schedule_text' => 'Apéro um sechs'])
            ->assertSessionHasErrors('schedule_text');

        $g = $party->guests()->create(['name' => 'Anna']);
        $this->get(route('party.show', $g->token))->assertSee('Ablauf')->assertSee('Apéro')->assertSee('Allergien');
        [$q1, $q2] = $party->questions;
        $this->post(route('party.rsvp', $g->token), ['response' => 'yes', 'answers' => [$q1['id'] => 'Vegi', $q2['id'] => 'Nüsse']])->assertSessionHasNoErrors();
        $this->post(route('party.rsvp', $g->token), ['response' => 'yes', 'answers' => [$q1['id'] => 'Pizza', $q2['id'] => 'Nüsse']]); // ungültige Option wird verworfen
        $this->assertSame(['Nüsse'], array_values($g->fresh()->answers));
        $this->post(route('party.rsvp', $g->token), ['response' => 'no', 'answers' => [$q2['id'] => 'x']]);
        $this->assertNull($g->fresh()->answers);

        $this->post(route('party.rsvp', $g->token), ['response' => 'maybe', 'answers' => [$q1['id'] => 'Fleisch']]);
        $csv = $this->actingAs($this->user)->get(route('organizer.parties.guests.export', [$this->organizer, $party]))->streamedContent();
        $this->assertStringContainsString('Essen;Allergien', $csv);
        $this->assertStringContainsString('Vielleicht;;;;Fleisch;', $csv);
    }

    public function test_confirmation_mail_message_to_group_and_auto_reminders(): void
    {
        Mail::fake();
        $party = $this->party(['invite_message' => 'Bis bald!', 'auto_remind_days' => 3, 'starts_at' => now()->addDays(2)]);
        $a = $party->guests()->create(['name' => 'Anna', 'email' => 'a@example.ch', 'invited_at' => now()->subDay()]);
        $b = $party->guests()->create(['name' => 'Ben', 'email' => 'b@example.ch', 'invited_at' => now()->subDay(), 'response' => 'yes']);
        $c = $party->guests()->create(['name' => 'Cara', 'email' => 'c@example.ch']); // nie eingeladen

        $this->post(route('party.rsvp', $a->token), ['response' => 'yes'])->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\PartyResponseMail::class, fn ($m) => $m->hasTo('a@example.ch') && count($m->attachments()) === 1);
        $this->post(route('party.rsvp', $a->token), ['response' => 'no']);
        Mail::assertSent(\App\Mail\PartyResponseMail::class, fn ($m) => $m->guest->response === 'no' && count($m->attachments()) === 0);

        $this->actingAs($this->user)->post(route('organizer.parties.message', [$this->organizer, $party]), ['audience' => 'yes', 'subject' => 'Ortswechsel', 'body' => 'Wir treffen uns im Park.'])->assertSessionHas('status');
        Mail::assertSent(\App\Mail\PartyMessageMail::class, 1);
        Mail::assertSent(\App\Mail\PartyMessageMail::class, fn ($m) => $m->hasTo('b@example.ch'));
        $this->actingAs($this->user)->post(route('organizer.parties.message', [$this->organizer, $party]), ['audience' => 'all', 'subject' => '', 'body' => 'x'])->assertSessionHasErrors('subject');

        // Automatische Erinnerung: nur eingeladene Gäste ohne Antwort, nur einmal.
        $d = $party->guests()->create(['name' => 'Dora', 'email' => 'd@example.ch', 'invited_at' => now()->subDay()]);
        $this->artisan('tickets:send-party-reminders')->assertSuccessful();
        Mail::assertSent(PartyInvitationMail::class, 1);
        Mail::assertSent(PartyInvitationMail::class, fn ($m) => $m->reminder && $m->hasTo('d@example.ch'));
        $this->artisan('tickets:send-party-reminders');
        Mail::assertSent(PartyInvitationMail::class, 1);
        $this->assertNotNull($d->fresh()->auto_reminded_at);

        // Zu früh: Beginn in 20 Tagen, Erinnerung erst 3 Tage vorher.
        $party->update(['starts_at' => now()->addDays(20)]);
        $e = $party->guests()->create(['name' => 'Eva', 'email' => 'e@example.ch', 'invited_at' => now()]);
        $this->artisan('tickets:send-party-reminders');
        $this->assertNull($e->fresh()->auto_reminded_at);
    }

    public function test_previews_render_without_a_real_guest(): void
    {
        $party = $this->party(['invite_message' => 'Bis bald!']);
        $this->actingAs($this->user)->get(route('organizer.parties.preview', [$this->organizer, $party]))->assertOk()->assertSee('Vorschau')->assertSee('Bis bald!')->assertSee('disabled', false);
        $this->actingAs($this->user)->get(route('organizer.parties.mail-preview', [$this->organizer, $party]))->assertOk()->assertSee('Bis bald!')->assertSee('Vorschau-Gast');
    }
}
