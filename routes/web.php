<?php

use App\Livewire\Messenger;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Messenger::class)->name('messenger');
