<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;

class Chat extends Component
{
    #[Url]
    public string $user = ''; // The user sending the message

    #[Url]
    public string $recipient = ''; // The recipient of the messages who is being spoken to and simulated

    public string $recipientInitials = ''; // Initials of the recipient for display purposes

    public array $messages = [];

    public string $newMessage = '';

    public function mount()
    {
        if ($this->user == '') {
            $this->user = 'User' . rand(1, 100); // Assign a random user name for demonstration
        }
        
        $this->messages = []; // Initialize messages array

        if ($this->recipient == '') {
            $this->recipient = 'Recipient'; // Set a default recipient
        }

        // Find recipient initials
        $recipientParts = explode(' ', $this->recipient);
        $this->recipientInitials = '';
        foreach ($recipientParts as $part) {
            $this->recipientInitials .= strtoupper($part[0]); // Get initials of recipient
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
        $response = 'This is a response to: ' . $lastMessage['text'];

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

        $withImg = strpos($lastMessage['text'], 'pic') !== false; //rand(0, 1) === 1; // Randomly decide if the response includes an image, Later replace this with logic to determine if an image should be included
        if ($withImg) {
            $numberOfAvailableImagesFromThisRecipient = count(scandir(public_path() . "/images/" . $this->recipient)) - 2;

            if ($numberOfAvailableImagesFromThisRecipient > 0) {
                $randomImageIndex = rand(0, $numberOfAvailableImagesFromThisRecipient - 1);
                $imageFiles = array_values(
                    array_diff(
                        scandir(
                            public_path() . "/images/" . $this->recipient
                        ), ['..', '.']
                    )
                );

                $imageToSend = $imageFiles[$randomImageIndex];
            } else {
                $imageToSend = 'noImgAvailable.png'; // No images available
            }

            // Append to original reply a note that this reply includes an image
            $response .= '. Ill also send across an image with this message.';
        }

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
        if (strpos($text, '.jpg') !== false || strpos($text, '.png') !== false) { // This is an image message
            // Convert this to image message contents
            $text = '
                <img 
                    src="' . asset('images/' . $this->recipient . '/' . $text) . '" 
                    alt="Image" 
                    style="
                        max-width: 45%; height: auto; min-width: 100px; min-height: 100px; border: 1px solid #ccc; 
                        border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    "
                >
            ';
        }

        $this->messages[] = [
            'text' => $text,
            'user' => $user,
        ];
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
