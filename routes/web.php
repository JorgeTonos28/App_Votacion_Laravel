<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JuryController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/e/{code}', [HomeController::class, 'index'])->name('event.code');
Route::post('/evento/acceder', [HomeController::class, 'access'])->middleware('throttle:8,10')->name('event.access');
Route::get('/error', [HomeController::class, 'error'])->name('error');

Route::get('/evento', [PublicController::class, 'lobby'])->name('public.lobby');
Route::get('/evento/votar', [PublicController::class, 'ballot'])->name('public.ballot');
Route::post('/evento/votar', [PublicController::class, 'submit'])->middleware('throttle:10,1')->name('public.submit');
Route::get('/evento/confirmacion', [PublicController::class, 'confirmed'])->name('public.confirmed');
Route::get('/api/public/state', [PublicController::class, 'state'])->name('public.state');

Route::get('/jurado', [JuryController::class, 'access'])->name('jury.access');
Route::post('/jurado/validar', [JuryController::class, 'validateCode'])->middleware('throttle:5,15')->name('jury.validate');
Route::post('/jurado/confirmar', [JuryController::class, 'confirm'])->name('jury.confirm');
Route::get('/jurado/panel', [JuryController::class, 'dashboard'])->name('jury.dashboard');
Route::get('/jurado/evaluar', [JuryController::class, 'ballot'])->name('jury.ballot');
Route::post('/jurado/evaluar', [JuryController::class, 'submit'])->middleware('throttle:10,1')->name('jury.submit');
Route::post('/jurado/salir', [JuryController::class, 'logout'])->name('jury.logout');
Route::get('/api/jury/state', [JuryController::class, 'state'])->name('jury.state');

Route::get('/proyeccion/{eventCode}', [ProjectionController::class, 'live'])->name('projection.live');
Route::get('/resultados/{eventCode}', [ProjectionController::class, 'ranking'])->name('projection.ranking');
Route::get('/api/projection/{eventCode}', [ProjectionController::class, 'state'])->name('projection.state');
Route::get('/qr/evento/{eventCode}', [AdminController::class, 'eventQr'])->name('event.qr');

Route::get('/admin/acceso', [AccountController::class, 'login'])->name('admin.login');
Route::post('/admin/acceso', [AccountController::class, 'authenticate'])->middleware('throttle:8,15')->name('admin.authenticate');
Route::get('/admin/verificar', [AccountController::class, 'verify'])->name('admin.verify');
Route::post('/admin/verificar', [AccountController::class, 'verifyCode'])->middleware('throttle:8,15')->name('admin.verify.submit');
Route::get('/admin/sin-permiso', [AccountController::class, 'denied'])->name('admin.denied');

