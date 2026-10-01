<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Models\StrategyRun;
use App\Services\Knowledge\ContextBuilder;
use App\Services\Strategy\ContentSuggestion;
use App\Services\Strategy\StrategyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class StrategyController extends Controller
{
    public function index(Request $request, Brand $brand): View
    {
        $brand = $this->ownedBrand($request, $brand);
        $strategyToken = Str::random(40);
        $request->session()->put('strategy-tokens.'.$strategyToken, [
            'brand_id' => (string) $brand->getKey(),
            'user_id' => (string) $request->user()->getKey(),
        ]);
        $runs = StrategyRun::query()
            ->whereBelongsTo($brand)
            ->whereBelongsTo($request->user())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('strategy.index', compact('brand', 'runs', 'strategyToken'));
    }

    public function store(Request $request, Brand $brand, StrategyService $service): RedirectResponse
    {
        $brand = $this->ownedBrand($request, $brand);
        $validated = $request->validate([
            'strategy_token' => ['required', 'string', 'size:40'],
        ]);
        $token = $request->session()->pull('strategy-tokens.'.$validated['strategy_token']);

        abort_unless(
            is_array($token)
            && ($token['brand_id'] ?? null) === (string) $brand->getKey()
            && ($token['user_id'] ?? null) === (string) $request->user()->getKey(),
            422,
        );

        $run = $service->generate($brand, $request->user());

        return to_route('marcas.estrategia.index', $brand)
            ->with(
                $run->status === 'succeeded' ? 'status' : 'error',
                $run->status === 'succeeded'
                    ? 'Se generaron tres sugerencias estratégicas.'
                    : 'No se pudieron generar sugerencias.',
            );
    }

    public function createDraft(
        Request $request,
        Brand $brand,
        StrategyRun $strategyRun,
        int $suggestion,
        ContextBuilder $contextBuilder,
    ): RedirectResponse {
        $brand = $this->ownedBrand($request, $brand);
        $strategyRun = StrategyRun::query()
            ->whereBelongsTo($brand)
            ->whereBelongsTo($request->user())
            ->where('status', 'succeeded')
            ->whereKey($strategyRun->getKey())
            ->firstOrFail();
        $suggestionData = $strategyRun->suggestions[$suggestion] ?? null;
        abort_unless(is_array($suggestionData), 422);

        try {
            $contentSuggestion = ContentSuggestion::fromArray($suggestionData);
        } catch (InvalidArgumentException) {
            abort(422, 'La sugerencia almacenada no es válida.');
        }

        $package = $contextBuilder->build($brand, $contentSuggestion->generationQuery);
        $draft = DB::transaction(function () use ($brand, $contentSuggestion, $package, $request): Draft {
            $draft = new Draft([
                'title' => 'Borrador: '.Str::limit($contentSuggestion->topic, 80, '...'),
                'content' => '',
                'status' => Draft::STATUS_DRAFT,
            ]);
            $draft->brand()->associate($brand);
            $draft->user()->associate($request->user());
            $draft->save();

            ContextSnapshot::fromPackage($draft, $request->user(), $package)->save();

            return $draft;
        });

        return to_route('marcas.borradores.edit', [$brand, $draft])
            ->with('status', 'Se creó un borrador desde la sugerencia. Revisá el contexto antes de generar.');
    }

    private function ownedBrand(Request $request, Brand $brand): Brand
    {
        return $request->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}
