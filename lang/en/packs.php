<?php

declare(strict_types=1);

return [
    'title' => 'Question packs',
    'new_pack' => 'New pack',
    'pack_title' => 'Pack title',
    'game_type' => 'Game',
    'create' => 'Create pack',
    'pack_label' => ':type pack',
    'copy_note' => 'Events keep their own copy of these questions. Editing the pack never changes results that were already played.',
    'empty' => 'No packs yet. Create one to start writing questions.',
    'pick_pack' => 'Pick a pack on the left, or create a new one.',
    'no_questions' => 'No questions yet.',
    'add_question' => 'Add question',
    'edit_question' => 'Edit question :number',
    'new_question' => 'New question',
    'save_question' => 'Save question',
    'delete_question' => 'Delete question',
    'move_up' => 'Move question :number up',
    'move_down' => 'Move question :number down',
    'rename' => 'Rename',
    'delete_pack' => 'Delete pack',
    'delete_pack_confirm' => 'The pack is removed from your list. Games already made from it keep their questions.',
    'points' => 'Points',
    'points_hint' => 'A bonus question can give 2 points; a warm-up can give 0.',
    'points_count' => '{0} 0 pts|{1} 1 pt|[2,*] :count pts',
    'seconds' => ':count s',

    'pentahoot' => [
        'prompt' => 'Category question',
        'prompt_hint' => 'Players pick one name from the event\'s name list. Everyone can vote, including for themselves.',
        'duration' => 'Time to vote',
        'duration_hint' => 'Any value from 5 to 300 seconds. Default 20.',
        'duration_seconds' => 'Seconds',
    ],

    'kata' => [
        'prompt' => 'Question',
        'answer' => 'Answer',
        'answer_hint' => 'Letters and numbers become boxes. Punctuation like - \' . / & is shown automatically. Max 40 characters.',
        'boxes' => 'Boxes shown from the start',
        'boxes_hint' => 'Select a box to show it from the start. At least one box must stay hidden.',
        'boxes_count' => ':open of :total open',
        'boxes_invalid' => 'Type an answer with letters or digits to see its boxes.',
        'box_label' => 'Box :number, letter :char',
    ],

    'created' => 'Pack created.',
    'saved' => 'Pack saved.',
    'deleted' => ':title deleted.',
    'question_added' => 'Question added.',
    'question_saved' => 'Question saved.',
    'question_deleted' => 'Question deleted.',
    'order_mismatch' => 'The order must list every question of this pack once.',
];
