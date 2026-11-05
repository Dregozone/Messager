<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Carbon;

/** Each minute: would this contact think about messaging me now? Their persona's chattiness decides. */
class Chattiness
{
    /** @var callable(): float */
    private $random;

    public function __construct(?callable $random = null)
    {
        $this->random = $random ?? fn () => mt_rand() / mt_getrandmax();
    }

    /**
     * 'reply' when my last message has gone unanswered (a reply that failed), 'reach-out' to think
     * about messaging first, or null to leave it this minute.
     */
    public function decide(Contact $contact, ?Carbon $now = null): ?string
    {
        $now ??= now();
        $c = $contact->persona('chattiness', []);

        // Busy with something already
        if ($contact->messages()->where('from_me', false)->pending()->exists()) {
            return null;
        }

        $last = $contact->messages()->visible()->orderByDesc('sent_at')->orderByDesc('id')->first();

        if ($last?->from_me) {
            // ReplyToMe normally handles it: only step in if it seems to have been lost
            $stale = $last->created_at->lt($now->copy()->subMinutes(10));

            return $stale && $last->read_at === null ? 'reply' : null;
        }

        if (! $this->awake($c, $now)) {
            return null;
        }

        $unanswered = $contact->unansweredCount();

        if ($unanswered >= ($c['max_unanswered'] ?? 2)) {
            return null;
        }

        if ($last && $last->sent_at->gt($now->copy()->subMinutes($c['min_gap'] ?? 60))) {
            return null;
        }

        if ($unanswered > 0 && $last->sent_at->lte($now->copy()->subMinutes($c['follow_up'] ?? 120))) {
            // A follow-up is due: they get round to it within ten minutes or so
            $chance = 0.1;
        } elseif ($unanswered > 0) {
            return null;
        } else {
            [$from, $until] = $c['hours'] ?? [9, 21];
            $chance = ($c['per_day'] ?? 1) / max(60, ($until - $from) * 60);
        }

        return ($this->random)() < $chance ? 'reach-out' : null;
    }

    private function awake(array $c, Carbon $now): bool
    {
        [$from, $until] = $c['hours'] ?? [9, 21];

        if (($c['weekdays_only'] ?? false) && $now->isWeekend()) {
            return false;
        }

        return $now->hour >= $from && $now->hour < $until;
    }
}
