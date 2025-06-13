<div
    x-data="{
        recipientTyping: false,

        async sendMessage() {
            await $wire.sendMessage()
            console.log('message sent')
            
            const response = await $wire.awaitResponse()
            console.log('letting recipient read the message for ' + response.waitForRead + ' seconds')

            // Wait for the recipient to read your message
            setTimeout(async () => {
                this.recipientTyping = true
                console.log('recipient has started typing...')

                // Send image if there is one
                if (response.imageToSend != null) {
                    setTimeout(() => {
                        this.recipientTyping = false
                        console.log('recipient has stopped typing...')

                        console.log(response.imageToSend)
                        
                        console.log('Will send image to user: ' + response.imageToSend)
                        $wire.addMessage(response.user, response.imageToSend)
                        console.log('image added to chat')
                        
                        // Then simulate recipient typing for a few seconds
                        setTimeout(() => {
                            this.recipientTyping = true
                            console.log('recipient has started typing...')
                        }, 1000 * 2.1) // Number of milliseconds

                        // Send message text
                        setTimeout(() => {
                            this.recipientTyping = false
                            console.log('recipient has stopped typing...')

                            $wire.addMessage(response.user, response.text)
                            console.log('message added to chat')
                        }, 1000 * response.waitForResponse / 2) // Number of milliseconds
                    }, 1000 * response.waitForResponse / 2) // Number of milliseconds

                } else {
                    // Send message text
                    setTimeout(() => {
                        this.recipientTyping = false
                        console.log('recipient has stopped typing...')

                        $wire.addMessage(response.user, response.text)
                        console.log('message added to chat')
                    }, 1000 * response.waitForResponse / 2) // Number of milliseconds 
                }
            }, 1000 * response.waitForRead) // Number of milliseconds
        }
    }"
    class="h-screen bg-zinc-100"
>
    <div class="max-w-2xl mx-auto flex flex-col h-full shadow-2xl shadow-zinc-400">
        {{-- Header --}}
        <div class="bg-green-800 text-white p-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
            {{-- Back button, Avatar, Name --}}
                <button class="p-2 text-2xl">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>

                @if (file_exists(public_path('images/avatars/{{ $recipient }}.png'))) {{-- If the users avatar exists, pull this, otherwise use a placeholder avatar --}}
                    <img 
                        src="{{ asset('images/avatars/' . $recipient . '.png') }}" 
                        alt="Avatar"
                        class="h-10 w-10 rounded-full inline-block"
                    >
                @else
                    <div class="h-10 w-10 bg-gray-300 rounded-full inline-block flex justify-center items-center text-center">
                        <span class="inline-block mt-2 text-green-900">{{ $recipientInitials }}</span>
                    </div>
                @endif

                <h1 class="text-2xl font-light ml-4">{{ $recipient }}</h1>
            </div>

            <div class="flex items-center gap-3 text-xl pr-2">
                {{-- Icons: Vid call, Call, menu --}}
                <button class="p-2">
                    <i class="fas fa-video"></i>
                </button>
                <button class="p-2">
                    <i class="fas fa-phone"></i>
                </button>
                <button class="p-2">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
        </div>

        {{-- Display all messages --}}
        <div class="max-h-[82vh] flex-1 overflow-y-auto p-6 bg-sky-100">
            @foreach($messages as $message)
                <div class="mb-4">

                    @if (strpos($message['text'], '<img ') !== false) {{-- If this is an image message --}}
                        <div>
                            <p>{!! $message['text'] !!}</p>
                        </div>

                    @else {{-- If this is NOT an image message --}}
                        <div 
                            class="
                                flex items-start w-max-[80%]
                                {{ $message['user'] === $user ? 'justify-end' : 'justify-start' }} 
                            "
                        >
                            <p
                                class="
                                    border px-4 py-3 rounded-lg text-xl
                                    {{ $message['user'] === $user ? 'bg-green-50 border-green-100' : 'bg-zinc-50 border-zinc-100' }} 
                                    shadow-sm
                                "
                            >
                                {!! $message['text'] !!}
                            
                                <span class="text-gray-500 text-sm ml-2">
                                    {{ $message['created_at']->format('H:i') }}
                                </span>
                            </p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Typing identifier --}}
        <div x-show="recipientTyping" class="p-4 text-gray-500 text-xl italic text-center bg-sky-100">
            {{ $recipient }} is typing . . .
        </div>


        {{-- Type a message --}}
        <div class="flex justify-evenly items-center p-4 bg-sky-100">
            <div class="bg-white rounded-3xl px-3 py-2 flex items-center w-full mr-2">
                {{-- Smiley icon --}}
                <button class="p-2 text-zinc-300 hover:text-gray-500">
                    <i class="fas fa-smile text-2xl"></i>
                </button>

                {{-- Type a message --}}
                <input 
                    type="text" 
                    wire:model.live="newMessage" 
                    x-on:keydown.enter="sendMessage()"
                    class="flex-1 p-2 rounded text-xl ml-2" 
                    placeholder="Type a message" 
                />

                {{-- Paperclip --}}
                <button class="p-2 text-gray-500 hover:text-gray-700 mr-2">
                    <i class="fas fa-paperclip rotate-270 text-2xl"></i>
                </button>

                {{-- Upload camera --}}
                <button class="p-2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-camera text-2xl"></i>
                </button>
            </div>

            <button 
                type="submit" 
                @if ($newMessage != '') x-on:click="sendMessage()" @endif 
                class="ml-2 px-4 py-2 bg-green-700 text-white h-13 w-13 rounded-full flex justify-center items-center hover:bg-green-800 transition-colors duration-200"
            >
                @if ($newMessage == '')
                    <i class="fas fa-microphone text-2xl"></i>
                @else
                    <i class="fas fa-paper-plane text-2xl"></i>
                @endif
            </button>
        </div>

    </div>
</div>
