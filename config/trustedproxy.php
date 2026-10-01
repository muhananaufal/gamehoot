<?php

declare(strict_types=1);

return [

    /*
    | O7, F11: the reverse proxies in front of the app (the host's Caddy or nginx), comma separated
    | IPs or CIDR ranges. Only requests from them may set X-Forwarded-For and -Proto, so rate
    | limits see each phone's address and signed media URLs use https. Empty trusts no one.
    */
    'proxies' => env('TRUSTED_PROXIES'),

];
