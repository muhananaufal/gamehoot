<?php

declare(strict_types=1);

// Only the messages Pentahoot words differently; Laravel's own file fills in the rest.
return [
    // K7: PHP refuses a file over upload_max_filesize before Laravel sees it.
    'uploaded' => 'The :attribute could not be uploaded. Images over 2 MB are refused; pick a smaller one.',
];
