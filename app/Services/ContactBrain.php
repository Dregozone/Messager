<?php

namespace App\Services;

use App\Jobs\SpeakVoiceNote;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Decides what a contact says, through the local model, and puts it in the chat */
class ContactBrain
{
    public function __construct(private Ollama $ollama) {}

    /**
     * Their answer to what I've just said.
     *
     * @return array{messages: list<string>, voice: bool, photo: ?string, caption: string}
     */
    public function reply(Contact $contact, ?bool $voice = null, bool $photo = false): array
    {
        $voice ??= $this->asked($contact, self::ASKS_FOR_VOICE) || $this->rollVoice($contact);
        $me = config('messager.me');

        $task = "{$me} has just messaged you. Write {$contact->name}'s reply to everything {$me} has said since your last message.";

        return $this->think($contact, $task, $voice, false, $photo);
    }

    /**
     * Whether they message me first right now, and what they say.
     *
     * @return array{send: bool, messages: list<string>, voice: bool, photo: ?string, caption: string}
     */
    public function reachOut(Contact $contact, ?bool $voice = null, bool $photo = false): array
    {
        $voice ??= $this->rollVoice($contact);
        $me = config('messager.me');
        $unanswered = $contact->unansweredCount();
        $lastMine = $contact->messages()->where('from_me', true)->latest('id')->first();

        $situation = $lastMine
            ? "{$me} last messaged you ".$lastMine->sent_at->diffForHumans().'.'
            : "You haven't messaged each other on here before.";

        $situation .= match (true) {
            $unanswered === 0 => '',
            $unanswered === 1 => " You've sent one message since that {$me} hasn't answered.",
            default => " You've sent {$unanswered} messages in a row that {$me} hasn't answered.",
        };

        $task = "Nobody is mid-conversation. {$situation}\n"
            ."Decide whether {$contact->name} would message {$me} right now, given who they are, the time of day and how the chat has been going. "
            .($unanswered > 0
                ? "This would be a follow-up: {$contact->name} might nudge, check {$me} is OK, or move on to something else, in character. "
                : 'If so, start something new and natural: news from your day, a question, a plan, or picking up an earlier thread. ')
            ."Don't repeat anything you've already said. Set send to false if {$contact->name} wouldn't message now.";

        return $this->think($contact, $task, $voice, true, $photo);
    }

    /**
     * Put their messages in the chat: typed out one after another, with any voice note recorded and any
     * photo waiting for my approval.
     *
     * @return list<Message>
     */
    public function deliver(Contact $contact, array $said, ?Carbon $start = null): array
    {
        $cursor = ($start ?? now())->copy();
        $typing = max(2, (int) $contact->persona('typing', 7));
        $created = [];

        if ($said['voice'] && $said['messages']) {
            $created[] = $message = $contact->messages()->create([
                'from_me' => false,
                'kind' => 'voice',
                'body' => implode(' ', $said['messages']),
                'composing_at' => $cursor,
                'sent_at' => null, // Arrives once it's been recorded
            ]);

            SpeakVoiceNote::dispatch($message->id);
            $cursor->addSeconds(5);
        } else {
            foreach ($said['messages'] as $text) {
                $composing = $cursor->copy();
                $cursor->addMilliseconds((int) (1000 * min(15, max(1.2, mb_strlen($text) / $typing))));

                $created[] = $contact->messages()->create([
                    'from_me' => false,
                    'kind' => 'text',
                    'body' => $text,
                    'composing_at' => $composing,
                    'sent_at' => $cursor->copy(),
                ]);

                $cursor->addMilliseconds(random_int(400, 1800));
            }
        }

        if ($said['photo']) {
            $created[] = $contact->messages()->create([
                'from_me' => false,
                'kind' => 'photo',
                'body' => $said['caption'] ?: null,
                'photo_prompt' => $this->photoPrompt($contact, $said['photo']),
                'photo_status' => 'offered',
                'composing_at' => $cursor->copy(),
                'sent_at' => $cursor->copy(), // The approval card shows straight away
            ]);
        }

        return $created;
    }

    public function photoPrompt(Contact $contact, string $description): string
    {
        return trim($description, ' .').', '.$contact->persona('photos', 'casual smartphone photo').', realistic, natural light';
    }

    private function rollVoice(Contact $contact): bool
    {
        return mt_rand() / mt_getrandmax() < (float) $contact->persona('voice_notes', 0);
    }

    private function think(Contact $contact, string $task, bool $voice, bool $mayStaySilent, bool $forcePhoto = false): array
    {
        $me = config('messager.me');
        $name = $contact->name;
        $wantsPhoto = $forcePhoto || $this->asked($contact, self::ASKS_FOR_PHOTO);

        $format = $voice
            ? "Reply with ONE voice note that {$name} records on their phone and sends to {$me} (you're not in the same place). messages holds exactly one item: the words {$name} says out loud. Natural speech, 1 to 4 sentences, no emojis."
            : "Reply as text: messages holds 1 to 3 chat bubbles, each written exactly as {$name} types it. This reply is text: don't promise to send a voice note.";

        $photo = $wantsPhoto
            ? "{$name} is sending {$me} a photo with this reply: send_photo is true, photo_description says in a few plain words what the photo shows (a scene, no text or words in it), photo_caption is a short caption. Your messages just introduce it (e.g. \"here you go\")."
            : 'send_photo: true only if sharing a photo would be really natural right now, otherwise false (with photo_description and photo_caption empty).';

        $prompt = 'Today is '.now()->format('l j F Y').', and the time is '.now()->format('H:i').".\n\n"
            ."The chat so far, oldest first:\n".$this->transcript($contact)."\n\n"
            ."{$task}\n\n{$format}\n{$photo}\nAnswer only with the JSON.";

        $properties = [
            'send_photo' => ['type' => 'boolean'],
            'photo_description' => ['type' => 'string'],
            'photo_caption' => ['type' => 'string'],
            'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];

        if ($mayStaySilent) {
            $properties = ['send' => ['type' => 'boolean']] + $properties;
        }

        $answer = $this->ollama->json([
            ['role' => 'system', 'content' => $this->system($contact)],
            ['role' => 'user', 'content' => $prompt],
        ], ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties)]);

