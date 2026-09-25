<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\KnowledgeEntryController;
use App\Http\Controllers\ContextController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->name('login.store');
    Route::get('/registrarse', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registrarse', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/salir', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::resourceVerbs([
    'create' => 'crear',
    'edit' => 'editar',
]);

Route::middleware('auth')->group(function () {
    Route::resource('marcas', BrandController::class)
        ->except('destroy')
        ->parameters(['marcas' => 'brand']);

    Route::prefix('marcas/{brand}')->name('marcas.')->group(function () {
        Route::get('contexto', [ContextController::class, 'create'])->name('contexto.create');
        Route::post('contexto', [ContextController::class, 'preview'])->name('contexto.preview');
        Route::post('contexto/borradores', [ContextController::class, 'storeDraft'])->name('contexto.borradores.store');
        Route::get('conocimiento', [KnowledgeEntryController::class, 'index'])->name('conocimiento.index');
        Route::get('conocimiento/crear', [KnowledgeEntryController::class, 'create'])->name('conocimiento.create');
        Route::post('conocimiento', [KnowledgeEntryController::class, 'store'])->name('conocimiento.store');
        Route::get('conocimiento/{knowledgeEntry}/editar', [KnowledgeEntryController::class, 'edit'])->name('conocimiento.edit');
        Route::put('conocimiento/{knowledgeEntry}', [KnowledgeEntryController::class, 'update'])->name('conocimiento.update');
        Route::patch('conocimiento/{knowledgeEntry}/verificar', [KnowledgeEntryController::class, 'verify'])->name('conocimiento.verify');
        Route::get('conocimiento/{knowledgeEntry}', [KnowledgeEntryController::class, 'show'])->name('conocimiento.show');

        Route::get('borradores', [DraftController::class, 'index'])->name('borradores.index');
        Route::get('borradores/crear', [DraftController::class, 'create'])->name('borradores.create');
        Route::post('borradores', [DraftController::class, 'store'])->name('borradores.store');
        Route::get('borradores/{draft}/editar', [DraftController::class, 'edit'])->name('borradores.edit');
        Route::put('borradores/{draft}', [DraftController::class, 'update'])->name('borradores.update');
            Route::get('borradores/{draft}/prompt-preview', [\App\Http\Controllers\PromptController::class, 'preview'])->name('borradores.prompt.preview');
    });
});
