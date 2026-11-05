<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/** Local text-to-speech: Kokoro-82M through scripts/kokoro.mjs */
class Kokoro
{
    /**
     * Speak $text into the WAV file at $path.
     *
     * @return float The length of the recording in seconds
     */
    public function speak(string $text, string $voice, float $speed, string $path): float
    {
        $text = self::speakable($text);

        if ($text === '') {
            throw new RuntimeException('Nothing to say');
        }

        @mkdir(dirname($path), 0755, true);

        $result = Process::timeout(config('messager.kokoro.timeout'))
            ->path(base_path())
            ->input($text)
            ->run([
                config('messager.kokoro.node'),
                base_path('scripts/kokoro.mjs'),
                config('messager.kokoro.dir'),
                $voice,
                (string) $speed,
                $path,
                (string) config('messager.kokoro.threads'),
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('Kokoro failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        $lines = array_filter(explode("\n", trim($result->output())));
        $seconds = json_decode((string) end($lines), true)['seconds'] ?? null;

        if (! is_numeric($seconds) || ! is_file($path)) {
            throw new RuntimeException('Kokoro made no recording: '.trim($result->output()));
        }

        return (float) $seconds;
    }

    /** What can be said out loud: no emojis, symbols or stage directions */
    public static function speakable(string $text): string
    {
        $text = preg_replace('/\*[^*]*\*/u', ' ', $text); // *laughs*
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2B00}-\x{2BFF}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }
}
