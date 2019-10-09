<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infrastructure\CorpusAdapter;
use PHPUnit\Framework\TestCase;

class PredictFromTypoTest extends TestCase
{
    /**
     * For TDD.
     *
     * @dataProvider patternProvider
     *
     * @param string $author
     * @param string $pattern
     */
    public function testTokenizeAuthor(string $author, string $pattern)
    {
        $predict = new PredictFromTypo();
        $this::assertEquals(
            $pattern,
            $predict->typoPatternFromAuthor($author)
        );
    }

    public function patternProvider()
    {
        return [
            ['Paul Penaud', 'FIRSTUPPER FIRSTUPPER'],
            ['Jean-Pierre Penaud', 'MIXED FIRSTUPPER'],
            ['J. Penaud', 'INITIAL FIRSTUPPER'],
            ['A. B. Penaud', 'INITIAL FIRSTUPPER'],
        ];
    }

    /**
     * @dataProvider authorProvider
     */
    public function testPredictNameFirstName($author, $expected)
    {
        $predict = new PredictFromTypo(new CorpusAdapter());
        $this::assertEquals(
            $expected,
            $predict->predictNameFirstName($author)
        );
    }

    public function authorProvider()
    {
        return [
            ['Pierre Penaud', ['firstname' => 'Pierre', 'name' => 'Penaud']],
            ['Jean-Pierre Penaud', ['firstname' => 'Jean-Pierre', 'name' => 'Penaud']],
            ['J. Penaud', ['firstname' => 'J.', 'name' => 'Penaud']],
            ['Penaud, J.', ['firstname' => 'J.', 'name' => 'Penaud']],
            ['A. Durand', ['firstname' => 'A.', 'name' => 'Durand']],
            ['A. B. Durand', ['firstname' => 'A. B.', 'name' => 'Durand']],
            ['Pierre Durand, Paul Marchal', ['fail' => '2+ authors in string']],
            ['Babar Elephant', ['fail' => 'firstname not in corpus']],
        ];
    }

    public function testWithStorageCorpus()
    {
        $corpus = new CorpusAdapter();
        $corpus->setCorpusInStorage('firstname', ['fubar', 'dada']);
        $predict = new PredictFromTypo($corpus);
        $this::assertEquals(
            ['firstname' => 'Fubar', 'name' => 'Penaud'],
            $predict->predictNameFirstName('Fubar Penaud')
        );
    }
}
