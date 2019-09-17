<?php

namespace App\Application;

use GuzzleHttp\Client;

class PublisherAction
{
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * import source from URL with Guzzle
     *
     * @return string|null
     * @throws \Exception
     */
    public function getHTMLSource(): string
    {
        $client = new Client(
            [
                'timeout' => 5,
            ]
        );
        $response = $client->get($this->url);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('response error '.$response);
        }
        $html = $response->getBody()->getContents();

        return $html;
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">
     *
     * @param string $html
     *
     * @return mixed
     * @throws \Exception
     */
    public function extractLdJson(string $html)
    {
        $parser = new TagParser();
        $results = $parser->importHtml($html)->xpathResults(
            '//script[@type="application/ld+json"]'
        );


        foreach ($results as $result) {
            $json = trim($result);
            // filtrage empty value (todo?)
            if (strlen($json) === 0) {
                continue;
            }
            $data = json_decode($json, true);

            // filtrage : @type => BreadcrumbList (lemonde)
            if ($data['@type'] == 'BreadcrumbList') {
                continue;
            }


            return $data;
        }
        throw new \Exception('no results');
    }

}