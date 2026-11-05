<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // Key of the persona in config/contacts.php
            $table->string('name');
            $table->string('avatar_path')->nullable(); // On the public disk
            $table->timestamp('last_considered_at')->nullable(); // Last time they thought about messaging first
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_me');
            $table->string('kind')->default('text'); // text, voice or photo
            $table->text('body')->nullable(); // The text, a voice note's transcript, or a photo's caption
            $table->string('audio_path')->nullable();
            $table->float('audio_seconds')->nullable();
            $table->text('photo_prompt')->nullable();
            $table->string('photo_status')->nullable(); // offered, queued, generating, ready, declined or failed
            $table->string('photo_path')->nullable();
            $table->timestamp('composing_at')->nullable(); // When they start typing or recording it
            $table->timestamp('sent_at')->nullable(); // When it shows in the chat; null or future while they're still typing it
            $table->timestamp('read_at')->nullable(); // When they read it (my messages)
            $table->timestamp('seen_at')->nullable(); // When I saw it (their messages)
            $table->timestamps();

            $table->index(['contact_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('contacts');
    }
};
