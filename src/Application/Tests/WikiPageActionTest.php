<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\WikiPageAction;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\Test\Integration\TestEnvironment;
use PHPUnit\Framework\TestCase;

class WikiPageActionTest extends TestCase
{
    public function testReplaceTemplateInText()
    {
        $text = 'zzzzzzz {{Ouvrage|titre=bla}} zzzz {{en}} {{Ouvrage|titre=bla}} zerqsdfqs';
        $origin = '{{Ouvrage|titre=bla}}';
        $replace = '{{Ouvrage|lang=en|titre=BLO}}';

        $this::assertSame(
            'zzzzzzz {{Ouvrage|lang=en|titre=BLO}} zzzz {{Ouvrage|lang=en|titre=BLO}} zerqsdfqs',
            WikiPageAction::replaceTemplateInText($text, $origin, $replace)
        );
    }

//    public function testIntegration()
//    {
//        // Mediawiki namespace not PSR-4
//        require_once __DIR__.'/../../../vendor/addwiki/mediawiki-api-base/tests/Integration/TestEnvironment.php';
//        putenv('ADDWIKI_MW_API=http://localhost:8888/api.php');
//
////        $api = MediawikiApi::newFromPage( TestEnvironment::newInstance()->getPageUrl() );
////        $this::assertInstanceOf( 'Mediawiki\Api\MediawikiApi', $api );
//        $api = TestEnvironment::newInstance()->getApi();
//        $page = new WikiPageAction(new MediawikiFactory($api), 'test');
//        $this::assertInstanceOf('App\Application\WikiPageAction', $page);
////        dump($page);
//    }
}
