<?php

declare(strict_types=1);

return [

    /*
    | F16: when on, private images are handed to nginx with X-Accel-Redirect after Laravel checked
    | the signature, so PHP workers are not held while hundreds of phones download (E14). Off in
    | local development and tests, where Laravel sends the file itself.
    */
    'accel' => (bool) env('MEDIA_ACCEL', false),

    // F16: the internal nginx location that maps to storage/app/media.
    'accel_location' => '/_media/',

    // E9: how long a signed URL for a private image stays valid, in minutes.
    'signed_minutes' => 720,

];
