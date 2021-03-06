<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Publisher\BookApiInterface;
use App\Domain\Publisher\OpenLibraryMapper;
use Exception;
use GuzzleHttp\Client;

/**
 * Doc : https://openlibrary.org/dev/docs/api/books
 * Class OpenLibraryAdapter.
 */
class OpenLibraryAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    protected $api;

    protected $mapper;

    protected $client;

    public function __construct()
    {
        $this->mapper = new OpenLibraryMapper();
        $this->client = new Client(['timeout' => 5,]);
    }

    /**
     * @param string $isbn
     *
     * @return array|null
     * @throws Exception
     */
    public function getDataByIsbn(string $isbn): ?array
    {
        $isbn = str_replace([' ', '-'], '', $isbn);

        $url = 'https://openlibrary.org/api/books?'.http_build_query(
                [
                    'bibkeys' => sprintf('ISBN:%s', urlencode($isbn)),
                    'format' => 'json',
                    'jscmd' => 'details', // 'data' or 'details'
                ]
            );

        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode());
        }
        $json = $response->getBody()->getContents();

        if (empty($json)) {
            return null;
        }

        $allData = json_decode($json, true) ?? null;
        // Warning : response structure is different with jscmd = data/details
        $detailData = $allData[array_key_first($allData)]['details'] ?? null;

        return $detailData;
    }
}
