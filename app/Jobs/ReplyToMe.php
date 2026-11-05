<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ContactBrain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/** A contact reads what I've sent and answers it */
class ReplyToMe implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public array $backoff = [10, 30];

    /** @param int $afterMessageId The message of mine that set this off */
    public function __construct(public int $contactId, public int $afterMessageId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-'.$this->contactId))->releaseAfter(5)->expireAfter(300)];
    }

    public function handle(ContactBrain $brain): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact) {
            return;
        }

        $latestMine = $contact->messages()->where('from_me', true)->max('id');

        // I've sent more since: the reply to that one covers this too
        if ($latestMine !== $this->afterMessageId) {
            return;
        }

        // Already answered (a retry, or someone else got there first)
        if ($contact->messages()->where('from_me', false)->where('id', '>', $this->afterMessageId)->whereNull('photo_status')->exists()) {
            return;
        }

        [$min, $max] = $contact->persona('reply_after', [2, 20]);
        $pickUp = now()->addSeconds(random_int((int) $min, (int) $max));

        $said = $brain->reply($contact);

        // They read everything I've sent when they pick up their phone
        $contact->messages()->where('from_me', true)->whereNull('read_at')->update(['read_at' => $pickUp]);

        if (! $said['messages'] && ! $said['photo']) {
            $said['messages'] = ['👍'];
            $said['voice'] = false;
        }

        // Thinking took a while already: count it towards their pick-up time
        $brain->deliver($contact, $said, $pickUp->max(now())->addSeconds(1));
    }
}
