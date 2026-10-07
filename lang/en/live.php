<?php

declare(strict_types=1);

// D-10: Live control, from preparing the event to closing it.
return [
    'steps_title' => 'Steps',
    'control_title' => 'Control',
    'monitor_title' => 'Monitor',
    'steps' => [
        'prepare' => 'Prepare',
        'open' => 'Open event',
        'close' => 'Close event',
    ],
    'phase' => [
        'draft' => 'Draft',
        'open' => 'Open',
        'playing' => 'Playing',
        'all_played' => 'All games played',
        'finished' => 'Finished',
    ],
    'game_status' => [
        'finished' => 'Finished',
        'running' => 'Running',
        'next' => 'Next',
        'waiting' => 'Waiting',
    ],
    'names' => '{0} No names|{1} 1 name|[2,*] :count names',
    'games' => '{0} no games|{1} 1 game|[2,*] :count games',
    'get_ready' => 'Get ready',
    'get_ready_help' => 'Players can join once the event is open. Names and games can still change after that.',
    'open_names' => 'Names',
    'open_games' => 'Games',
    'next_game' => 'Next game',
    'next_game_help' => 'Games run in their order on the Games page.',
    'start_out_of_order' => 'Start “:title” now?',
    'start_out_of_order_help' => '“:title” starts before “:next”. The order on the Games page stays as it is.',
    'no_games' => 'No games yet',
    'no_games_help' => 'Add a game from a question pack on the Games page.',
    'add_game' => 'Add a game',
    'last_game' => 'On screen: :title',
    'up_next' => 'Up next: :title',
    'more_actions' => 'More actions',
    'all_played' => 'All games played',
    'all_played_help' => ':done of :total games finished. Close the event when you are done, or add another game.',
    'event_closed' => 'Event closed',
    'event_closed_help' => 'Players see “This event has ended”. Results stay available.',
    'results' => 'See results',
    'preview' => 'Public View preview',
    'preview_note' => 'What the projector shows right now.',
    'copy' => 'Copy',
    'copied' => 'Copied',
];
