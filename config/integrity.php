<?php

// Tunables for docs section 19 (multi-layer location integrity). Several of
// these correspond directly to docs section 44 OPEN QUESTIONs that were
// never answered (Q5 GPS accuracy threshold, Q6 capture session time
// window, Q7 mock-location policy) — defaults below, tune via env without
// a redeploy.
return [
    // Section 44 Q7: BLOCK rejects the location submission outright;
    // FLAGGED accepts it but marks the location SUSPICIOUS for admin
    // review. FLAGGED is the default — V1 has no field data yet on false
    // positive rates, and losing a field visit's evidence outright over a
    // possible false positive is a worse failure mode than flagging it.
    'mock_location_policy' => env('INTEGRITY_MOCK_LOCATION_POLICY', 'FLAGGED'),

    // Section 44 Q5. Worse (larger) than this and the location is marked
    // SUSPICIOUS for low confidence (docs section 19.4).
    'max_acceptable_accuracy_meters' => (float) env('INTEGRITY_MAX_ACCEPTABLE_ACCURACY_METERS', 50),

    // Section 19.5 impossible-travel check: the speed implied by two
    // consecutive locations for the same user. 150 km/h comfortably covers
    // any plausible ground transport in the field, so it only catches
    // genuinely impossible jumps.
    'max_plausible_speed_kmh' => (float) env('INTEGRITY_MAX_PLAUSIBLE_SPEED_KMH', 150),

    // Section 44 Q6 / section 19.6's own suggested baseline (30-60s)
    // between a capture session's location and photo.
    'capture_session_max_window_seconds' => (int) env('INTEGRITY_CAPTURE_SESSION_WINDOW_SECONDS', 60),
];
