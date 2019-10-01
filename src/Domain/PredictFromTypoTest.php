<?php

namespace App\Domain;

use PHPUnit\Framework\TestCase;

class PredictFromTypoTest extends TestCase
{

    /**
     * For TDD
     * @dataProvider patternProvider
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

    public function patternProvider(){
        return [
            ['Paul Penaud', 'FIRSTUPPER FIRSTUPPER'],
            ['Jean-Pierre Penaud', 'MIXED FIRSTUPPER'],
            ['J. Penaud', 'INITIAL FIRSTUPPER'],
            ['A. B. Penaud', 'INITIAL INITIAL FIRSTUPPER'],
        ];
    }

    /**
     * @dataProvider authorProvider
     */
    public function testPredictNameFirstName($author, $expected)
    {
        $predict = new PredictFromTypo();
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
            ['A. B. Durand', ['firstname' => 'A. B.', 'name' => 'Durand']],
        ];
    }
}