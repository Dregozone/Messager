<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\ComfyUi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Generates a photo I've approved, with ComfyUI. Runs on the media queue so chat replies don't wait on it. */
class RenderPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 420;

    public function __construct(public int $messageId)
    {
        $this->onQueue('media');
    }

    public function handle(ComfyUi $comfy): void
    {
        $message = Message::with('contact')->find($this->messageId);

        if (! $message || $message->photo_status !== 'queued') {
            return;
        }

        $message->update(['photo_status' => 'generating']);

        $png = $comfy->render((string) $message->photo_prompt);
        $path = 'photos/'.$message->contact->slug.'/'.$message->id.'.png';
        Storage::disk('public')->put($path, $png);

        $message->update([
            'photo_status' => 'ready',
            'photo_path' => $path,
            'sent_at' => now(),
            'seen_at' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Message::whereKey($this->messageId)->update(['photo_status' => 'failed']);
    }
}
