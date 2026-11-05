# Messager

A local instant-messaging simulator: pick a contact, chat, and they reply in their own personality and texting style. They send voice notes, offer photos, and message you first when you've gone quiet. Everything runs on this PC.

| Piece | Used for |
| --- | --- |
| Ollama, `qwen3.5:4b` | What contacts say (JSON replies, thinking off) |
| Kokoro-82M (kokoro-js) | Voice notes, one voice per contact |
| ComfyUI, SDXL Turbo | Photos (only after you approve them) and avatars |
| Laravel queue + scheduler | Replies, voice notes, photos, and contacts reaching out |

## Running it

Herd serves http://messager.test. In this folder, run:

```
composer dev
```

That starts the chat queue worker, the media queue worker (photos), the scheduler (`contacts:tick` every minute) and Vite.

For photos and avatars, ComfyUI must be reachable at `COMFYUI_URL` (default `http://127.0.0.1:8188`). `php artisan comfyui:serve` runs the Comfy Desktop install headless on that port.

## Contacts

Contacts live in `config/contacts.php`. Each entry holds a persona, a texting style, a Kokoro voice, a colour, avatar and photo prompts, and chattiness: active hours, weekdays only, conversations a day, follow-up delay, maximum unanswered messages, minimum gap between conversations, reply speed, voice-note chance and typing speed. After adding or removing a contact, run `php artisan contacts:sync`.

## Commands

- `php artisan contacts:nudge maisie`: make someone message you now
- `php artisan contacts:avatars [slug] [--force]`: render avatars with ComfyUI
- `php artisan contacts:tick`: one round of "should anyone message?" (the scheduler runs this every minute)
- `php artisan comfyui:serve`: run ComfyUI headless for this app

Settings are in `config/messager.php` (`.env`: `MESSAGER_ME`, `OLLAMA_MODEL`, `KOKORO_DIR`, `COMFYUI_URL`, ...).
