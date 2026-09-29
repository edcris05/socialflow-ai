<?php

namespace App\Services\Generation;

use App\Contracts\GenerationEvaluatorInterface;
use App\Contracts\GenerationProviderInterface;
use App\Models\Draft;
use App\Models\GenerationRun;
use App\Models\User;
use App\Services\Prompting\PromptComposer;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerationService
{
    public function __construct(
        private GenerationProviderInterface $provider,
        private PromptComposer $composer,
        private GenerationEvaluatorInterface $evaluator,
    ) {}

    public function generate(Draft $draft, User $user, bool $regenerate = false): GenerationRun
    {
        $run = DB::transaction(function () use ($draft, $user, $regenerate): GenerationRun {
            $draft = Draft::query()->lockForUpdate()->findOrFail($draft->getKey());
            $snapshot = $draft->contextSnapshot()->firstOrFail();
            $latest = $draft->generationRuns()->where('operation', 'draft_content')->latest()->first();
            if ($latest && in_array($latest->status, ['pending', 'running'], true)) {
                return $latest;
            }
            if ($latest && $latest->status === 'succeeded' && ! $regenerate) {
                return $latest;
            }

            return GenerationRun::create([
                'brand_id' => $draft->brand_id,
                'draft_id' => $draft->getKey(),
                'context_snapshot_id' => $snapshot->getKey(),
                'user_id' => $user->getKey(),
                'provider' => 'openai',
                'model' => (string) config('services.openai.model'),
                'operation' => 'draft_content',
                'status' => 'pending',
            ]);
        });
        if ($run->status !== 'pending') {
            return $run;
        }

        $run->update(['status' => 'running']);

        try {
            $result = $this->provider->generate($this->composer->compose($run->contextSnapshot));
            if (trim($result->content) === '') {
                throw new \RuntimeException('El proveedor devolvió contenido inválido.');
            }

            DB::transaction(function () use ($run, $result): void {
                $lockedRun = GenerationRun::query()->lockForUpdate()->findOrFail($run->getKey());
                $generatedContent = $result->content;
                $evaluation = $this->evaluator->evaluate($generatedContent, $lockedRun->contextSnapshot);

                $lockedRun->draft()->update(['content' => $generatedContent]);
                $lockedRun->update([
                    'status' => 'succeeded',
                    'generated_content' => $generatedContent,
                    'provider' => $result->provider,
                    'model' => $result->model,
                    'input_tokens' => $result->inputTokens,
                    'cached_input_tokens' => $result->cachedInputTokens,
                    'output_tokens' => $result->outputTokens,
                    'estimated_cost_usd' => $this->estimatedCost($result),
                    'provider_request_id' => $result->providerRequestId,
                    'finish_reason' => $result->finishReason,
                    'error' => null,
                    'evaluation_status' => $evaluation->status,
                    'evaluation_violations' => $evaluation->violations,
                    'evaluation_warnings' => $evaluation->warnings,
                    'evaluated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $failure = ['status' => 'failed', 'error' => $this->safeError($exception)];
            if ($exception instanceof InvalidProviderResponseException) {
                $failure += [
                    'input_tokens' => $exception->inputTokens,
                    'cached_input_tokens' => $exception->cachedInputTokens,
                    'output_tokens' => $exception->outputTokens,
                    'estimated_cost_usd' => $this->estimatedCost(new GenerationResult('', 'openai', $run->model, $exception->inputTokens, $exception->cachedInputTokens, $exception->outputTokens, $exception->finishReason, $exception->providerRequestId)),
                    'provider_request_id' => $exception->providerRequestId,
                    'finish_reason' => $exception->finishReason,
                ];
            }
            $run->update($failure);
        }

        return $run->fresh();
    }

    private function estimatedCost(GenerationResult $result): ?float
    {
        if ($result->inputTokens === null || $result->outputTokens === null) {
            return null;
        }
        $prices = config('services.openai.pricing');
        if (! is_array($prices) || ! is_numeric($prices['input'] ?? null) || ! is_numeric($prices['cached_input'] ?? null) || ! is_numeric($prices['output'] ?? null)) {
            return null;
        }
        $cached = $result->cachedInputTokens ?? 0;
        if ($cached > $result->inputTokens) {
            return null;
        }

        return (($result->inputTokens - $cached) * (float) $prices['input'] + $cached * (float) $prices['cached_input'] + $result->outputTokens * (float) $prices['output']) / 1000000;
    }

    private function safeError(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, 500);
    }
}
