<?php

namespace App\Livewire;

use App\Jobs\RenderPhoto;
use App\Jobs\ReplyToMe;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Messenger extends Component
{
    /** The open chat's contact slug */
    #[Url(as: 'chat', except: '')]
    public string $chat = '';

    public string $search = '';

    public function open(string $slug): void
    {
        $this->chat = Contact::where('slug', $slug)->exists() ? $slug : '';
    }

    public function close(): void
    {
        $this->chat = '';
    }

    public function send(string $text): void
    {
        $text = trim(mb_substr($text, 0, 4000));
        $contact = $this->contact();

        if ($text === '' || ! $contact) {
            return;
        }

        $message = $contact->messages()->create([
            'from_me' => true,
            'kind' => 'text',
            'body' => $text,
            'sent_at' => now(),
        ]);

        [$min, $max] = config('messager.reply_debounce');
        ReplyToMe::dispatch($contact->id, $message->id)->delay(now()->addSeconds(random_int($min, $max)));
    }

    public function approvePhoto(int $id, string $prompt): void
    {
        $message = $this->theirMessage($id);
        $prompt = trim(mb_substr($prompt, 0, 1500));

        if (! $message || ! in_array($message->photo_status, ['offered', 'failed'], true) || $prompt === '') {
            return;
        }

        $message->update(['photo_status' => 'queued', 'photo_prompt' => $prompt]);
        RenderPhoto::dispatch($message->id);
    }

    public function declinePhoto(int $id): void
    {
        $message = $this->theirMessage($id);

        if ($message && in_array($message->photo_status, ['offered', 'failed'], true)) {
            $message->update(['photo_status' => 'declined']);
        }
    }

    public function render()
    {
        $contact = $this->contact();
        $contact?->markSeen();

        return view('livewire.messenger', [
            'contacts' => $this->contacts(),
            'contact' => $contact,
            'messages' => $contact
                ? $contact->messages()->visible()->orderBy('sent_at')->orderBy('id')->get()
                : collect(),
            'activity' => $contact?->activity(),
            'lastSeen' => $contact?->lastSeen(),
            // Jobs a minute overdue mean no queue worker is running
            'queueStuck' => DB::table('jobs')->where('available_at', '<', now()->subMinute()->timestamp)->whereNull('reserved_at')->exists(),
        ]);
    }

    private function contact(): ?Contact
    {
        return $this->chat === '' ? null : Contact::where('slug', $this->chat)->first();
    }

    private function theirMessage(int $id): ?Message
    {
        return Message::where('from_me', false)->where('kind', 'photo')->find($id);
    }

    /** Everyone, most recent chat first */
    private function contacts(): Collection
    {
        return Contact::with('latestMessage')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->get()
            ->sortByDesc(fn (Contact $c) => $c->latestMessage?->sent_at?->timestamp ?? 0)
            ->values();
    }
}
