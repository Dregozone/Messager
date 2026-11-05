<?php

use App\Jobs\ReachOut;
use App\Jobs\RenderPhoto;
use App\Jobs\ReplyToMe;
use App\Jobs\SpeakVoiceNote;
use App\Livewire\Messenger;
use App\Models\Contact;
use App\Models\Message;
use App\Services\Chattiness;
use App\Services\ContactBrain;
use App\Services\Kokoro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('contacts:sync');
    $this->maisie = Contact::where('slug', 'maisie')->first();
});

/** Make the local model answer with $answer */
function modelSays(array $answer): void
{
    Http::fake(['*/api/chat' => Http::response(['message' => ['content' => json_encode($answer)]])]);
}

function said(array $messages = ['hiii'], array $extra = []): array
{
    return $extra + ['send_photo' => false, 'photo_description' => '', 'photo_caption' => '', 'messages' => $messages];
}

function mine(Contact $contact, string $text, ?Carbon $at = null): Message
{
    return $contact->messages()->create(['from_me' => true, 'body' => $text, 'sent_at' => $at ?? now(), 'created_at' => $at ?? now()]);
}

function theirs(Contact $contact, string $text, ?Carbon $at = null): Message
{
    return $contact->messages()->create(['from_me' => false, 'body' => $text, 'composing_at' => $at ?? now(), 'sent_at' => $at ?? now()]);
}

it('syncs the contacts from config', function () {
    expect(Contact::pluck('slug')->all())->toEqualCanonicalizing(array_keys(config('contacts')));
});

it('gives every contact a full persona and a real Kokoro voice', function (string $slug) {
    $voices = ['af_heart', 'af_alloy', 'af_aoede', 'af_bella', 'af_jessica', 'af_kore', 'af_nicole', 'af_nova', 'af_river', 'af_sarah', 'af_sky',
        'am_adam', 'am_echo', 'am_eric', 'am_fenrir', 'am_liam', 'am_michael', 'am_onyx', 'am_puck', 'am_santa',
        'bf_emma', 'bf_isabella', 'bf_alice', 'bf_lily', 'bm_george', 'bm_lewis', 'bm_daniel', 'bm_fable'];
    $p = config("contacts.$slug");

    expect($p)->toHaveKeys(['name', 'relationship', 'about', 'style', 'voice', 'colour', 'avatar', 'photos', 'chattiness', 'reply_after', 'voice_notes', 'typing'])
        ->and($voices)->toContain($p['voice']['id'])
        ->and($p['chattiness'])->toHaveKeys(['hours', 'weekdays_only', 'per_day', 'follow_up', 'max_unanswered', 'min_gap']);
})->with(['maisie', 'tom', 'mum', 'jake', 'priya', 'sam']);

it('shows the chat list and opens a chat', function () {
    theirs($this->maisie, 'omg are you free tonight');

    Livewire::test(Messenger::class)
        ->assertSee(['Maisie', 'Tom', 'Mum', 'omg are you free tonight'])
        ->call('open', 'maisie')
        ->assertSet('chat', 'maisie')
        ->assertSee('Message Maisie');

    expect($this->maisie->unreadCount())->toBe(0);
});

it('ignores a chat that does not exist', function () {
    Livewire::test(Messenger::class)->call('open', '../../etc')->assertSet('chat', '');
    $this->get('/?chat=nobody')->assertOk();
});

it('sends my message and asks for a reply after a short pause', function () {
    Queue::fake();

    Livewire::withQueryParams(['chat' => 'maisie'])->test(Messenger::class)->call('send', '  hello you  ');

    $message = $this->maisie->messages()->sole();
    expect($message->body)->toBe('hello you')->and($message->from_me)->toBeTrue();
    Queue::assertPushed(ReplyToMe::class, fn ($job) => $job->afterMessageId === $message->id && $job->delay !== null);
});

it('replies in character with bubbles that are typed out one after another', function () {
    modelSays(said(["Maisie: omg hiii 😭\n\nwait", '[Tue 3 Nov 19:40] how are u']));
    config(['contacts.maisie.voice_notes' => 0]);
    $me = mine($this->maisie, 'hey');

    (new ReplyToMe($this->maisie->id, $me->id))->handle(app(ContactBrain::class));

    $replies = $this->maisie->messages()->where('from_me', false)->orderBy('id')->get();
    expect($replies->pluck('body')->all())->toBe(['omg hiii 😭', 'wait', 'how are u'])
        ->and($replies[1]->sent_at->gt($replies[0]->sent_at))->toBeTrue()
        ->and($me->fresh()->read_at)->not->toBeNull();

    Http::assertSent(fn ($r) => $r['model'] === 'qwen3.5:4b' && $r['think'] === false
        && str_contains($r['messages'][0]['content'], 'You are Maisie')
        && str_contains($r['messages'][1]['content'], 'Anders: hey'));
});

