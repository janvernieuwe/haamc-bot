<?php

namespace App\Error;

use App\Message\RewatchNomination;

/**
 * Class RewatchErrorDm
 * @package App\Error
 */
class RewatchErrorDm extends AbstractErrorDm
{
    private $message = <<<EOF
Hoi %s,

Helaas is je nominatie (%s) voor de rewatch niet geldig omdat:

%s

Hierbij nog eens de regels waar een nominatie aan moet voldoen:

* Nominatie is enkel een link naar de MAL pagina van de anime
* De serie moet mnimaal 10 en maximaal 13 afleveringen bevatten
* Er zijn maximaal 10 nominaties
* Geen hentai :smirk:
* De serie moet minstens 2 jaar oud zijn (eind datum)

Je nominatie is hierdoor verwijderd of geflagged, maar we zien graag een nieuwe (geldige) nominatie van je!
EOF;

    /**
     * @param RewatchNomination $nomination
     */
    public function send(RewatchNomination $nomination): void
    {
        $errors = $this->validator->validate($nomination);
        // No errors
        if (!count($errors)) {
            return;
        }
        // Already messaged
        if ($nomination->hasReaction('❌')) {
            return;
        }
        $errors = $this->parseErrors($errors);
        $errors = '* '.implode(PHP_EOL.'* ', $errors);
        $message = sprintf(
            $this->message,
            $nomination->getAuthor(),
            $nomination->getAnime()->title,
            $errors
        );
        $this->sendDM($nomination, $message);
    }
}
