<?php

declare(strict_types=1);

return [
    'types' => [
        'pentahoot' => 'Pentahoot',
        'tebak_kata' => 'Word Guess',
        'tebak_gambar' => 'Picture Guess',
    ],

    'kata' => [
        'answer_unsupported' => 'The answer may only contain letters, digits, spaces and the punctuation - \' . / &, with at least one letter or digit.',
        'open_index_out_of_range' => 'Opened boxes must be numbered from 0 to :max.',
        'all_boxes_open' => 'At least one box must stay closed.',
    ],

    // Shared by Tebak Kata and Tebak Gambar.
    'tebak' => [
        'winner_unknown' => 'Pick a name from this event\'s list.',
    ],

    // D-9, T8: the games of an event.
    'title' => 'Games',
    'subtitle' => 'Games are played in this order. Each one is a copy of a question pack.',
    'pack' => 'question pack',
    'add' => 'Add game',
    'add_from_pack' => 'Add a game from a pack',
    'choose_pack' => 'Question pack',
    'no_packs' => 'You have no Pentahoot packs yet. Write one under Question packs first.',
    'empty' => 'No games yet. Add one from a question pack.',
    'questions' => '{0} No questions|{1} 1 question|[2,*] :count questions',
    'added' => 'Game added. Its questions are copies of the pack.',
    'deleted' => 'Game deleted.',
    'reloaded' => 'Questions reloaded from the pack.',
    'finished' => 'Game finished.',
    'questions_title' => 'Questions of :title',
    'questions_link' => 'Questions',
    'questions_help' => 'These are copies of the pack. Edit one until it has been on screen; the pack is not changed.',
    'question_saved' => 'Question saved. The pack is not changed.',
    'edit_question' => 'Edit question :number',
    'locked' => 'On screen already',
    'save_question' => 'Save question',
    'reload' => 'Reload from pack',
    'reload_help' => 'Replaces the questions with the latest version of the pack.',
    'delete' => 'Delete',
    'delete_confirm' => 'Delete “:title”? Its copied questions are removed too.',
    'start' => 'Start game',
    'finish' => 'Finish game',
    'finish_confirm' => 'Finish “:title”? Questions that are not done yet will not count.',
    'status' => [
        'waiting' => 'Not started',
        'running' => 'Running',
        'finished' => 'Finished',
    ],
    'playable_later' => 'Word Guess and Picture Guess games arrive in later releases.',
];
