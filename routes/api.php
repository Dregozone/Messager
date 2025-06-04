<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/addMessage', function (Request $request) {
    $message = $request->input('message');
    $user = $request->input('user', 'User'); // Default to 'User' if not provided

    // Here you would typically save the message to a database or session
    // For demonstration, we'll just return it as a response
    return response()->json([
        'message' => $message,
        'user' => $user,
    ]);
})->name('addMessage');