Route::middleware(['auth', 'role:Administrator,Operator'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/jurados', [AdminController::class, 'allJurors'])->name('admin.jurors.all');
    Route::get('/admin/resultados', [AdminController::class, 'allResults'])->name('admin.results.all');
    Route::post('/admin/salir', [AccountController::class, 'logout'])->name('admin.logout');
    Route::get('/admin/eventos/{event}/participantes', [AdminController::class, 'participants'])->name('admin.participants');
    Route::get('/admin/eventos/{event}/jurados', [AdminController::class, 'jurors'])->name('admin.jurors');
    Route::get('/admin/eventos/{event}/votantes', [AdminController::class, 'voters'])->name('admin.voters');
    Route::get('/admin/eventos/{event}/votacion', [AdminController::class, 'voting'])->name('admin.voting');
    Route::get('/admin/eventos/{event}/control', [AdminController::class, 'live'])->name('admin.live');
    Route::get('/api/admin/eventos/{event}/estado', [AdminController::class, 'liveState'])->name('admin.live.state');
    Route::post('/admin/eventos/{event}/control/{operation}', [AdminController::class, 'control'])->name('admin.control');
    Route::get('/admin/eventos/{event}/resultados', [AdminController::class, 'eventResults'])->name('admin.event.results');
    Route::post('/admin/eventos/{event}/resultados/recalcular', [AdminController::class, 'recalculate'])->name('admin.results.recalculate');
    Route::post('/admin/eventos/{event}/resultados/publicar', [AdminController::class, 'publish'])->name('admin.results.publish');
    Route::post('/admin/eventos/{event}/resultados/ocultar', [AdminController::class, 'unpublish'])->name('admin.results.unpublish');
    Route::get('/admin/eventos/{event}/reportes', [AdminController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/eventos/{event}/exportar.csv', [AdminController::class, 'export'])->name('admin.export');
});

Route::middleware(['auth', 'role:Administrator'])->group(function () {
    Route::get('/admin/configuracion', [AdminController::class, 'settings'])->name('admin.settings');
    Route::get('/admin/seguridad/mfa', [AccountController::class, 'mfa'])->name('admin.mfa');
    Route::post('/admin/seguridad/mfa', [AccountController::class, 'enableMfa'])->name('admin.mfa.enable');
    Route::get('/admin/eventos/nuevo', [AdminController::class, 'create'])->name('admin.events.create');
    Route::post('/admin/eventos/nuevo', [AdminController::class, 'store'])->name('admin.events.store');
    Route::get('/admin/eventos/{event}/editar', [AdminController::class, 'edit'])->name('admin.events.edit');
    Route::post('/admin/eventos/{event}/editar', [AdminController::class, 'update'])->name('admin.events.update');
    Route::post('/admin/eventos/{event}/clonar', [AdminController::class, 'cloneEvent'])->name('admin.events.clone');
    Route::post('/admin/eventos/{event}/archivar', [AdminController::class, 'archive'])->name('admin.events.archive');
    Route::post('/admin/participantes/agregar', [AdminController::class, 'addParticipant'])->name('admin.participants.add');
    Route::post('/admin/eventos/{event}/participantes/importar', [AdminController::class, 'importParticipants'])->name('admin.participants.import');
    Route::get('/admin/participantes/{participant}/editar', [AdminController::class, 'editParticipant'])->name('admin.participants.edit');
    Route::post('/admin/participantes/{participant}/editar', [AdminController::class, 'updateParticipant'])->name('admin.participants.update');
    Route::post('/admin/participantes/{participant}/estado', [AdminController::class, 'changeParticipantStatus'])->name('admin.participants.status');
    Route::post('/admin/participantes/{participant}/eliminar', [AdminController::class, 'deleteParticipant'])->name('admin.participants.delete');
    Route::get('/api/admin/jurados/buscar', [AdminController::class, 'searchJurors'])->name('admin.jurors.search');
    Route::post('/admin/jurados/agregar', [AdminController::class, 'addJuror'])->name('admin.jurors.add');
    Route::post('/admin/jurados/{juror}/regenerar', [AdminController::class, 'regenerateJuror'])->name('admin.jurors.regenerate');
    Route::post('/admin/jurados/{juror}/revocar', [AdminController::class, 'revokeJuror'])->name('admin.jurors.revoke');
    Route::post('/admin/eventos/{event}/votantes/generar-codigos', [AdminController::class, 'generateVoterCodes'])->name('admin.voters.generate');
    Route::post('/admin/eventos/{event}/votantes/importar', [AdminController::class, 'importVoters'])->name('admin.voters.import');
    Route::post('/admin/votantes/{voter}/estado', [AdminController::class, 'changeVoterStatus'])->name('admin.voters.status');
    Route::post('/admin/votacion/pesos', [AdminController::class, 'saveWeights'])->name('admin.voting.weights');
    Route::post('/admin/eventos/{event}/votacion/rubrica', [AdminController::class, 'saveRubric'])->name('admin.voting.rubric');
    Route::post('/admin/votos/{vote}/invalidar', [AdminController::class, 'invalidateVote'])->name('admin.votes.invalidate');
});

Route::get('/health', fn () => response()->json(['status' => 'Healthy']));
