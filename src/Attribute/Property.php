<?php

namespace GenAI\Property\Attribute;

/**
 * Binds a Property class to a config group, e.g. #[Property(group: 'database')]
 * or #[Property(group: 'mail', file: 'mail.ini', prefix: 'smtp')].
 *
 * file defaults to the app's main config file, app.ini — so most config lives in
 * one place and a Property only names its group. Pass file: explicitly to split a
 * concern into its own file.
 *
 * When optional is true, a missing file or group is not an error: the class is
 * bound to whatever is present (often nothing), so its bindData() gets an empty
 * Map and falls back to its own defaults. Use it for config a framework ships
 * but the app may not provide.
 *
 * BUILD-TIME ONLY (PHP 8). Read by PropertyProcessor during compilation; never
 * loaded on the PHP 5.3 runtime (there the #[Property(...)] line is a comment).
 * Requires the genai/attribute scanner (a composer "suggest").
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Property
{
    public function __construct(
        public string $group,
        public string $file = 'app.ini',
        public ?string $prefix = null,
        public bool $optional = false
    ) {
    }
}
