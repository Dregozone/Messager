<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Contact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_considered_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Their persona from config/contacts.php */
    public function persona(?string $key = null, mixed $default = null): mixed
    {
        $persona = config('contacts.'.$this->slug, []);

        return $key === null ? $persona : data_get($persona, $key, $default);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(['sent_at' => 'max', 'id' => 'max'], fn (Builder $q) => $q->visible());
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path).'?v='.$this->updated_at?->timestamp : null;
    }

    public function gradient(): string
    {
        [$from, $to] = $this->persona('colour', ['#94a3b8', '#475569']);

        return "linear-gradient(135deg, {$from}, {$to})";
    }

    /** What they're doing right now: 'recording' a voice note, 'typing', or null */
    public function activity(): ?string
    {
        $pending = $this->messages()->where('from_me', false)->composing()->oldest('id')->first();

        return match (true) {
            $pending === null => null,
            $pending->kind === 'voice' => 'recording',
            default => 'typing',
        };
    }

    /** When they were last around: their last message, or their last reading of mine */
    public function lastSeen(): ?Carbon
    {
        $sent = $this->messages()->where('from_me', false)->visible()->max('sent_at');
        $read = $this->messages()->where('from_me', true)->where('read_at', '<=', now())->max('read_at');

        return collect([$sent, $read])->filter()->map(fn ($t) => Carbon::parse($t))->max();
    }

    /** How many of their messages in a row I've left without a reply */
    public function unansweredCount(): int
    {
        $lastMine = $this->messages()->where('from_me', true)->max('id') ?? 0;

        return $this->messages()->where('from_me', false)->visible()->where('id', '>', $lastMine)
            ->where(fn ($q) => $q->whereNull('photo_status')->orWhere('photo_status', 'ready'))
            ->count();
    }

    public function unreadCount(): int
    {
        return $this->messages()->where('from_me', false)->visible()->whereNull('seen_at')->count();
    }

    public function markSeen(): void
    {
        $this->messages()->where('from_me', false)->visible()->whereNull('seen_at')->update(['seen_at' => now()]);
    }
}
