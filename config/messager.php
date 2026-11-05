<?php

return [

    // What the contacts call you
    'me' => env('MESSAGER_ME', 'Anders'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3.5:4b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
        // How many earlier messages each reply sees
        'history' => (int) env('OLLAMA_HISTORY', 40),
    ],

    'kokoro' => [
        // A folder holding kokoro-js in node_modules and the Kokoro-82M model in models/ (life-os already has one)
        'dir' => env('KOKORO_DIR', 'C:\Users\aclea\Herd\life-os\helper\tools\kokoro'),
        'node' => env('NODE_BINARY', 'node'),
        'threads' => (int) env('KOKORO_THREADS', 6),
        'timeout' => (int) env('KOKORO_TIMEOUT', 180),
    ],

    'comfyui' => [
        'url' => env('COMFYUI_URL', 'http://127.0.0.1:8188'),
        // For `php artisan comfyui:serve`: runs Comfy Desktop's own ComfyUI headless on the port above
        // (Comfy Desktop itself puts ComfyUI behind a login on a random port, so other apps can't use it)
        'install' => env('COMFYUI_INSTALL', 'D:/Comfy-Desktop/ComfyUI-Installs/ComfyUI'),
        'model_paths' => env('COMFYUI_MODEL_PATHS', 'C:/Users/aclea/AppData/Roaming/Comfy Desktop/shared_model_paths.yaml'),
        'checkpoint' => env('COMFYUI_CHECKPOINT', 'sd_xl_turbo_1.0_fp16.safetensors'),
        'steps' => (int) env('COMFYUI_STEPS', 4),
        'size' => (int) env('COMFYUI_SIZE', 512),
        'timeout' => (int) env('COMFYUI_TIMEOUT', 300),
    ],

    // Seconds before a contact starts on a reply, so a burst of your messages gets one answer
    'reply_debounce' => [4, 10],

];
