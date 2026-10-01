<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_approves_draft_with_actor_and_time(): void
    {
        $this->travelTo('2026-10-01 12:34:56');
        [$user, $brand, $draft] = $this->draft();

        $this->actingAs($user)
            ->patch(route('marcas.borradores.approve', [$brand, $draft]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $draft->refresh();
        $this->assertSame(Draft::STATUS_APPROVED, $draft->status);
        $this->assertSame($user->getKey(), $draft->approved_by);
        $this->assertSame('2026-10-01 12:34:56', $draft->approved_at->toDateTimeString());
        $this->assertNull($draft->rejected_by);
        $this->assertNull($draft->rejected_at);

        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('APROBADO')
            ->assertSee('Aprobado para publicación.')
            ->assertSee('Aprobado por '.$user->name)
            ->assertSee('La aprobación no publicó ni programó contenido')
            ->assertDontSee('Guardar borrador')
            ->assertDontSee('Generar contenido');
    }

    public function test_authorized_user_rejects_and_reopens_without_erasing_rejection_audit(): void
    {
        $this->travelTo('2026-10-01 13:00:00');
        [$user, $brand, $draft] = $this->draft();

        $this->actingAs($user)
            ->patch(route('marcas.borradores.reject', [$brand, $draft]), [
                'rejection_reason' => 'Falta ajustar el llamado a la acción.',
            ])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $draft->refresh();
        $this->assertSame(Draft::STATUS_REJECTED, $draft->status);
        $this->assertSame($user->getKey(), $draft->rejected_by);
        $this->assertSame('2026-10-01 13:00:00', $draft->rejected_at->toDateTimeString());
        $this->assertSame('Falta ajustar el llamado a la acción.', $draft->rejection_reason);

        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('RECHAZADO')
            ->assertSee('Falta ajustar el llamado a la acción.')
            ->assertSee('Volver a borrador');

        $rejectedAt = $draft->rejected_at->toDateTimeString();
        $this->travelTo('2026-10-01 14:00:00');
        $this->patch(route('marcas.borradores.reopen', [$brand, $draft]))
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $draft->refresh();
        $this->assertSame(Draft::STATUS_DRAFT, $draft->status);
        $this->assertSame($user->getKey(), $draft->rejected_by);
        $this->assertSame($rejectedAt, $draft->rejected_at->toDateTimeString());
        $this->assertSame('Falta ajustar el llamado a la acción.', $draft->rejection_reason);

        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))->assertRedirect();

        $draft->refresh();
        $this->assertSame(Draft::STATUS_APPROVED, $draft->status);
        $this->assertSame($user->getKey(), $draft->approved_by);
        $this->assertSame($user->getKey(), $draft->rejected_by);
        $this->assertSame($rejectedAt, $draft->rejected_at->toDateTimeString());
    }

    public function test_manual_edit_is_identifiable_and_preserves_generation_evaluation_and_grounding(): void
    {
        $this->travelTo('2026-10-01 15:00:00');
        [$user, $brand, $draft, $snapshot] = $this->draft();
        $run = $this->generationRun($user, $brand, $draft, $snapshot, 'Contenido generado original');

        $this->actingAs($user)
            ->put(route('marcas.borradores.update', [$brand, $draft]), [
                'title' => 'Versión editada',
                'content' => 'Contenido editado por una persona',
                'status' => Draft::STATUS_APPROVED,
            ])
            ->assertRedirect(route('marcas.borradores.edit', [$brand, $draft]));

        $draft->refresh();
        $run->refresh();
        $this->assertSame(Draft::STATUS_DRAFT, $draft->status);
        $this->assertSame('Contenido editado por una persona', $draft->content);
        $this->assertSame('2026-10-01 15:00:00', $draft->manually_edited_at->toDateTimeString());
        $this->assertSame('Contenido generado original', $run->generated_content);
        $this->assertSame('requires_review', $run->evaluation_status);
        $this->assertSame([['code' => 'PRICE_REQUIRES_CONFIRMATION', 'message' => 'Precio sin confirmar.']], $run->evaluation_violations);
        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame([[
            'claim' => ['text' => 'Afirmación no respaldada'],
            'status' => 'UNKNOWN',
            'evidence_excerpt' => null,
        ]], $run->grounding_results);

        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Contenido editado manualmente después de la generación.')
            ->assertSee('corresponden a la versión generada original');

        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))->assertRedirect();

        $this->assertSame('Contenido generado original', $run->fresh()->generated_content);
        $this->assertSame('requires_review', $run->evaluation_status);
        $this->assertSame('requires_review', $run->grounding_status);
        $this->assertSame('Promocionar stickers', $snapshot->fresh()->query);
        $this->assertSame(1, ContextSnapshot::query()->whereKey($snapshot->getKey())->count());
    }

    public function test_requires_review_draft_can_be_approved_after_visible_warning(): void
    {
        [$user, $brand, $draft, $snapshot] = $this->draft();
        $run = $this->generationRun($user, $brand, $draft, $snapshot, 'Contenido con alertas');
        $this->actingAs($user);

        $this->get(route('marcas.borradores.edit', [$brand, $draft]))
            ->assertOk()
            ->assertSeeInOrder([
                'Hay alertas automáticas que requieren revisión.',
                'Aprobar',
            ]);

        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))->assertRedirect();

        $this->assertSame(Draft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertSame('succeeded', $run->fresh()->status);
        $this->assertSame('requires_review', $run->evaluation_status);
        $this->assertSame('requires_review', $run->grounding_status);
    }

    public function test_cross_tenant_user_cannot_approve_reject_or_edit_draft(): void
    {
        [$otherUser, $otherBrand, $otherDraft] = $this->draft();
        [$user, $brand] = $this->draft();
        $this->actingAs($user);

        $this->patch(route('marcas.borradores.approve', [$brand, $otherDraft]))->assertNotFound();
        $this->patch(route('marcas.borradores.reject', [$brand, $otherDraft]), [
            'rejection_reason' => 'No autorizado',
        ])->assertNotFound();
        $this->put(route('marcas.borradores.update', [$brand, $otherDraft]), [
            'title' => 'Manipulado',
            'content' => 'Manipulado',
        ])->assertNotFound();

        $otherDraft->refresh();
        $this->assertSame($otherUser->getKey(), $otherDraft->user_id);
        $this->assertSame($otherBrand->getKey(), $otherDraft->brand_id);
        $this->assertSame(Draft::STATUS_DRAFT, $otherDraft->status);
        $this->assertSame('Contenido inicial', $otherDraft->content);
        $this->assertNull($otherDraft->approved_by);
        $this->assertNull($otherDraft->rejected_by);
    }

    public function test_approved_draft_rejects_further_transitions_edits_and_generation(): void
    {
        [$user, $brand, $draft] = $this->draft();
        $this->actingAs($user);
        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))->assertRedirect();

        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))
            ->assertUnprocessable();
        $this->patch(route('marcas.borradores.reject', [$brand, $draft]))
            ->assertUnprocessable();
        $this->patch(route('marcas.borradores.reopen', [$brand, $draft]))
            ->assertUnprocessable();
        $this->put(route('marcas.borradores.update', [$brand, $draft]), [
            'title' => 'Cambio inválido',
            'content' => 'Cambio inválido',
        ])->assertUnprocessable();

        Http::preventStrayRequests();
        $this->post(route('marcas.borradores.generar', [$brand, $draft]), [
            'generation_token' => str_repeat('a', 40),
        ])->assertUnprocessable();

        $draft->refresh();
        $this->assertSame(Draft::STATUS_APPROVED, $draft->status);
        $this->assertSame('Contenido inicial', $draft->content);
        $this->assertSame(0, GenerationRun::count());
        Http::assertNothingSent();
    }

    public function test_rejected_draft_must_return_to_draft_before_editing_or_approval(): void
    {
        [$user, $brand, $draft] = $this->draft();
        $this->actingAs($user);
        $this->patch(route('marcas.borradores.reject', [$brand, $draft]))->assertRedirect();

        $this->patch(route('marcas.borradores.approve', [$brand, $draft]))
            ->assertUnprocessable();
        $this->patch(route('marcas.borradores.reject', [$brand, $draft]))
            ->assertUnprocessable();
        $this->put(route('marcas.borradores.update', [$brand, $draft]), [
            'title' => 'Cambio inválido',
            'content' => 'Cambio inválido',
        ])->assertUnprocessable();

        $this->patch(route('marcas.borradores.reopen', [$brand, $draft]))->assertRedirect();
        $this->put(route('marcas.borradores.update', [$brand, $draft]), [
            'title' => 'Revisado',
            'content' => 'Contenido revisado',
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame(Draft::STATUS_DRAFT, $draft->status);
        $this->assertSame('Contenido revisado', $draft->content);
    }

    public function test_approval_preserves_all_prior_generation_runs(): void
    {
        [$user, $brand, $draft, $snapshot] = $this->draft();
        $first = $this->generationRun($user, $brand, $draft, $snapshot, 'Primera generación');
        $second = $this->generationRun($user, $brand, $draft, $snapshot, 'Segunda generación');

        $this->actingAs($user)
            ->patch(route('marcas.borradores.approve', [$brand, $draft]))
            ->assertRedirect();

        $this->assertSame(2, $draft->generationRuns()->count());
        $this->assertSame('Primera generación', $first->fresh()->generated_content);
        $this->assertSame('Segunda generación', $second->fresh()->generated_content);
        $this->assertSame('requires_review', $first->grounding_status);
        $this->assertSame('requires_review', $second->grounding_status);
    }

    /** @return array{User, Brand, Draft, ContextSnapshot} */
    private function draft(): array
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $user->brands()->attach($brand, ['role' => 'owner']);
        $draft = Draft::create([
            'brand_id' => $brand->getKey(),
            'user_id' => $user->getKey(),
            'title' => 'Borrador de prueba',
            'content' => 'Contenido inicial',
            'status' => Draft::STATUS_DRAFT,
        ]);
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

        return [$user, $brand, $draft, $snapshot];
    }

    private function generationRun(
        User $user,
        Brand $brand,
        Draft $draft,
        ContextSnapshot $snapshot,
        string $content,
    ): GenerationRun {
        return GenerationRun::create([
            'brand_id' => $brand->getKey(),
            'draft_id' => $draft->getKey(),
            'context_snapshot_id' => $snapshot->getKey(),
            'user_id' => $user->getKey(),
            'provider' => 'fake',
            'model' => 'fake-mini',
            'operation' => 'draft_content',
            'status' => 'succeeded',
            'generated_content' => $content,
            'evaluation_status' => 'requires_review',
            'evaluation_violations' => [[
                'code' => 'PRICE_REQUIRES_CONFIRMATION',
                'message' => 'Precio sin confirmar.',
            ]],
            'evaluation_warnings' => [],
            'evaluated_at' => now(),
            'grounding_status' => 'requires_review',
            'grounding_results' => [[
                'claim' => ['text' => 'Afirmación no respaldada'],
                'status' => 'UNKNOWN',
                'evidence_excerpt' => null,
            ]],
        ]);
    }
}