it('waits for me to finish before replying to a burst of messages', function () {
    Http::fake();
    $first = mine($this->maisie, 'hey');
    mine($this->maisie, 'you there?');

    (new ReplyToMe($this->maisie->id, $first->id))->handle(app(ContactBrain::class));

    Http::assertNothingSent();
    expect($this->maisie->messages()->where('from_me', false)->count())->toBe(0);
});

it('records a voice note in their own voice', function () {
    Queue::fake();
    Storage::fake('public');
    modelSays(said(['Hi darling 🌷 hope you are eating properly']));
    $mum = Contact::where('slug', 'mum')->first();
    $me = mine($mum, 'hi mum');

    app(ContactBrain::class)->deliver($mum, app(ContactBrain::class)->reply($mum, voice: true));

    $note = $mum->messages()->where('kind', 'voice')->sole();
    expect($note->sent_at)->toBeNull()->and($mum->activity())->toBe('recording');
    Queue::assertPushed(SpeakVoiceNote::class);

    Process::fake(['*' => Process::result(output: '{"ok":true,"seconds":4.2}')]);
    Storage::disk('public')->put("voice/mum/{$note->id}.wav", 'RIFF'); // What Kokoro would have written
    (new SpeakVoiceNote($note->id))->handle(app(Kokoro::class));

    Process::assertRan(fn ($p) => in_array('bf_isabella', $p->command) && $p->input === 'Hi darling hope you are eating properly');
    expect($note->fresh())->audio_path->toBe("voice/mum/{$note->id}.wav")->audio_seconds->toBe(4.2)->sent_at->not->toBeNull();
});

it('falls back to text when a voice note cannot be recorded', function () {
    $note = $this->maisie->messages()->create(['from_me' => false, 'kind' => 'voice', 'body' => 'hiya', 'composing_at' => now()]);

    (new SpeakVoiceNote($note->id))->failed(new RuntimeException('no node'));

    expect($note->fresh())->kind->toBe('text')->sent_at->not->toBeNull();
});

it('offers a photo when I ask for one, and generates nothing until I approve it', function () {
    Queue::fake();
    modelSays(said(['here u go'], ['photo_description' => 'my cat asleep on the sofa', 'photo_caption' => 'biscuit 💤']));
    config(['contacts.maisie.voice_notes' => 0]);
    $me = mine($this->maisie, 'send me a pic of biscuit!');

    (new ReplyToMe($this->maisie->id, $me->id))->handle(app(ContactBrain::class));

    $photo = $this->maisie->messages()->where('kind', 'photo')->sole();
    expect($photo)->photo_status->toBe('offered')->body->toBe('biscuit 💤')
        ->and($photo->photo_prompt)->toStartWith('my cat asleep on the sofa, ');
    Queue::assertNotPushed(RenderPhoto::class);

    $this->travelTo(now()->addMinute()); // Once they've finished typing
    Livewire::withQueryParams(['chat' => 'maisie'])->test(Messenger::class)
        ->assertSee('Maisie wants to send a photo')
        ->call('approvePhoto', $photo->id, 'a ginger cat asleep on a green sofa');

    expect($photo->fresh())->photo_status->toBe('queued')->photo_prompt->toBe('a ginger cat asleep on a green sofa');
    Queue::assertPushedOn('media', RenderPhoto::class);
});

it('lets me decline a photo', function () {
    Queue::fake();
    $photo = $this->maisie->messages()->create(['from_me' => false, 'kind' => 'photo', 'photo_prompt' => 'x', 'photo_status' => 'offered', 'sent_at' => now()]);

    Livewire::test(Messenger::class)->call('declinePhoto', $photo->id);

    expect($photo->fresh()->photo_status)->toBe('declined');
    Queue::assertNothingPushed();
});

it('renders an approved photo with ComfyUI', function () {
    Storage::fake('public');
    Http::fake([
        '*/system_stats' => Http::response(['system' => []]),
        '*/api/generate' => Http::response([]),
        '*/prompt' => Http::response(['prompt_id' => 'abc']),
        '*/history/abc' => Http::response(['abc' => ['status' => ['status_str' => 'success'], 'outputs' => ['save' => ['images' => [['filename' => 'photo_0001.png', 'subfolder' => 'messager', 'type' => 'output']]]]]]),
        '*/view*' => Http::response('PNGDATA'),
        '*/free' => Http::response([]),
    ]);
    $photo = $this->maisie->messages()->create(['from_me' => false, 'kind' => 'photo', 'photo_prompt' => 'a cat', 'photo_status' => 'queued', 'sent_at' => now()]);

    app()->call([new RenderPhoto($photo->id), 'handle']);

    expect($photo->fresh())->photo_status->toBe('ready')->photo_path->toBe("photos/maisie/{$photo->id}.png");
    Storage::disk('public')->assertExists("photos/maisie/{$photo->id}.png");
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/prompt') && $r['prompt']['positive']['inputs']['text'] === 'a cat'
        && $r['prompt']['ckpt']['inputs']['ckpt_name'] === 'sd_xl_turbo_1.0_fp16.safetensors');
});

