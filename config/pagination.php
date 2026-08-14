<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default page size
    |--------------------------------------------------------------------------
    |
    | The number of items per page for EVERY paginated list in the app — both
    | the mobile API lists and the admin web tables. Clients cannot override it
    | (the API ignores any ?per_page= they send); change it here / via env.
    |
    */

    'per_page' => (int) env('PAGINATION_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Place-search page size
    |--------------------------------------------------------------------------
    |
    | Place search pages separately from the lists above so the web results
    | grid always loads full rows — keep it even (the desktop grid is two
    | cards per row), otherwise the last row ends with a dangling card.
    |
    */

    'search_per_page' => (int) env('PAGINATION_SEARCH_PER_PAGE', 6),
];
