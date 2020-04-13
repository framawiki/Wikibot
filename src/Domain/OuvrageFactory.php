<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Infrastructure\BnfAdapter;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

/**
 * todo rename OuvrageImport/Gateway ?
 * Class OuvrageFactory
 *
 * @package App\Domain
 */
class OuvrageFactory
{
    private function __construct()
    {
    }

    public static function OpenLibraryFromIsbn(string $isbn): ?OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new OpenLibraryAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setSource('OL');

        return $ouvrage;
    }

    public static function GoogleFromIsbn(string $isbn): OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new GoogleBooksAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setSource('GB');

        return $ouvrage;
    }

    public static function BnfFromIsbn(string $isbn): OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new BnfAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setSource('BnF');

        return $ouvrage;
    }

    public static function OptimizedFromData(array $data): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $ouvrage->hydrate($data);
        $proc = new OuvrageOptimize($ouvrage);
        $proc->doTasks();

        return $proc->getOuvrage();
    }
}
