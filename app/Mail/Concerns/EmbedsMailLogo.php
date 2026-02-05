<?php

namespace App\Mail\Concerns;

/**
 * Placeholder for mail logo. Header displays "Party Planner" as text instead of an image.
 * Kept for compatibility with existing mail classes; no image is embedded.
 */
trait EmbedsMailLogo
{
    public function build(): void
    {
        // Logo disabled: header uses "Party Planner" text only
    }
}
