<?php

namespace App\Livewire;

use App\Services\ChatResponseService;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Chat extends Component
{
    // The only people that can be chatted with; the recipient is used in file paths, so never trust it otherwise
    public const RECIPIENTS = ['Alice', 'Bob', 'Charlie', 'Diana', 'Recipient', 'Person 2'];

    #[Url]
    public string $user = ''; // The user sending the message

    #[Url, Locked]
    public string $recipient = ''; // The recipient of the messages who is being spoken to and simulated

    public string $recipientInitials = ''; // Initials of the recipient for display purposes

    public array $messages = [];

    public string $newMessage = '';

    public array $users = [];

    public function mount()
    {
        if ($this->user == '') {
            $this->user = 'User' . rand(1, 100); // Assign a random user name for demonstration
        }
        
        $this->messages = []; // Initialize messages array

        $this->recipient = self::allowedRecipient($this->recipient); // Comes from the URL, so check it

        if ($this->recipient !== '') {
            // Find recipient initials
            $recipientParts = explode(' ', $this->recipient);
            $this->recipientInitials = '';
            foreach ($recipientParts as $part) {
                $this->recipientInitials .= strtoupper($part[0]); // Get initials of recipient
            }
        }

        // Build list of users that can be chatted with
        $this->users = array_combine(self::RECIPIENTS, self::RECIPIENTS);
    }

    public static function allowedRecipient(string $recipient): string
    {
        return in_array($recipient, self::RECIPIENTS, true) ? $recipient : '';
    }

    public function selectRecipient(string $recipient)
    {
        $recipient = self::allowedRecipient($recipient);

        $this->recipient = $recipient;

        if ($recipient !== '') {
            // Find recipient initials
            $recipientParts = explode(' ', $this->recipient);
            $this->recipientInitials = '';
            foreach ($recipientParts as $part) {
                $this->recipientInitials .= strtoupper($part[0]); // Get initials of recipient
            }

            // Reset messages for the new recipient
            $this->messages = [];
        }
    }

    public function sendMessage()
    {
        if (trim($this->newMessage) === '') {
            return;
        }

        $this->messages[] = [
            'text' => $this->newMessage,
            'user' => $this->user,
            'created_at' => now(),
        ];

        $this->newMessage = ''; // Clear the input field after sending
    }

    public function awaitResponse()
    {
        if (empty($this->messages)) {
            return; // No messages to respond to
        }

        // Find most recently sent message
        $lastMessage = end($this->messages);

        $waitForRead = 0.5 * strlen($lastMessage['text']);
        // sleep($waitForRead); // Wait for a proportional time based on message length

        // Find appropriate response
        // $response = 'This is a response to: ' . $lastMessage['text'];
        [$response, $imageToSend] = ChatResponseService::generateResponse($lastMessage, $this->recipient, $this->messages);

        // Simulate typing for propportianal time based on reply message length
        $waitForResponse = 0.3 * strlen($response);
        if ($waitForResponse > 20) {
            $waitForResponse = 20; // Cap the wait time to 20 seconds
        }
        // sleep($waitForResponse);


        // Debug
        // $waitForRead = 1;
        // $waitForResponse = 1;
        // End debug

        

        return [
            'user' => $this->recipient,
            'text' => $response,
            'imageToSend' => $imageToSend ?? null,
            'waitForRead' => $waitForRead,
            'waitForResponse' => $waitForResponse,
        ];
    }

    public function addMessage(string $user, string $text)
    {
        $image = null;

        if (strpos($text, '.jpg') !== false || strpos($text, '.png') !== false) { // This is an image message
            // Only the file name is used, so the image always comes from the recipient's own folder
            $image = asset('images/' . rawurlencode($this->recipient) . '/' . rawurlencode(basename($text)));
            $text = '';
        }

        $this->messages[] = [
            'text' => $text,
            'image' => $image,
            'user' => $user,
            'created_at' => now(),
        ];
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
