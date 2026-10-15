<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::livewire('session-records', 'ipdr::session-records')->name('session-records.index');
});
