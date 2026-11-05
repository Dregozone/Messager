<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'audio_seconds' => 'float',
            'composing_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Messages that have arrived in the chat */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNotNull('sent_at')->where('sent_at', '<=', now());
    }

    /** Their messages not yet arrived */
    public function scopePending(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('sent_at')->orWhere('sent_at', '>', now()));
    }

    /** Their messages they're typing or recording right now */
    public function scopeComposing(Builder $query): void
    {
        $query->pending()->where('composing_at', '<=', now())->where('kind', '!=', 'photo');
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** How this message reads in the chat list */
    public function preview(): string
    {
        return match ($this->kind) {
            'voice' => '🎤 Voice message ('.$this->duration().')',
            'photo' => match ($this->photo_status) {
                'ready' => '📷 '.($this->body ?: 'Photo'),
                'declined' => '📷 Photo declined',
                default => '📷 Wants to send a photo',
            },
            default => (string) $this->body,
        };
    }

    public function duration(): string
    {
        $seconds = (int) round($this->audio_seconds ?? 0);

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    /** How a model sees this message in the chat history */
    public function forModel(): string
    {
        return match ($this->kind) {
            'voice' => '[voice note] '.$this->body,
            'photo' => match ($this->photo_status) {
                'ready' => '[sent a photo: '.$this->photo_prompt.'] '.$this->body,
                'declined', 'failed' => '[tried to send a photo but it didn\'t go through]',
                default => '[sending a photo: '.$this->photo_prompt.']',
            },
            default => (string) $this->body,
        };
    }
}
