<?php

return [

    /*
    | Private disk for uploads (papers, datasets, avatars) and generated exports. Use "local" in
    | development and an S3-compatible disk (see filesystems.php "s3") in production.
    */
    'disk' => env('RESEARCHHUB_DISK', 'local'),

    'uploads' => [
        // Per file; PHP's upload_max_filesize and post_max_size must allow it (see App\Support\UploadLimits).
        'paper_max_kb' => (int) env('PAPER_MAX_KB', 32 * 1024),
        'paper_max_count' => (int) env('PAPER_MAX_COUNT', 60),
        'dataset_max_kb' => (int) env('DATASET_MAX_KB', 32 * 1024),
        'transcript_max_kb' => (int) env('TRANSCRIPT_MAX_KB', 32 * 1024),
        'deliverable_max_kb' => (int) env('DELIVERABLE_MAX_KB', 32 * 1024),
        'avatar_max_kb' => 2048,
    ],

    'submission' => [
        // Source papers a researcher must upload before submitting (admins can change it under Platform Settings).
        'min_sources' => (int) env('MIN_SOURCES', 1),
    ],
    // Emails allowed to open /horizon outside the local environment (comma-separated).
    'horizon_admins' => env('HORIZON_ADMINS', ''),

    /*
    | What a student pays to start a project, by research type, in the major unit of the currency (e.g. cedis).
    | Administrators can change these under Admin → Platform Settings. A price of 0 makes that type free.
    */
    'payments' => [
        'currency' => env('PAYMENT_CURRENCY', 'GHS'),
        'prices' => [
            'undergraduate' => (float) env('PRICE_UNDERGRADUATE', 300),
            'mba' => (float) env('PRICE_MBA', 600),
            'mphil' => (float) env('PRICE_MPHIL', 800),
            'phd' => (float) env('PRICE_PHD', 1500),
        ],
    ],

    'rate_limits' => [
        // Title suggestion requests (the only AI feature) per researcher per hour; there is also a limit of 5 a minute.
        'title_suggestions_per_hour' => (int) env('TITLE_SUGGESTIONS_PER_HOUR', 20),
    ],

    'assets' => [
        // auto: use compiled Vite assets when public/build exists, otherwise the Tailwind CDN like the designs.
        // vite: always use Vite. cdn: always use the Tailwind Play CDN.
        'tailwind' => env('TAILWIND_ASSETS', 'auto'),
    ],

    /*
    | Images hot-linked by the design files. Run `php artisan researchhub:fetch-images` once to download
    | them into public/images; views then serve the local copies.
    */
    'images' => [
        'home_hero' => [
            'file' => 'home-hero.jpg',
            'url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBhrWAlAz5Q0ueRo_Bw_tlJB9W1AeGmdZ4utikj8SgZNQ6i0INMxBNOLX4evQbQ7D4zGIJcdRa5E-2Sk0tb0bjdk8j3tH-v_n17Gsg07U5cXQaESC_SeA2DgypGgLa81PzRfmvQhQOkofINdTZ4JO0YKbFZEZMwBd9Mv3w9bp9RBhX_cuT4A1-8GkkQznFnvBrCLTQeXZySAWyB-GbZBz999c1cuQILBagr0fUSh_xqilt4BNSXce9jj8lDrs_z5-4qFqGtfDLaYGmm',
        ],
        'wizard_reference' => [
            'file' => 'wizard-reference.jpg',
            'url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCdfLJwW2l2oYsMsnF7X-7pMbWZysBtZOcBUlPBoP810wT3Z4b4Ytgr410h1DSXzMqoLNdH1cvgVk5wP18CnbEv7BIuov8AbuqDDi01TNNfTvyToKu6-YDzASAxTqJKW4h-Y2vpt53E-28EUgc6FiCIo75xpQmaO6ix7_i8HE2ul4Ew0E4RaPy4yGmdnF_GybsJly-FhlhoSay6zRJ1jkwSQHhVwp0anHBhEUPKq8KJCTYhPsdbGM0VC2JaRrMA-0nGZwgJEqoghk9n',
        ],
    ],
];
