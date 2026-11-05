<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ContactBrain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/** A contact considers messaging me first */
class ReachOut implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    /**
     * @param  bool  $force  Message me whatever (contacts:nudge)
     * @param  bool|null  $voice  A voice note (true), text (false), or left to chance (null)
     * @param  bool  $photo  Include a photo offer
     */
    public function __construct(public int $contactId, public bool $force = false, public ?bool $voice = null, public bool $photo = false) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-'.$this->contactId))->dontRelease()->expireAfter(300)];
    }

    public function handle(ContactBrain $brain): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact) {
            return;
        }

        // Something's already on its way, or my last message is waiting on a reply (that's ReplyToMe's job)
        if ($contact->messages()->where('from_me', false)->pending()->exists()) {
            return;
        }

        $last = $contact->messages()->visible()->orderByDesc('sent_at')->orderByDesc('id')->first();

        if ($last?->from_me && ! $this->force) {
            return;
        }

        $said = $brain->reachOut($contact, $this->voice, $this->photo);

        if (! $said['send'] && ! $this->force) {
            return;
        }

        if (! $said['messages'] && ! $said['photo']) {
            return;
        }

        $brain->deliver($contact, $said);
    }
}
