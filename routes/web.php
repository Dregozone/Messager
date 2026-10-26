<?php

use App\Livewire\Chat;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Chat::class)->name('chat');
