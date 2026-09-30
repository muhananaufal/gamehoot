<?php

declare(strict_types=1);

// Tebak Kata screens: Public View, phone mirror (E14) and Live control. __N__ is filled in by JavaScript.
return [
    'question_of' => 'Question __N__ of __TOTAL__',
    'raise_hand' => 'Raise your hand if you know the answer',
    'answer_out_loud' => 'Answer out loud to the host',
    'next_soon' => 'The next question is coming',
    'legend_initial' => 'Open from the start',
    'legend_hint' => 'Hint',
    'winner' => 'Winner',
    'no_winner' => 'No winner',
    'points' => ['one' => '__N__ point', 'other' => '__N__ points'],
    'leaderboard' => 'Leaderboard',
    'after_question' => 'After question __N__ of __TOTAL__',
    'tie_note' => 'Same points: whoever got there first ranks higher.',
    'final' => 'Final results',
    'moved_up' => 'up __N__',
    'moved_down' => 'down __N__',
    'new_entry' => 'new',
    'watch_screen' => 'Watch the screen and answer out loud to the host.',

    // Live control (D-5, D-6, E6, E11, E12, E13)
    'questions' => 'Questions',
    'answer' => 'Answer',
    'hint_help' => 'Click a closed box to open it as a hint.',
    'open_box' => 'Open box __N__ as a hint',
    'show' => 'Show',
    'skip' => 'Skip',
    'skip_help' => 'Moves this question to the end of the queue. Once per question.',
    'pick_winner' => 'Pick winner',
    'find_winner' => 'Find the winner',
    'confirm_winner' => 'Winner: :name. This cannot be undone.',
    'confirm_winner_title' => 'Confirm the winner?',
    'surrender' => 'Surrender',
    'surrender_title' => 'Surrender this question?',
    'surrender_help' => 'Shows the answer with no winner. This cannot be undone.',
    'show_leaderboard' => 'Show leaderboard',
    'show_final' => 'Show final results',
    'final_help' => 'Freezes the final board and shows the podium. Questions not played do not count.',
    'no_question' => 'Pick a question to show.',
    'live_board' => 'Leaderboard now',
    'live_board_empty' => 'No winner yet.',
    'status' => [
        'q' => 'Queued',
        'Q' => 'Skipped',
        's' => 'On screen',
        'w' => 'Won',
        'x' => 'Surrendered',
    ],
];
