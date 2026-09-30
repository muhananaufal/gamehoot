<?php

declare(strict_types=1);

// Pentahoot screens: phone, Public View and Live control. __N__ is filled in by JavaScript.
return [
    'question_of' => 'Question __N__ of __TOTAL__',
    'seconds' => 'sec',
    'get_ready' => 'Get ready for the next question',
    'next_soon' => 'The next question is coming',

    // Phone (E3, E4, E4b, F9)
    'search_label' => 'Find the name you pick',
    'search_placeholder' => 'Type a name',
    'no_match' => 'No name matches. Check the spelling.',
    'loading_names' => 'Loading names…',
    'send' => 'Send my answer',
    'sending' => 'Sending…',
    'sent' => 'Answer sent',
    'your_pick' => 'Your pick',
    'locked' => 'Your answer is locked. Wait for the host to show the results.',
    'time_up' => 'Time is up',
    'time_up_body' => 'Voting for this question has closed.',
    'waiting_results' => 'Waiting for the host to show the results',
    'results_of' => 'Results of question __N__',
    'ties_note' => 'Names with the same votes share a rank.',
    'your_pick_was' => 'Your pick: :name',
    'network_error' => 'Could not send. Check your connection and try again.',

    // Public View (E2, E4, E15, E17)
    'answer_on_phone' => 'Pick your answer on your phone',
    'answered_of' => '__N__ / __TOTAL__ answered',
    'votes_total' => ['one' => '__N__ vote', 'other' => '__N__ votes'],
    'no_votes' => 'No votes',
    'votes' => 'votes',
    // E20: one row standing for a large tie on the projector.
    'tied_names' => ['one' => '__N__ name tied', 'other' => '__N__ names tied'],
    'game_over' => 'Thanks for playing!',
    'game_over_host' => 'This game is finished. Start the next one from the Games page.',

    // Live control (D-5, D-8, E13, E15, C-3)
    'no_game' => 'No game is running.',
    'no_game_help' => 'Start a game from the Games page.',
    'open_games' => 'Open Games',
    'questions' => 'Questions',
    'status' => [
        'ready' => 'Ready',
        'live' => 'Live',
        'closed' => 'Time up',
        'revealed' => 'Revealed',
        'done' => 'Done',
    ],
    'no_question' => 'Pick a question to open.',
    'open' => 'Open',
    'start' => 'Start',
    'stop' => 'Stop',
    'reveal' => 'Reveal',
    'next' => 'Next question',
    'reset' => 'Reset votes',
    'reset_title' => 'Reset this question?',
    'reset_help' => 'Deletes every vote of this question and logs it. Players vote again when you start it.',
    'danger' => 'Actions that cannot be undone',
    'all_answered' => 'Everyone answered (__N__/__TOTAL__). Press Stop when ready.',
    'tally' => 'Live count',
    'tally_note' => 'Only hosts see this',
    'tally_empty' => 'No votes yet.',
    'finish' => 'Finish game',
];
