<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\Replay;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Without a Reverb server the Public View follows by polling /state (T2), so each step can take a
| few seconds to show; the plugin retries assertions until its timeout.
*/

it('replays a final podium from Live control on the Public View, after the event closed, and goes back (D-11)', function (): void {
    $event = Pentahoot::event(attributes: ['status' => EventStatus::Finished]);
    Replay::pentahoot($event);
    Replay::kata($event);

    $screen = visit('/year-end-party/screen')->resize(1920, 1080)->assertDontSee('Final results');

    actingAs($event->owner()->firstOrFail());
    $host = visit("/host/{$event->id}")->assertSee('Replay on screen');
    $openLists = 'document.querySelectorAll("#live-replay ol:not([style*=none])").length';
    $pick = <<<'JS'
        document.querySelector("#live-replay [role=combobox]").click();
        [...document.querySelectorAll("#live-replay [role=option]")].find((o) => o.innerText.includes("2. Word Round")).click();
        JS;

    // Nothing is picked at first, so the Monitor column stays short (D-11).
    $host->assertSee('Pick a game to see the moments you can show again.')->assertScript($openLists, 0);

    $host->script($pick);
    $host->assertScript($openLists, 1)->assertDontSee('Pick a game to see the moments you can show again.');
    // Its final podium closes the moments of the game.
    $host->script('[...document.querySelectorAll("#live-replay ol:not([style*=none]) li")].find((li) => li.innerText.includes("Final podium")).querySelector("[data-test=replay-show]").click()');
    $host->assertSee('Showing: 2. Word Round · Final podium');

    $screen->assertSee('Final results')->assertSee('Rita Wulandari')->assertNoJavaScriptErrors();

    $host->script('[...document.querySelectorAll("#live-replay button")].find((b) => b.innerText.trim() === "Back to live").click()');
    $host->assertDontSee('Showing:');

    $screen->assertDontSee('Final results')->assertNoJavaScriptErrors();

    // Picking the same game again empties the card (D-11).
    $host->assertScript($openLists, 1);
    $host->script($pick);
    $host->assertScript($openLists, 0)->assertSee('Pick a game to see the moments you can show again.');
    $host->assertNoJavaScriptErrors();
});
