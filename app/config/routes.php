<?php
/**
 * routes.php
 * Whitelist map for the front controller (index.php).
 * key = value of ?page=  |  value = module entry file (relative to app root)
 *
 * This is intentionally a flat array, not a "router class" — the project
 * has no framework, and a single lookup table is enough for 7 pages.
 * If the number of routes grows a lot (nested/module-internal actions),
 * revisit with a slightly smarter dispatcher — not needed yet.
 */

declare(strict_types=1);

return [
    'dashboard'   => 'modules/dashboard/index.php',
    'reservation' => 'modules/reservation/index.php',
    'visitor'     => 'modules/visitor/index.php',
    'documents'   => 'modules/documents/index.php',
    'retention'   => 'modules/retention/index.php',
    'legal'       => 'modules/legal/index.php',
    'contracts'   => 'modules/contracts/index.php',
];
