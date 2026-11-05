@php
    $when = function (?\Illuminate\Support\Carbon $t): string {
        if (! $t) return '';
        if ($t->isToday()) return $t->format('H:i');
        if ($t->isYesterday()) return 'Yesterday';
        if ($t->gt(now()->subDays(6))) return $t->format('l');
        return $t->format('d/m/Y');
    };
    $day = function (\Illuminate\Support\Carbon $t): string {
        if ($t->isToday()) return 'Today';
        if ($t->isYesterday()) return 'Yesterday';
        if ($t->gt(now()->subDays(6))) return $t->format('l');
        return $t->format('j F Y');
    };
@endphp

<div wire:poll.2s class="flex h-dvh w-full overflow-hidden bg-canvas">

    {{-- Chat list --}}
    <aside class="{{ $contact ? 'hidden md:flex' : 'flex' }} w-full md:w-[380px] lg:w-[420px] shrink-0 flex-col border-r border-line bg-panel">
        <header class="flex items-center justify-between px-5 pt-5 pb-3">
            <h1 class="text-2xl font-bold tracking-tight">Chats</h1>
            <div class="flex items-center gap-2 rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent-strong">
                <x-icon name="lock" class="size-3.5" /> Local only
            </div>
        </header>

        <div class="px-4 pb-3">
            <label class="flex items-center gap-3 rounded-xl bg-panel-2 px-4 py-2.5 text-muted focus-within:ring-2 focus-within:ring-accent/40">
                <x-icon name="search" class="size-4" />
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search" class="w-full bg-transparent text-sm text-ink outline-none placeholder:text-muted">
            </label>
        </div>

        @if ($queueStuck)
            <div class="mx-4 mb-3 flex gap-3 rounded-xl border border-amber-300/50 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <x-icon name="alert" class="mt-0.5 size-4" />
                <p>Messages are waiting to be processed. Is the queue worker running? Start everything with <code class="font-mono font-semibold">composer dev</code>.</p>
            </div>
        @endif

        <nav class="scrollbar-thin flex-1 overflow-y-auto pb-4">
            @forelse ($contacts as $c)
                @php
                    $last = $c->latestMessage;
                    $act = $c->activity();
                    $unread = $c->unreadCount();
                    $active = $contact?->is($c);
                @endphp
                <button
                    wire:key="contact-{{ $c->id }}"
                    wire:click="open('{{ $c->slug }}')"
                    class="group flex w-full items-center gap-3 px-4 py-3 text-left transition-colors {{ $active ? 'bg-panel-2' : 'hover:bg-panel-2/70' }}"
                >
                    <x-avatar :contact="$c" size="size-13" />
                    <div class="min-w-0 flex-1 border-b border-line pb-3 group-last:border-0 -mb-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate font-semibold">{{ $c->name }}</span>
                            <span class="shrink-0 text-xs {{ $unread ? 'font-semibold text-accent' : 'text-muted' }}">{{ $when($last?->sent_at) }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between gap-2">
                            <p class="flex min-w-0 items-center gap-1 truncate text-sm {{ $act ? 'font-medium text-accent' : 'text-muted' }}">
                                @if ($act)
                                    {{ $act === 'recording' ? 'recording audio…' : 'typing…' }}
                                @elseif ($last)
                                    @if ($last->from_me)
                                        <x-icon name="checks" class="size-4 {{ $last->read_at && $last->read_at->lte(now()) ? 'text-read' : 'text-muted' }}" />
                                    @endif
                                    <span class="truncate">{{ $last->preview() }}</span>
                                @else
                                    <span class="truncate italic">{{ ucfirst(\Illuminate\Support\Str::before($c->persona('relationship', ''), ';')) }}</span>
                                @endif
                            </p>
                            @if ($unread)
                                <span class="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-accent px-1.5 text-[11px] font-bold text-white">{{ $unread }}</span>
                            @endif
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-6 py-10 text-center text-sm text-muted">
                    @if ($search !== '') No one called "{{ $search }}". @else No contacts yet. Run <code class="font-mono">php artisan contacts:sync</code>. @endif
                </p>
            @endforelse
        </nav>
    </aside>

    {{-- Conversation --}}
    <main class="{{ $contact ? 'flex' : 'hidden md:flex' }} min-w-0 flex-1 flex-col">
        @if (! $contact)
            <div class="chat-wall grid flex-1 place-items-center p-8">
                <div class="max-w-sm text-center">
                    <div class="mx-auto mb-6 grid size-20 place-items-center rounded-3xl bg-accent text-white shadow-lg shadow-accent/30">
                        <x-icon name="chat" class="size-10" />
                    </div>
                    <h2 class="text-2xl font-semibold">Messager</h2>
                    <p class="mt-2 text-muted">Pick someone to chat with. They'll reply in their own style, send voice notes, and might message you first when you go quiet.</p>
                    <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-muted"><x-icon name="lock" class="size-3.5" /> Everything runs on this PC</p>
                </div>
            </div>
        @else
            {{-- Header --}}
            <header wire:key="header-{{ $contact->id }}" class="flex items-center gap-3 border-b border-line bg-panel px-3 py-2.5 md:px-5">
                <button wire:click="close" class="-ml-1 grid size-9 place-items-center rounded-full text-muted hover:bg-panel-2 md:hidden" aria-label="Back to chats">
                    <x-icon name="back" class="size-6" />
                </button>
                <x-avatar :contact="$contact" size="size-10" />
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-semibold leading-tight">{{ $contact->name }}</h2>
                    <p class="truncate text-xs {{ $activity ? 'font-medium text-accent' : 'text-muted' }}">
                        @if ($activity === 'recording') recording audio…
                        @elseif ($activity) typing…
                        @elseif ($lastSeen && $lastSeen->gt(now()->subMinutes(2))) online
                        @elseif ($lastSeen) last seen {{ $lastSeen->isToday() ? 'today' : ($lastSeen->isYesterday() ? 'yesterday' : $lastSeen->format('j M')) }} at {{ $lastSeen->format('H:i') }}
                        @else {{ ucfirst(\Illuminate\Support\Str::before($contact->persona('relationship', ''), ';')) }}
                        @endif
                    </p>
                </div>
            </header>

            {{-- Messages --}}
            <div
                x-data="{
                    stick: true,
                    init() {
                        this.$nextTick(() => this.bottom())
                        new MutationObserver(() => { if (this.stick) this.bottom() }).observe(this.$el, { childList: true, subtree: true })
                    },
                    bottom() { this.$el.scrollTop = this.$el.scrollHeight },
                    track() { this.stick = this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 120 },
                }"
                x-on:scroll.passive="track()"
                x-on:sent.window="stick = true; $nextTick(() => bottom())"
                wire:key="messages-{{ $contact->id }}"
                class="chat-wall scrollbar-thin flex-1 overflow-y-auto px-3 py-4 md:px-[6%]"
            >
                <div class="mx-auto flex max-w-3xl flex-col">
                    <div class="mx-auto mb-4 max-w-md rounded-lg bg-amber-100/90 px-3 py-1.5 text-center text-xs text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                        <x-icon name="lock" class="mr-1 inline size-3" /> {{ $contact->name }} is a simulated contact. Messages, voice notes and photos are generated on this PC.
                    </div>

                    @php $prev = null; @endphp
                    @foreach ($messages as $m)
                        @php
                            $newDay = ! $prev || ! $prev->sent_at->isSameDay($m->sent_at);
                            $firstOfRun = $newDay || $prev->from_me !== $m->from_me;
                            $prev = $m;
                            $read = $m->from_me && $m->read_at && $m->read_at->lte(now());
                        @endphp

                        @if ($newDay)
                            <div class="sticky top-0 z-10 my-3 flex justify-center" wire:key="day-{{ $m->sent_at->format('Ymd') }}">
                                <span class="rounded-lg bg-panel/95 px-3 py-1 text-xs font-medium text-muted shadow-sm backdrop-blur">{{ $day($m->sent_at) }}</span>
                            </div>
                        @endif

                        <div wire:key="m-{{ $m->id }}-{{ $m->photo_status }}" class="pop-in flex {{ $m->from_me ? 'justify-end' : 'justify-start' }} {{ $firstOfRun ? 'mt-3' : 'mt-0.5' }}">

                            @if ($m->kind === 'photo' && in_array($m->photo_status, ['offered', 'failed']))
                                {{-- Approval card --}}
                                <div class="w-full max-w-md rounded-2xl border border-accent/30 bg-panel p-4 shadow-sm" x-data="{ prompt: @js($m->photo_prompt), busy: false }">
                                    <div class="flex items-center gap-3">
                                        <div class="grid size-10 place-items-center rounded-xl bg-accent-soft text-accent-strong"><x-icon name="image" /></div>
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $contact->name }} wants to send a photo</p>
                                            <p class="text-xs text-muted">
                                                @if ($m->photo_status === 'failed')
                                                    <span class="text-red-500">That didn't work. Is ComfyUI running?</span> Edit and try again.
                                                @else
                                                    Nothing is generated until you approve it.
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    @if ($m->body)
                                        <p class="mt-3 text-sm">Caption: <span class="italic">“{{ $m->body }}”</span></p>
                                    @endif
                                    <label class="mt-3 block text-xs font-medium text-muted">Image prompt</label>
                                    <textarea x-model="prompt" rows="3" wire:ignore class="mt-1 w-full resize-y rounded-xl border border-line bg-panel-2 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-accent/40"></textarea>
                                    <div class="mt-3 flex justify-end gap-2">
                                        <button x-on:click="busy = true; $wire.declinePhoto({{ $m->id }})" x-bind:disabled="busy" class="rounded-full px-4 py-2 text-sm font-medium text-muted hover:bg-panel-2 disabled:opacity-50">Decline</button>
                                        <button x-on:click="busy = true; $wire.approvePhoto({{ $m->id }}, prompt)" x-bind:disabled="busy || ! prompt.trim()" class="flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-accent-strong disabled:opacity-50">
                                            <x-icon name="sparkles" class="size-4" /> {{ $m->photo_status === 'failed' ? 'Try again' : 'Approve & generate' }}
                                        </button>
                                    </div>
                                </div>

                            @elseif ($m->kind === 'photo' && $m->photo_status === 'declined')
                                <p class="mx-auto rounded-lg bg-panel/80 px-3 py-1 text-xs text-muted">You declined {{ $contact->name }}'s photo</p>

                            @else
                                {{-- Bubble --}}
                                <div class="relative max-w-[85%] md:max-w-[65%] rounded-2xl px-2.5 py-1.5 shadow-sm {{ $m->from_me ? 'bg-mine' : 'bg-theirs' }} {{ $firstOfRun ? ($m->from_me ? 'bubble-tail-mine rounded-tr-none' : 'bubble-tail-theirs rounded-tl-none') : '' }}">

                                    @if ($m->kind === 'voice')
                                        <div
                                            class="flex w-64 max-w-full items-center gap-3 py-1 pl-1"
                                            x-data="{
                                                playing: false, at: 0, length: {{ $m->audio_seconds ?? 0 }}, transcript: false,
                                                toggle() { const a = this.$refs.audio; a.paused ? a.play() : a.pause() },
                                                seek(e) { const r = e.currentTarget.getBoundingClientRect(); this.$refs.audio.currentTime = (e.clientX - r.left) / r.width * this.length },
                                                clock(s) { s = Math.round(s); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') },
                                            }"
                                        >
                                            <audio x-ref="audio" preload="metadata" src="{{ $m->audioUrl() }}"
                                                x-on:play="playing = true" x-on:pause="playing = false" x-on:ended="playing = false; at = 0"
                                                x-on:timeupdate="at = $event.target.currentTime"></audio>
                                            <div class="relative">
                                                <x-avatar :contact="$contact" size="size-11" />
                                                <span class="absolute -right-1 -bottom-1 grid size-5 place-items-center rounded-full bg-accent text-white ring-2 ring-theirs"><x-icon name="mic" class="size-3" /></span>
                                            </div>
                                            <button x-on:click="toggle()" class="grid size-9 shrink-0 place-items-center rounded-full text-muted hover:text-ink" aria-label="Play voice note">
                                                <x-icon name="play" class="size-6" x-show="! playing" />
                                                <x-icon name="pause" class="size-6" x-show="playing" x-cloak />
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex h-7 cursor-pointer items-center gap-[2px]" x-on:click="seek($event)">
                                                    @foreach (range(0, 27) as $i)
                                                        @php $h = 20 + (crc32($m->id . '-' . $i) % 80); @endphp
                                                        <span class="w-[3px] rounded-full transition-colors" style="height: {{ $h }}%"
                                                            x-bind:class="at / length > {{ $i / 28 }} ? 'bg-accent' : 'bg-muted/40'"></span>
                                                    @endforeach
                                                </div>
                                                <div class="mt-0.5 flex justify-between text-[11px] text-muted">
                                                    <span x-text="clock(playing || at ? at : length)">{{ $m->duration() }}</span>
                                                    <button x-on:click="transcript = ! transcript" class="hover:text-ink hover:underline" x-text="transcript ? 'Hide transcript' : 'Transcript'">Transcript</button>
                                                </div>
                                            </div>
                                        </div>
                                        <p x-show="transcript" x-cloak class="mx-1 mb-1 border-t border-line pt-1.5 text-sm italic text-muted">{{ $m->body }}</p>

                                    @elseif ($m->kind === 'photo' && $m->photo_status === 'ready')
                                        <div x-data="{ zoom: false }">
                                            <button x-on:click="zoom = true" class="block overflow-hidden rounded-xl">
                                                <img src="{{ $m->photoUrl() }}" alt="{{ $m->body ?: 'Photo' }}" class="aspect-square w-72 max-w-full object-cover transition-transform hover:scale-[1.02]" loading="lazy">
                                            </button>
                                            <template x-teleport="body">
                                                <div x-show="zoom" x-cloak x-transition.opacity x-on:click="zoom = false" x-on:keydown.escape.window="zoom = false" class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-6">
                                                    <img src="{{ $m->photoUrl() }}" alt="" class="max-h-full max-w-full rounded-xl shadow-2xl">
                                                    <button class="absolute top-5 right-5 grid size-10 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close"><x-icon name="x" /></button>
                                                </div>
                                            </template>
                                        </div>
                                        @if ($m->body)
                                            <p class="px-1 pt-1.5 whitespace-pre-wrap break-words text-[15px] leading-snug">{{ $m->body }}</p>
                                        @endif

                                    @elseif ($m->kind === 'photo')
                                        <div class="grid aspect-square w-64 max-w-full place-items-center overflow-hidden rounded-xl bg-panel-2">
                                            <div class="flex flex-col items-center gap-2 text-sm text-muted">
                                                <x-icon name="image" class="size-8 animate-pulse" />
                                                {{ $m->photo_status === 'queued' ? 'Waiting for ComfyUI…' : 'Generating photo…' }}
                                            </div>
                                        </div>

                                    @else
                                        <p class="px-1 whitespace-pre-wrap break-words text-[15px] leading-snug">{{ $m->body }}<span class="inline-block w-16"></span></p>
                                    @endif

                                    <span class="{{ $m->kind === 'text' ? 'absolute right-2.5 bottom-1' : 'flex justify-end px-1' }} flex items-center gap-1 text-[11px] text-muted">
                                        {{ $m->sent_at->format('H:i') }}
                                        @if ($m->from_me)
                                            <x-icon name="checks" class="size-4 {{ $read ? 'text-read' : '' }}" />
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if ($activity)
                        <div class="pop-in mt-3 flex justify-start" wire:key="activity">
                            <div class="bubble-tail-theirs relative flex items-center gap-2 rounded-2xl rounded-tl-none bg-theirs px-4 py-3 shadow-sm">
                                @if ($activity === 'recording')
                                    <x-icon name="mic" class="size-4 animate-pulse text-red-500" />
                                    <span class="text-sm text-muted">recording…</span>
                                @else
                                    <span class="typing-dot size-2 rounded-full bg-muted"></span>
                                    <span class="typing-dot size-2 rounded-full bg-muted"></span>
                                    <span class="typing-dot size-2 rounded-full bg-muted"></span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Composer --}}
            <footer
                wire:key="composer-{{ $contact->id }}"
                wire:ignore
                x-data="{
                    text: '',
                    send() {
                        const text = this.text.trim()
                        if (! text) return
                        this.text = ''
                        this.$nextTick(() => this.grow())
                        $wire.send(text)
                        window.dispatchEvent(new CustomEvent('sent'))
                    },
                    grow() { const t = this.$refs.input; t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px' },
                }"
                x-init="$refs.input.focus()"
                class="flex items-end gap-2 border-t border-line bg-panel px-3 py-3 md:px-5"
            >
                <div class="flex flex-1 items-end rounded-3xl bg-panel-2 px-4 py-2.5 focus-within:ring-2 focus-within:ring-accent/40">
                    <textarea
                        x-ref="input"
                        x-model="text"
                        x-on:input="grow()"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send() }"
                        rows="1"
                        placeholder="Message {{ $contact->name }}"
                        class="scrollbar-thin max-h-40 w-full resize-none bg-transparent text-[15px] leading-6 outline-none placeholder:text-muted"
                    ></textarea>
                </div>
                <button
                    x-on:click="send()"
                    x-bind:class="text.trim() ? 'bg-accent hover:bg-accent-strong' : 'bg-accent/50'"
                    class="grid size-11 shrink-0 place-items-center rounded-full text-white shadow-sm transition-colors"
                    aria-label="Send"
                >
                    <x-icon name="send" class="size-5 translate-x-px" />
                </button>
            </footer>
        @endif
    </main>
</div>
