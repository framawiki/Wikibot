<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use PHPUnit\Framework\TestCase;

class OuvrageCompleteTest extends TestCase
{
    public function testGetResult()
    {
        $origin = new OuvrageTemplate();
        $origin->hydrateFromText(
            '{{Ouvrage |id =Bonneton|nom1=Collectif | titre = Loiret : un département à l\'élégance naturelle | éditeur = Christine Bonneton | lieu = Paris | année = 2 septembre 1998 | isbn = 978-2-86253-234-9| pages totales = 319}}'
        );

        $google = new OuvrageClean();
        $google->hydrateFromText(
            '{{ouvrage|langue=fr|auteur1=Clément Borgal|titre=Loiret|année=1998|pages totales=319|isbn=9782862532349}}'
        );

        $comp = new OuvrageComplete($origin, $google);
        $this::assertEquals(
            '{{Ouvrage |id=Bonneton |nom1=Collectif |titre=Loiret : un département à l\'élégance naturelle |éditeur=Christine Bonneton |lieu=Paris |année=2 septembre 1998 |isbn=978-2-86253-234-9 |pages totales=319 |langue=fr |passage=}}',
            $comp->getResult()->serialize()
        );
    }

    /**
     * @dataProvider provideProcessSousTitre
     */
    public function testProcessSousTitre(string $originStr, string $onlineStr, string $expected)
    {
        $origin = new OuvrageTemplate();
        $origin->hydrateFromText($originStr);

        $online = new OuvrageClean();
        $online->hydrateFromText($onlineStr);

        $comp = new OuvrageComplete($origin, $online);
        $this::assertEquals(
            $expected,
            $comp->getResult()->serialize(true)
        );
    }

    public function provideProcessSousTitre()
    {
        return [
            // titres identiques mais sous-titre manquant
            [
                '{{Ouvrage|titre = Loiret Joli}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|langue=|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=|passage=}}',
            ],
            // punctuation titre différente, sous-titre manquant
            [
                '{{Ouvrage|titre = Loiret Joli !!!!}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|langue=|titre=Loiret Joli !!!!|sous-titre=un département|éditeur=|année=|pages totales=|isbn=|passage=}}',
            ],
            // sous-titre inclus dans titre original
            [
                '{{Ouvrage|titre = Loiret Joli : un département}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|langue=|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=|passage=}}',
            ],
            // sous-titre absent online
            [
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre = Loiret Joli}}',
                '{{Ouvrage|langue=|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=|passage=}}',
            ],
            // titre absent online
            [
                '{{Ouvrage|auteur1=bla|titre = Loiret Joli}}',
                '{{Ouvrage|auteur1=bla}}',
                '{{Ouvrage|langue=|auteur1=bla|titre=Loiret Joli|éditeur=|année=|pages totales=|isbn=|passage=}}',
            ],
        ];
    }
}
