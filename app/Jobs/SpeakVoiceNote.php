<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Kokoro;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Records a contact's voice note with their Kokoro voice */
class SpeakVoiceNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(public int $messageId) {}

    public function handle(Kokoro $kokoro): void
    {
        $message = Message::with('contact')->find($this->messageId);

        if (! $message || $message->audio_path) {
            return;
        }

        $contact = $message->contact;
        $path = 'voice/'.$contact->slug.'/'.$message->id.'.wav';

        $seconds = $kokoro->speak(
            (string) $message->body,
            $contact->persona('voice.id', 'af_heart'),
            (float) $contact->persona('voice.speed', 1.0),
            Storage::disk('public')->path($path),
        );

        // Recording takes as long as the note itself
        $arrives = $message->composing_at->copy()->addMilliseconds((int) ($seconds * 1000));

        $message->update([
            'audio_path' => $path,
            'audio_seconds' => $seconds,
            'sent_at' => $arrives->max(now()->addSecond()),
        ]);
    }

    /** No voice? They type it instead, so the conversation carries on */
    public function failed(?Throwable $e): void
    {
        Message::whereKey($this->messageId)->whereNull('audio_path')->update([
            'kind' => 'text',
            'sent_at' => now(),
        ]);

        report($e);
    }
}
