<?php

use App\Jobs\ReachOut;
use App\Jobs\ReplyToMe;
use App\Models\Contact;
use App\Services\Chattiness;
use App\Services\ComfyUi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('contacts:sync', function () {
    $slugs = array_keys(config('contacts'));

    foreach (config('contacts') as $slug => $persona) {
        Contact::updateOrCreate(['slug' => $slug], ['name' => $persona['name']]);
    }

    $removed = Contact::whereNotIn('slug', $slugs)->delete();

    $this->info(count($slugs).' contacts'.($removed ? ", {$removed} removed" : ''));
})->purpose('Bring the contacts in line with config/contacts.php');

Artisan::command('contacts:tick', function (Chattiness $chattiness) {
    foreach (Contact::all() as $contact) {
        $decision = $chattiness->decide($contact);

        if ($decision === 'reply') {
            $lastMine = $contact->messages()->where('from_me', true)->max('id');
            ReplyToMe::dispatch($contact->id, $lastMine);
        } elseif ($decision === 'reach-out') {
            ReachOut::dispatch($contact->id);
        }

        if ($decision) {
            $contact->update(['last_considered_at' => now()]);
            $this->line("{$contact->name}: {$decision}");
        }
    }
})->purpose('Let each contact decide whether to message you (run every minute by the scheduler)');

Artisan::command('contacts:nudge {contact : The contact\'s slug, e.g. maisie} {--voice : Make it a voice note} {--photo : Offer a photo}', function (string $contact) {
    $found = Contact::where('slug', $contact)->first();

    if (! $found) {
        return $this->error("No contact '{$contact}'. Try: ".Contact::pluck('slug')->implode(', '));
    }

    ReachOut::dispatch($found->id, force: true, voice: $this->option('voice') ?: null, photo: (bool) $this->option('photo'));
    $this->info("{$found->name} will message you shortly (the queue worker needs to be running).");
})->purpose('Make a contact message you now');

Artisan::command('contacts:avatars {contact? : Only this contact\'s slug} {--force : Replace avatars that already exist}', function (ComfyUi $comfy) {
    if (! $comfy->isRunning()) {
        return $this->error('ComfyUI is not running at '.config('messager.comfyui.url').'. Open ComfyUI Desktop first.');
    }

    $contacts = Contact::when($this->argument('contact'), fn ($q, $slug) => $q->where('slug', $slug))->get();

    foreach ($contacts as $contact) {
        if ($contact->avatar_path && ! $this->option('force')) {
            $this->line("{$contact->name}: already has one (use --force to replace it)");

            continue;
        }

        $this->line("{$contact->name}: rendering…");
        $png = $comfy->render($contact->persona('avatar').', headshot, centered face, looking at camera, sharp focus, photorealistic', crc32($contact->slug));
        $path = "avatars/{$contact->slug}.png";
        Storage::disk('public')->put($path, $png);
        $contact->update(['avatar_path' => $path]);
    }

    $this->info('Done');
})->purpose('Generate contact avatars with ComfyUI');

Artisan::command('comfyui:serve', function () {
    $install = rtrim(config('messager.comfyui.install'), '/\\');
    $port = parse_url(config('messager.comfyui.url'), PHP_URL_PORT) ?: 8188;

    $command = [
        $install.'/standalone-env/python.exe', $install.'/ComfyUI/main.py',
        '--listen', '127.0.0.1', '--port', (string) $port, '--disable-auto-launch',
    ];

    if (is_file(config('messager.comfyui.model_paths'))) {
        array_push($command, '--extra-model-paths-config', config('messager.comfyui.model_paths'));
    }

    $this->info("Starting ComfyUI on port {$port}…");

    return Process::forever()->path($install.'/ComfyUI')
        ->run($command, fn ($type, $out) => $this->output->write($out))->exitCode();
})->purpose('Run ComfyUI headless for photos and avatars');

Schedule::command('contacts:tick')->everyMinute()->withoutOverlapping();
