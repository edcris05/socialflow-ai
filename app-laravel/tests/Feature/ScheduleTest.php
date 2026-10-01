<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_schedules_approved_draft_and_keeps_audit_history_immutable(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft, $snapshot, $run] = $this->approvedDraft();
        $approvedAt = $draft->approved_at->toDateTimeString();
        $runContent = $run->generated_content;

        $this->actingAs($user)
            ->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-02T14:30'])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $publication = ScheduledPublication::query()->sole();
        $this->assertSame($brand->getKey(), $publication->brand_id);
        $this->assertSame($draft->getKey(), $publication->draft_id);
        $this->assertSame($user->getKey(), $publication->scheduled_by);
        $this->assertSame('scheduled', $publication->status);
        $this->assertSame('2026-10-02 14:30:00', $publication->scheduled_for->toDateTimeString());
        $this->assertSame(Draft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertSame($approvedAt, $draft->fresh()->approved_at->toDateTimeString());
        $this->assertSame($runContent, $run->fresh()->generated_content);
        $this->assertSame('Promocionar stickers', $snapshot->fresh()->query);
        $this->assertNull($publication->cancelled_at);
        $this->assertSame('PROGRAMADO', $publication->statusLabel());
        Http::assertNothingSent();
    }

    public function test_non_approved_drafts_cannot_be_scheduled(): void
    {
        [$user, $brand, $draft] = $this->draft();
        $this->actingAs($user)
            ->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-02T14:30'])
            ->assertUnprocessable();

        $draft->update(['status' => Draft::STATUS_REJECTED]);
        $this->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-02T14:30'])
            ->assertUnprocessable();
        $this->assertSame(0, ScheduledPublication::count());
    }

    public function test_past_scheduled_time_is_rejected(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();

        $this->followingRedirects()->actingAs($user)
            ->withHeader('referer', route('marcas.borradores.edit', [$brand, $draft]))
            ->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-01T11:59'])
            ->assertOk()
            ->assertSee('La fecha y hora deben ser posteriores al momento actual.')
            ->assertSee('La fecha y hora deben estar en el futuro.');
        $this->assertSame(0, ScheduledPublication::count());
    }

    public function test_current_scheduled_time_is_rejected(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();

        $this->actingAs($user)
            ->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-01T12:00'])
            ->assertSessionHasErrors('scheduled_for');
        $this->assertSame(0, ScheduledPublication::count());
    }

    public function test_schedule_and_reschedule_forms_expose_a_future_minimum(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();
        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertSee('min="2026-10-01T12:00"', false);
        $publication = $this->schedule($user, $brand, $draft, '2026-10-02T13:00');

        $this->get(route('marcas.programacion.index', $brand))
            ->assertSee('min="2026-10-01T12:00"', false)
            ->assertSee('La fecha y hora deben ser posteriores al momento actual.');
        $this->assertSame('scheduled', $publication->fresh()->status);
    }

    public function test_future_schedule_is_ready_only_when_time_arrives(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();
        $this->schedule($user, $brand, $draft, '2026-10-01T13:00');
        $publication = ScheduledPublication::query()->sole();

        $this->assertFalse($publication->isReady());
        $this->assertSame('PROGRAMADO', $publication->statusLabel());
        $this->travelTo('2026-10-01 13:00:00');
        $this->assertTrue($publication->fresh()->isReady());
        $this->assertSame('LISTO', $publication->fresh()->statusLabel());
        $this->assertSame('scheduled', $publication->fresh()->status);
    }

    public function test_cancellation_records_actor_and_time_without_deleting_schedule(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();
        $publication = $this->schedule($user, $brand, $draft, '2026-10-02T13:00');
        $this->travelTo('2026-10-01 12:30:00');

        $this->actingAs($user)
            ->patch(route('marcas.programacion.cancel', [$brand, $publication]))
            ->assertRedirect(route('marcas.programacion.index', $brand));

        $publication->refresh();
        $this->assertSame('cancelled', $publication->status);
        $this->assertSame($user->getKey(), $publication->cancelled_by);
        $this->assertSame('2026-10-01 12:30:00', $publication->cancelled_at->toDateTimeString());
        $this->assertSame('CANCELADO', $publication->statusLabel());
        $this->assertDatabaseHas('scheduled_publications', ['id' => $publication->getKey()]);
    }

    public function test_rescheduling_updates_the_single_active_publication(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $draft] = $this->approvedDraft();
        $publication = $this->schedule($user, $brand, $draft, '2026-10-02T13:00');

        $this->actingAs($user)
            ->patch(route('marcas.programacion.update', [$brand, $publication]), ['scheduled_for' => '2026-10-03T15:45'])
            ->assertRedirect(route('marcas.programacion.index', $brand));

        $this->assertSame(1, ScheduledPublication::query()->count());
        $this->assertSame('2026-10-03 15:45:00', $publication->fresh()->scheduled_for->toDateTimeString());

        $this->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-04T16:00'])
            ->assertRedirect();
        $this->assertSame(1, ScheduledPublication::query()->where('status', 'scheduled')->count());
        $this->assertSame('2026-10-04 16:00:00', $publication->fresh()->scheduled_for->toDateTimeString());
    }

    public function test_cancelled_publication_can_be_replaced_by_one_active_schedule(): void
    {
        [$user, $brand, $draft] = $this->approvedDraft();
        $publication = $this->schedule($user, $brand, $draft, '2026-10-02T13:00');
        $this->actingAs($user)->patch(route('marcas.programacion.cancel', [$brand, $publication]));
        $this->post(route('marcas.borradores.programar', [$brand, $draft]), ['scheduled_for' => '2026-10-03T13:00']);

        $this->assertSame(2, ScheduledPublication::query()->count());
        $this->assertSame(1, ScheduledPublication::query()->where('status', 'scheduled')->count());
        $this->assertSame(1, ScheduledPublication::query()->where('status', 'cancelled')->count());
    }

    public function test_cross_tenant_user_cannot_schedule_cancel_or_reschedule(): void
    {
        [$owner, $otherBrand, $otherDraft] = $this->approvedDraft();
        $publication = $this->schedule($owner, $otherBrand, $otherDraft, '2026-10-02T13:00');
        [$user, $brand] = $this->draft();
        $this->actingAs($user);

        $this->post(route('marcas.borradores.programar', [$brand, $otherDraft]), ['scheduled_for' => '2026-10-03T13:00'])->assertNotFound();
        $this->patch(route('marcas.programacion.cancel', [$brand, $publication]))->assertNotFound();
        $this->patch(route('marcas.programacion.update', [$brand, $publication]), ['scheduled_for' => '2026-10-03T13:00'])->assertNotFound();
        $this->get(route('marcas.programacion.index', $otherBrand))->assertNotFound();

        $this->assertSame('scheduled', $publication->fresh()->status);
    }

    public function test_calendar_lists_upcoming_ready_and_cancelled_items(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $brand, $firstDraft] = $this->approvedDraft('Upcoming');
        $upcoming = $this->schedule($user, $brand, $firstDraft, '2026-10-02T13:00');
        [$sameUser, $sameBrand, $readyDraft] = $this->approvedDraft('Ready', $user, $brand);
        $ready = $this->schedule($sameUser, $sameBrand, $readyDraft, '2026-10-01T11:00', allowPast: true);
        [$sameUser, $sameBrand, $cancelledDraft] = $this->approvedDraft('Cancelled', $user, $brand);
        $cancelled = $this->schedule($sameUser, $sameBrand, $cancelledDraft, '2026-10-03T13:00');
        $this->actingAs($user)->patch(route('marcas.programacion.cancel', [$brand, $cancelled]));

        $this->get(route('marcas.programacion.index', $brand))
            ->assertOk()
            ->assertSeeInOrder([$readyDraft->title, 'LISTO', $upcoming->draft->title, 'PROGRAMADO', $cancelledDraft->title, 'CANCELADO'])
            ->assertSee('Los elementos listos todavía no se publican automáticamente.');
    }

    public function test_approved_draft_remains_terminal_and_strategy_does_not_schedule_automatically(): void
    {
        [$user, $brand, $draft, $snapshot, $run] = $this->approvedDraft();
        $this->assertSame(Draft::STATUS_APPROVED, $draft->status);
        $this->assertSame(0, ScheduledPublication::count());
        $this->assertSame(1, GenerationRun::whereKey($run)->count());
        $this->assertSame('Promocionar stickers', $snapshot->query);

        $this->actingAs($user)->put(route('marcas.borradores.update', [$brand, $draft]), [
            'title' => 'Cambio bloqueado',
            'content' => 'Cambio bloqueado',
        ])->assertUnprocessable();
    }

    /** @return array{User, Brand, Draft} */
    private function draft(string $title = 'Draft'): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => $title,
            'content' => 'Contenido aprobado',
            'status' => Draft::STATUS_DRAFT,
        ]);

        return [$user, $brand, $draft];
    }

    /** @return array{User, Brand, Draft, ContextSnapshot, GenerationRun} */
    private function approvedDraft(string $title = 'Draft', ?User $user = null, ?Brand $brand = null): array
    {
        if (! $user || ! $brand) {
            [$user, $brand, $draft] = $this->draft($title);
        } else {
            $draft = Draft::create([
                'brand_id' => $brand->getKey(),
                'user_id' => $user->getKey(),
                'title' => $title,
                'content' => 'Contenido aprobado',
                'status' => Draft::STATUS_DRAFT,
            ]);
        }
        $snapshot = ContextSnapshot::create([
            'draft_id' => $draft->getKey(),
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'query' => 'Promocionar stickers',
            'relevant_knowledge' => [],
            'brand_context' => [],
            'policies' => [],
            'restrictions' => [],
            'pending_knowledge' => [],
            'sources' => [],
            'warnings' => [],
            'missing_information' => [],
            'matches' => [],
        ]);
        $run = GenerationRun::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'context_snapshot_id' => $snapshot->getKey(),
            'user_id' => $user->getKey(),
            'provider' => 'fake',
            'model' => 'fake-mini',
            'operation' => 'draft_content',
            'status' => 'succeeded',
            'generated_content' => 'Contenido aprobado',
            'evaluation_status' => 'passed',
            'grounding_status' => 'passed',
            'grounding_results' => [],
        ]);
        $this->actingAs($user)->patch(route('marcas.borradores.approve', [$brand, $draft]));

        return [$user, $brand, $draft->fresh(), $snapshot->fresh(), $run->fresh()];
    }

    private function schedule(User $user, Brand $brand, Draft $draft, string $scheduledFor, bool $allowPast = false): ScheduledPublication
    {
        $payload = ['scheduled_for' => $scheduledFor];
        if ($allowPast) {
            $this->travelTo('2026-10-01 10:00:00');
        }
        $this->actingAs($user)->post(route('marcas.borradores.programar', [$brand, $draft]), $payload)->assertRedirect();
        if ($allowPast) {
            $this->travelTo('2026-10-01 12:00:00');
        }

        return ScheduledPublication::query()->latest('id')->firstOrFail();
    }
}
