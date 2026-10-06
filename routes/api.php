<?php

use App\Http\Controllers\Api\DemandeController;
use Illuminate\Support\Facades\Route;

Route::get('/demandes/statistiques', [DemandeController::class, 'statistiques']);
Route::get('/demandes', [DemandeController::class, 'index']);
Route::post('/demandes', [DemandeController::class, 'store']);
Route::get('/demandes/{demande}', [DemandeController::class, 'show'])->whereNumber('demande');
Route::patch('/demandes/{demande}/statut', [DemandeController::class, 'updateStatut'])->whereNumber('demande');
