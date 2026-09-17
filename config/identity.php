<?php

// Tunables for the Identity module (docs section 25: access token short-lived,
// refresh token revocable). Exact values are not specified by the baseline
// doc (not one of the enumerated OPEN QUESTIONs in section 44 either) — these
// are implementation defaults and can be tuned without a schema change.
return [
    'access_token_ttl_minutes' => (int) env('IDENTITY_ACCESS_TOKEN_TTL_MINUTES', 60),

    'refresh_token_ttl_days' => (int) env('IDENTITY_REFRESH_TOKEN_TTL_DAYS', 30),
];
