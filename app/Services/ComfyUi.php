<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Image generation through a local ComfyUI's API, with SDXL Turbo by default */
class ComfyUi
{
    public function __construct(private Ollama $ollama) {}

    public function isRunning(): bool
    {
        try {
            return Http::timeout(3)->get($this->url('/system_stats'))->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Render $prompt and return the PNG's bytes.
     *
     * @param  callable(): void|null  $sleep  Waits between polls (tests skip it)
     */
    public function render(string $prompt, ?int $seed = null, ?callable $sleep = null): string
    {
        if (! $this->isRunning()) {
            throw new RuntimeException('ComfyUI is not running at '.config('messager.comfyui.url').'. Open ComfyUI Desktop and try again.');
        }

        // Give the GPU over to ComfyUI; the chat model loads again on its next reply
        $this->ollama->unload();

        $id = Http::timeout(30)->post($this->url('/prompt'), [
            'prompt' => $this->workflow($prompt, $seed ?? random_int(1, PHP_INT_MAX >> 1)),
            'client_id' => (string) Str::uuid(),
        ])->throw()->json('prompt_id');

        if (! $id) {
            throw new RuntimeException('ComfyUI did not queue the image');
        }

        $sleep ??= fn () => usleep(750_000);
        $deadline = microtime(true) + config('messager.comfyui.timeout');

        while (microtime(true) < $deadline) {
            $entry = Http::timeout(15)->get($this->url('/history/'.$id))->json($id);

            if (($entry['status']['status_str'] ?? null) === 'error') {
                throw new RuntimeException('ComfyUI failed: '.json_encode($entry['status']['messages'] ?? []));
            }

            $image = $entry['outputs']['save']['images'][0] ?? null;

            if ($image) {
                $bytes = Http::timeout(30)->get($this->url('/view'), [
                    'filename' => $image['filename'],
                    'subfolder' => $image['subfolder'] ?? '',
                    'type' => $image['type'] ?? 'output',
                ])->throw()->body();

                // Hand the GPU back for the chat model
                Http::timeout(10)->post($this->url('/free'), ['unload_models' => true, 'free_memory' => true]);

                return $bytes;
            }

            $sleep();
        }

        throw new RuntimeException('ComfyUI took too long');
    }

    /** An SDXL Turbo text-to-image graph in ComfyUI's API format */
    public function workflow(string $prompt, int $seed): array
    {
        $size = config('messager.comfyui.size');

        return [
            'ckpt' => ['class_type' => 'CheckpointLoaderSimple', 'inputs' => ['ckpt_name' => config('messager.comfyui.checkpoint')]],
            'latent' => ['class_type' => 'EmptyLatentImage', 'inputs' => ['width' => $size, 'height' => $size, 'batch_size' => 1]],
            'positive' => ['class_type' => 'CLIPTextEncode', 'inputs' => ['text' => $prompt, 'clip' => ['ckpt', 1]]],
            'negative' => ['class_type' => 'CLIPTextEncode', 'inputs' => ['text' => 'text, watermark, deformed, blurry, lowres', 'clip' => ['ckpt', 1]]],
            'sample' => ['class_type' => 'KSampler', 'inputs' => [
                'seed' => $seed,
                'steps' => config('messager.comfyui.steps'),
                'cfg' => 1.0,
                'sampler_name' => 'euler_ancestral',
                'scheduler' => 'normal',
                'denoise' => 1.0,
                'model' => ['ckpt', 0],
                'positive' => ['positive', 0],
                'negative' => ['negative', 0],
                'latent_image' => ['latent', 0],
            ]],
            'decode' => ['class_type' => 'VAEDecode', 'inputs' => ['samples' => ['sample', 0], 'vae' => ['ckpt', 2]]],
            'save' => ['class_type' => 'SaveImage', 'inputs' => ['filename_prefix' => 'messager/photo', 'images' => ['decode', 0]]],
        ];
    }

    private function url(string $path): string
    {
        return rtrim(config('messager.comfyui.url'), '/').$path;
    }
}
