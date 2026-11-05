<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** The local chat model, through Ollama's /api/chat */
class Ollama
{
    /**
     * Ask for an answer shaped by a JSON schema.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function json(array $messages, array $schema, float $temperature = 0.9): array
    {
        $response = Http::timeout(config('messager.ollama.timeout'))
            ->post(config('messager.ollama.url').'/api/chat', [
                'model' => config('messager.ollama.model'),
                'messages' => $messages,
                'format' => $schema,
                'stream' => false,
                'think' => false, // qwen3.5 thinks by default: slow, and nothing here needs it
                'keep_alive' => '15m',
                'options' => ['temperature' => $temperature, 'num_ctx' => 8192],
            ])
            ->throw();

        $content = (string) $response->json('message.content');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            // Small models sometimes wrap the JSON in prose or a code fence
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The model did not answer in JSON: '.mb_substr($content, 0, 200));
        }

        return $decoded;
    }

    /** Free the GPU for ComfyUI */
    public function unload(): void
    {
        try {
            Http::timeout(15)->post(config('messager.ollama.url').'/api/generate', [
                'model' => config('messager.ollama.model'),
                'keep_alive' => 0,
            ]);
        } catch (\Throwable) {
            // Not fatal: ComfyUI can still offload if there's not enough room
        }
    }
}