it('reaches out only when the model decides to', function (bool $send, int $expected) {
    modelSays(said(['u alive? 👀'], ['send' => $send]));
    config(['contacts.maisie.voice_notes' => 0]);

    (new ReachOut($this->maisie->id))->handle(app(ContactBrain::class));

    expect($this->maisie->messages()->count())->toBe($expected);
})->with([[true, 1], [false, 0]]);

it('does not reach out over my unanswered message', function () {
    Http::fake();
    mine($this->maisie, 'hello?');

    (new ReachOut($this->maisie->id))->handle(app(ContactBrain::class));

    Http::assertNothingSent();
});

describe('chattiness', function () {
    beforeEach(function () {
        config(['contacts.maisie.chattiness' => ['hours' => [9, 22], 'weekdays_only' => false, 'per_day' => 4, 'follow_up' => 60, 'max_unanswered' => 2, 'min_gap' => 30]]);
        $this->always = new Chattiness(fn () => 0.0);
        $this->never = new Chattiness(fn () => 0.99);
    });

    it('sometimes starts a conversation during their hours', function () {
        $noon = Carbon::parse('2026-11-04 12:00');

        expect($this->always->decide($this->maisie, $noon))->toBe('reach-out')
            ->and($this->never->decide($this->maisie, $noon))->toBeNull();
    });

    it('stays quiet outside their hours and at weekends if weekdays only', function () {
        expect($this->always->decide($this->maisie, Carbon::parse('2026-11-04 23:30')))->toBeNull();

        config(['contacts.maisie.chattiness.weekdays_only' => true]);
        expect($this->always->decide($this->maisie, Carbon::parse('2026-11-07 12:00')))->toBeNull();
    });

    it('follows up when I leave them hanging, but only so many times', function () {
        $this->travelTo(Carbon::parse('2026-11-04 12:00'));
        theirs($this->maisie, 'hey', now()->subMinutes(30));
        expect($this->always->decide($this->maisie))->toBeNull(); // Not time to follow up yet

        $this->travelTo(now()->addMinutes(45));
        expect($this->always->decide($this->maisie))->toBe('reach-out');

        theirs($this->maisie, 'helloooo', now()->subMinutes(90));
        $this->travelTo(now()->addHours(3));
        expect($this->always->decide($this->maisie))->toBeNull(); // Two unanswered: they give up
    });

    it('picks up a reply that got lost', function () {
        $this->travelTo(Carbon::parse('2026-11-04 12:00'));
        mine($this->maisie, 'you there?', now()->subMinutes(20));

        expect($this->never->decide($this->maisie))->toBe('reply');
    });

    it('waits while they are already typing', function () {
        $this->maisie->messages()->create(['from_me' => false, 'body' => 'x', 'composing_at' => now(), 'sent_at' => now()->addMinute()]);

        expect($this->always->decide($this->maisie, Carbon::parse('2026-11-04 12:00')))->toBeNull();
    });
});

it('lets the scheduler run the tick and nudge commands', function () {
    Queue::fake();

    $this->artisan('contacts:tick')->assertSuccessful();
    $this->artisan('contacts:nudge', ['contact' => 'tom'])->assertSuccessful();

    Queue::assertPushed(ReachOut::class, fn ($job) => $job->force);
});

it('keeps only what can be spoken', function () {
    expect(Kokoro::speakable('omg 😭✨ *laughs* hiya  there'))->toBe('omg hiya there');
});

it('sends a voice note when I ask for one', function (string $ask) {
    Queue::fake();
    modelSays(said(['ok so basically my whole day was chaos']));
    config(['contacts.maisie.voice_notes' => 0]); // Never by chance
    $me = mine($this->maisie, $ask);

    (new ReplyToMe($this->maisie->id, $me->id))->handle(app(ContactBrain::class));

    expect($this->maisie->messages()->where('kind', 'voice')->count())->toBe(1);
    Queue::assertPushed(SpeakVoiceNote::class);
})->with(['tell me about your day in a voice note', 'send a VN', 'can I hear your voice?', 'voicenote pls']);

it('replies in text when I do not ask for a voice note', function () {
    modelSays(said(['haha same']));
    config(['contacts.maisie.voice_notes' => 0]);
    $me = mine($this->maisie, 'I invoiced the client today');

    (new ReplyToMe($this->maisie->id, $me->id))->handle(app(ContactBrain::class));

    expect($this->maisie->messages()->where('kind', 'voice')->count())->toBe(0);
});

it('can nudge someone into a voice note or a photo for testing', function () {
    Queue::fake();

    $this->artisan('contacts:nudge', ['contact' => 'maisie', '--voice' => true, '--photo' => true])->assertSuccessful();
    $this->artisan('contacts:nudge', ['contact' => 'tom'])->assertSuccessful();

    Queue::assertPushed(ReachOut::class, fn ($job) => $job->voice === true && $job->photo === true);
    Queue::assertPushed(ReachOut::class, fn ($job) => $job->voice === null && $job->photo === false);
});
