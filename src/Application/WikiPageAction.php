<?php

namespace App\Application;

use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Page;

class WikiPageAction
{
    /**
     * @var Page
     */
    protected $page;

    /**
     * WikiPageAction constructor.
     *
     * @param MediawikiFactory $wiki
     * @param string           $title
     */
    public function __construct(MediawikiFactory $wiki, string $title)
    {
        $this->page = $wiki->newPageGetter()->getFromTitle($title);
    }

    /**
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->page->getRevisions()->getLatest()->getContent()->getData(
        );
    }

    /**
     * Extract <ref> data from text
     *
     * @param $text string
     *
     * @return array
     * @throws \Exception
     */
    public function extractRefFromText(string $text): ?array
    {
        $parser = new TagParser(); // todo ParserFactory
        $refs = $parser->importHtml($text)->getRefValues(); // []

        return (array)$refs;
    }

    /**
     * TODO $url parameter
     * check if any ref contains a targeted website/URL
     *
     * @param array $refs
     *
     * @return array
     */
    public function filterRefByURL(array $refs): array
    {
        $validRef = [];
        foreach ($refs as $ref) {
            if (preg_match(
                    '#(?<url>https?:\/\/(?:www\.)?lemonde\.fr\/[^ \]]+)#i',
                    $ref,
                    $matches
                ) > 0
            ) {
                $validRef[] = ['url' => $matches['url'], 'raw' => $ref];
            }
        }

        return $validRef;
    }

}