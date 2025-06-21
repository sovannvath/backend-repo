<?php
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;
// routes/web.php
Route::get('/roles/{role}', [RoleController::class, 'show']);

Route::get('/', function () {
    return view('welcome');
});