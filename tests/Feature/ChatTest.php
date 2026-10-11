<?php

use App\Livewire\Chat;
use App\Services\ChatResponseService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('renders the chat page for a recipient from the URL', function () {
    $this->get('/?recipient=Alice')->assertOk()->assertSee('Alice')->assertSeeLivewire(Chat::class);

    Livewire::withQueryParams(['recipient' => 'Person 2'])->test(Chat::class)
        ->assertSet('recipient', 'Person 2')
        ->assertSet('recipientInitials', 'P2');
});

it('sends a message and gets a simulated reply', function () {
    $component = Livewire::test(Chat::class)
        ->call('selectRecipient', 'Bob')
        ->assertSet('recipientInitials', 'B')
        ->set('newMessage', 'Hello, how are you today?')
        ->call('sendMessage')
        ->assertSet('newMessage', '')
        ->assertCount('messages', 1);

    $reply = $component->instance()->awaitResponse();
    expect($reply)->toBeArray()->and($reply['text'])->not->toBeEmpty()->and($reply['user'])->toBe('Bob');
});

it('ignores a recipient that is not on the list', function (string $recipient) {
    Livewire::withQueryParams(['recipient' => $recipient])->test(Chat::class)->assertSet('recipient', '');

    Livewire::test(Chat::class)->call('selectRecipient', $recipient)->assertSet('recipient', '');

    $this->get('/?recipient=' . urlencode($recipient))->assertOk();
})->with(['../../vendor/autoload', '../data/default', 'Mallory', 'x"><script>alert(1)</script>']);

it('does not let the browser set the recipient directly', function () {
    Livewire::test(Chat::class)->set('recipient', '../../vendor/autoload');
})->throws(CannotUpdateLockedPropertyException::class);

it('never loads a data file from outside the data folder', function () {
    $outside = storage_path('framework/testing/outside.php');
    @mkdir(dirname($outside), 0755, true);
    file_put_contents($outside, '<?php $GLOBALS["outsideFileLoaded"] = true;');

    try {
        [$response] = ChatResponseService::generateResponse(['text' => 'hello'], '../../storage/framework/testing/outside', []);

        expect($GLOBALS['outsideFileLoaded'] ?? false)->toBeFalse()->and($response)->not->toBeEmpty();
    } finally {
        @unlink($outside);
    }
});

it('shows typed messages as text, not HTML', function () {
    Livewire::test(Chat::class)
        ->call('selectRecipient', 'Alice')
        ->set('newMessage', '<script>alert(1)</script>')
        ->call('sendMessage')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('only shows images from the recipient\'s own folder', function () {
    $component = Livewire::test(Chat::class)
        ->call('selectRecipient', 'Person 2')
        ->call('addMessage', 'Person 2', '../../secret/x"><script>alert(1)</script>.png');

    $image = $component->get('messages')[0]['image'];

    expect($image)->toStartWith(asset('images/Person%202/'))->not->toContain('..')->not->toContain('<');
    $component->assertDontSeeHtml('<script>alert(1)</script>');
});
