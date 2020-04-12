<?php

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Application\PublisherAction;
use App\Domain\Publisher\WebMapper;
use App\Infrastructure\Logger;
use Exception;
use PHPUnit\Framework\TestCase;

class WebMapperTest extends TestCase
{

    /**
     * @dataProvider provideMappingFromFile
     *
     * @param       $filename
     * @param array $expected
     *
     * @return void
     * @throws Exception
     */
    public function testMappingProcess($filename, array $expected): void
    {
        $html = file_get_contents($filename);

        $publiAction = new PublisherAction('bla');
        $htmlData = $publiAction->extractWebData($html);
        $mapper = new WebMapper(new Logger());
        $data = $mapper->process($htmlData);
        if (isset($data['consulté le'])) {
            $data['consulté le'] = '11-04-2020';// unit testing date...
        }
        $this::assertSame($expected, $data);
    }

    public function provideMappingFromFile()
    {
        return [
            [
                __DIR__.'/fixture_lemonde.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Le Monde',
                    'titre' => 'Coronavirus : la Californie placée à son tour en confinement',
                    'url' => 'https://www.lemonde.fr/planete/article/2020/03/20/coronavirus-la-californie-placee-en-confinement_6033754_3244.html',
                    'date' => '20-03-2020',
                ],
            ],
            [
                __DIR__.'/fixture_journalsOpenEdition.html',
                [

                    'DATA-TYPE' => 'Open Graph/Dublin Core',
                    'DATA-ARTICLE' => true,
                    'titre' => 'Alger',
                    'url' => 'http://journals.openedition.org/encyclopedieberbere/2434',
                    'consulté le' => '11-04-2020',
                    'date' => '01-07-1986',
                    'url-access' => 'limité',
                    'périodique' => 'Encyclopédie berbère',
                    'et al.' => 'oui',
                    'auteur1' => 'Camps, G.; Leglay, M.',
                    'numéro' => '4',
                    'page' => '447–472',
                    'éditeur' => 'Éditions Peeters',
                    'issn' => '1015-7344',
                    'isbn' => '2-85744-282-3',
                ],
            ],
            [
                __DIR__.'/fixture_pubmed.html',
                [
                    'DATA-TYPE' => 'Open Graph/Dublin Core',
                    'DATA-ARTICLE' => true,
                    'site' => 'PubMed Central (PMC)',
                    'titre' => 'The Diesel Exhaust in Miners Study: A Nested Case–Control Study of Lung Cancer and Diesel Exhaust',
                    'url' => 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3369553/',
                    'consulté le' => '11-04-2020',
                    'date' => '06-06-2012',
                    'périodique' => 'JNCI Journal of the National Cancer Institute',
                    'et al.' => 'oui',
                    'auteur1' => 'Debra T. Silverman, Claudine M. Samanic',
                    'volume' => '104',
                    'numéro' => '11',
                    'page' => '855',
                    'doi' => '10.1093/jnci/djs034',
                    'pmid' => '22393209',
                ],
            ],
            [
                __DIR__.'/fixture_figaro.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Le Figaro',
                    'titre' => 'Face au Covid-19, les cliniques privées mobilisées… mais en manque de masques',
                    'url' => 'http://www.lefigaro.fr/sciences/face-au-covid-19-les-cliniques-privees-mobilisees-mais-en-manque-de-masques-20200318',
                    'date' => '18-03-2020',
                    'auteur1' => 'Marie-Cécile Renault',
                    'url-access' => 'limité',
                ],
            ],
            [
                __DIR__.'/fixture_liberation.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Libération',
                    'titre' => 'En Bretagne, le Parisiens-bashing guette',
                    'url' => 'https://www.liberation.fr/france/2020/03/20/en-bretagne-le-parisiens-bashing-guette_1782471',
                    'date' => '20-03-2020',
                    'auteur1' => 'Pierre-Henri Allain',
                    'url-access' => 'ouvert',
                ],
            ],

        ];
    }

}