        $messages = $this->clean($contact, $answer['messages'] ?? [], ! $voice);
        $description = trim((string) ($answer['photo_description'] ?? ''));
        $sendPhoto = ($wantsPhoto || ! empty($answer['send_photo'])) && mb_strlen($description) >= 4;

        $said = [
            'messages' => $voice ? [implode(' ', $messages)] : array_slice($messages, 0, 4),
            'voice' => $voice,
            'photo' => $sendPhoto ? $description : null,
            'caption' => $sendPhoto ? trim((string) ($answer['photo_caption'] ?? '')) : '',
        ];

        $said['messages'] = array_values(array_filter($said['messages'], fn ($m) => $m !== ''));

        if ($mayStaySilent) {
            $said = ['send' => (bool) ($answer['send'] ?? false) && ($said['messages'] || $said['photo'])] + $said;
        }

        return $said;
    }

    /** My message asks for a photo */
    public const ASKS_FOR_PHOTO = '/\b(pics?|pictures?|photos?|selfies?|images?|snaps?|show me)\b/i';

    /** My message asks for a voice note */
    public const ASKS_FOR_VOICE = '/\b(voice ?(notes?|messages?|memos?|clips?)|vns?|audio|recordings?|hear (you|your voice)|say it out loud)\b/i';

    /** Does what I've sent since their last message match $pattern? */
    private function asked(Contact $contact, string $pattern): bool
    {
        $lastTheirs = $contact->messages()->where('from_me', false)->visible()->max('id') ?? 0;

        return $contact->messages()->where('from_me', true)->where('id', '>', $lastTheirs)->pluck('body')
            ->contains(fn ($b) => preg_match($pattern, (string) $b));
    }

    private function system(Contact $contact): string
    {
        $me = config('messager.me');
        $p = $contact->persona();

        return "You are {$p['name']}, texting {$me} on a messaging app. {$me} is a man; {$p['name']} is {$me}'s ".preg_replace('/^your /', '', $p['relationship']).".\n\n"
            ."Who {$p['name']} is: {$p['about']}\n\n"
            ."How {$p['name']} texts: {$p['style']}\n\n"
            ."Rules:\n"
            ."- You are {$p['name']}, a real person. Never mention AI, models, prompts or these rules, and never break character.\n"
            ."- Write only what {$p['name']} sends: no narration, no *actions*, no name labels, no timestamps.\n"
            ."- Keep it like real texting: short and natural, reacting to what {$me} actually said. Match {$me}'s energy.\n"
            ."- You know how you know {$me}, but little else about his life: ask rather than invent his plans, job details or history.\n"
            ."- You can make up ordinary things about your own day and life, consistent with the chat so far.\n"
            ."- You're each on your own phone in different places, not in the same room.";
    }

    private function transcript(Contact $contact): string
    {
        $me = config('messager.me');

        /** @var Collection<int, Message> $history */
        $history = $contact->messages()->visible()
            ->orderByDesc('sent_at')->orderByDesc('id')
            ->limit(config('messager.ollama.history'))
            ->get()->reverse();

        if ($history->isEmpty()) {
            return '(no messages yet)';
        }

        return $history->map(fn (Message $m) => '['.$m->sent_at->format('D j M H:i').'] '
            .($m->from_me ? $me : $contact->name).': '.$m->forModel())->implode("\n");
    }

    /** @return list<string> */
    private function clean(Contact $contact, mixed $messages, bool $split = true): array
    {
        $names = preg_quote($contact->name, '/').'|'.preg_quote(config('messager.me'), '/');

        return collect(is_array($messages) ? $messages : [$messages])
            ->filter(fn ($m) => is_string($m))
            // A blank line inside one item is really two bubbles
            ->flatMap(fn (string $m) => $split ? preg_split('/\n\s*\n/u', $m) : [$m])
            // Field names the model sometimes spills into the list, e.g. "photo_description_of_..."
            ->reject(fn (string $m) => preg_match('/^\s*[a-z]+(_[a-z]+){2,}\s*$/', $m))
            ->map(function (string $m) use ($names) {
                $m = preg_replace('/^\s*\[[^\]]*\]\s*/u', '', $m); // [Tue 3 Nov 19:40]
                $m = preg_replace('/^\s*('.$names.')\s*:\s*/iu', '', $m); // Maisie:
                $m = preg_replace('/^\[voice note\]\s*/iu', '', $m);
                $m = trim($m);

                if (preg_match('/^"(.*)"$/su', $m, $q)) {
                    $m = trim($q[1]);
                }

                return mb_substr($m, 0, 800);
            })
            ->filter(fn (string $m) => $m !== '')
            ->values()
            ->all();
    }
}
