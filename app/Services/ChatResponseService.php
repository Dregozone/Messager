<?php

namespace App\Services;

Class ChatResponseService
{
    /**
     * Generate a response based on the last message.
     *
     * @param array $lastMessage The last message sent by the user.
     * @return array<string> The generated response and image if applicable.
     */
    public static function generateResponse(array $lastMessage, string $recipient, array $allMessages): array
    {
        // Bring in the response data for this recipient if it exists, otherwise use the default data
        require_once public_path() . '/data/default.php'; // Load the default data file

        if (file_exists(public_path() . '/data/' . $recipient . '.php')) {
            require_once public_path() . '/data/' . $recipient . '.php'; // Load recipient-specific data if it exists

            // Merge the arrays to ensure recipient-specific responses override default ones
            $wordAliases = array_merge($wordAliases, $recipientWordAliases ?? []);
            $availableResponses = array_merge($availableResponses, $recipientAvailableResponses ?? []);
            $genericResponses = array_merge($genericResponses, $recipientGenericResponses ?? []);
        }

        $response = '';

        // Check if the last message contains any keywords
        $foundKeyword = false;
        foreach ($wordAliases as $keyword => $aliases) {
            foreach ($aliases as $alias) {
                if (stripos($lastMessage['text'], $alias) !== false) {
                    // If an alias is found, use the keyword for the response
                    $response .= $availableResponses[$keyword][array_rand($availableResponses[$keyword])] . ' ';
                    $foundKeyword = true;
                }
            }
        }

        // If no keyword was found, use a generic response
        if (! $foundKeyword) {
            $response = $genericResponses[array_rand($genericResponses)];
        }

        $alsoSendPicAliases = ['pic', 'image', 'photo', 'picture', 'img'];
        $withImg = false;
        // Check if the last message contains any alias for sending an image
        foreach ($alsoSendPicAliases as $alias) {
            if (stripos($lastMessage['text'], $alias) !== false) {
                // If an alias is found, append a note to the response
                $withImg = true;
                break;
            }
        }

        if ($withImg) {
            if (is_dir(public_path() . "/images/" . $recipient) === false) {
                // If the recipient's image directory does not exist, create it
                mkdir(public_path() . "/images/" . $recipient, 0755, true);
            }
            
            $numberOfAvailableImagesFromThisRecipient = count(scandir(public_path() . "/images/" . $recipient)) - 3; // -3 to account for '.', '..', and '.gitignore'

            if ($numberOfAvailableImagesFromThisRecipient > 0) {
                $randomImageIndex = rand(0, $numberOfAvailableImagesFromThisRecipient - 1);
                $imageFiles = array_values(
                    array_diff(
                        scandir(
                            public_path() . "/images/" . $recipient
                        ), ['..', '.', '.gitignore']
                    )
                );

                $imageToSend = $imageFiles[$randomImageIndex];

                // Append to original reply a note that this reply includes an image
                $response .= '. Ill also send across an image with this message.';
            } else {
                $imageToSend = NULL; //'noImgAvailable.png'; // No images available

                // Append to original reply a note that this reply includes an image
                $response .= '. I dont have any pics to send.';
            }
        }

        // Simulate a response generation process
        // In a real application, this could involve calling an AI service or processing logic
        return [$response, $imageToSend ?? null];
    }
}
