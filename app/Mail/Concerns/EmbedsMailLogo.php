<?php

namespace App\Mail\Concerns;

use Symfony\Component\Mime\Part\DataPart;

/**
 * Embeds the platform logo as an inline image with a fixed Content-ID.
 * The part is added without a filename so it is not shown as an attachment.
 */
trait EmbedsMailLogo
{
    protected const LOGO_CID = 'logo@partyplanner';

    public function build(): void
    {
        $logoPath = public_path('images/logo.png');
        if (! file_exists($logoPath)) {
            return;
        }

        $this->withSymfonyMessage(function ($message) use ($logoPath) {
            // fromPath(path, name, contentType) - name=null so no filename in header (avoids "attachment" display)
            $part = DataPart::fromPath($logoPath, null, 'image/png');
            $part->asInline();
            $part->setContentId(self::LOGO_CID);
            $message->addPart($part);
        });
    }
}
