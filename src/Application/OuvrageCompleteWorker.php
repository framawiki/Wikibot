<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageOptimize;
use App\Domain\Publisher\Wikidata2Ouvrage;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\Logger;
use App\Infrastructure\Memory;
use App\Infrastructure\WikidataAdapter;
use Exception;
use GuzzleHttp\Client;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Class OuvrageCompleteWorker
 *
 * @package App\Application
 */
class OuvrageCompleteWorker
{
    /**
     * Exclusion requête BnF/Google/etc
     * Format EAN ou ISBN10 sans tiret.
     */
    const ISBN_EAN_SKIP
        = [
            '9782918758440', // Profils de lignes du réseau ferré français vol.2
            '9782918758341', // Profils de lignes du réseau ferré français vol.1
        ];
    /**
     * @var QueueInterface
     */
    private $queueAdapter;
    /**
     * @var string
     */
    private $raw = '';
    private $page; // article title

    private $summaryLog = [];
    private $notCosmetic = false;
    private $major = false;
    /**
     * @var OuvrageTemplate
     */
    private $ouvrage;
    /**
     * @var LoggerInterface|NullLogger
     */
    private $log;

    public function __construct(QueueInterface $queueAdapter, ?LoggerInterface $log = null)
    {
        $this->queueAdapter = $queueAdapter;
        $this->log = $log ?? new NullLogger();
    }

    public function run(?int $limit = 10000)
    {
        $memory = new Memory();
        while ($limit > 0) {
            $limit--;
            sleep(1);
            $row = $this->getNewRow2Complete();
            $this->raw = $row['raw'];
            $this->page = $row['page'];

            echo sprintf(
                "-------------------------------\n%s [%s]\n%s\n%s\n",
                date("Y-m-d H:i:s"),
                WikiBotConfig::getGitVersion() ?? '',
                $this->page,
                $this->raw
            );

            $this->log->info($memory->getMemory(true));

            // initialise variables
            $this->summaryLog = [];
            $this->ouvrage = null;
            $this->notCosmetic = false;
            $this->major = false;


            try {
                $parse = TemplateParser::parseAllTemplateByName('ouvrage', $this->raw);
                $origin = $parse['ouvrage'][0]['model'] ?? null;
            } catch (Throwable $e) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            if (!$origin instanceof OuvrageTemplate) {
                echo sprintf("*** ERREUR impossible de transformer en modèle %s \n", $this->raw);
                continue;
            }

            // Final optimizing (with online predictions)
            $optimizer = new OuvrageOptimize($origin, $this->page, new Logger());
            $optimizer->doTasks();
            $this->ouvrage = $optimizer->getOuvrage();
            $this->summaryLog = array_merge($this->summaryLog, $optimizer->getSummaryLog());
            $this->notCosmetic = ($optimizer->notCosmetic || $this->notCosmetic);

            /**
             * RECHERCHE ONLINE
             */
            $isbn = $origin->getParam('isbn') ?? null; // avant mise en forme EAN>ISBN
            $isbn10 = $origin->getParam('isbn2') ?? $origin->getParam('isbn10') ?? null;
            if (!empty($isbn)
                && !$origin->hasParamValue('isbn invalide')
                && !$origin->hasParamValue('isbn erroné')
            ) {
                $this->onlineIsbnSearch($isbn, $isbn10);
            }

            $this->sendCompleted();
            unset($optimizer);
            unset($parse);
            unset($origin);
        } // END WHILE

        return true;
    }

    /**
     * Get array (title+raw strings) to complete from AMQP queue, SQL Select or file reading.
     *
     * @return array
     * @throws Exception
     */
    private function getNewRow2Complete(): array
    {
        $row = $this->queueAdapter->getNewRaw();
        if (empty($row) || empty($row['raw'])) {
            echo "STOP: no more queue to process \n";
            throw new Exception('no more queue to process');
        }

        return $row;
    }

    /**
     * @param string      $isbn
     * @param string|null $isbn10
     *
     * @return bool
     */
    private function isIsbnSkipped(string $isbn, ?string $isbn10 = null): bool
    {
        if (in_array(str_replace('-', '', $isbn), self::ISBN_EAN_SKIP)
            || ($isbn10 !== null
                && in_array(str_replace('-', '', $isbn10), self::ISBN_EAN_SKIP))
        ) {
            return true;
        }

        return false;
    }

    private function onlineIsbnSearch(string $isbn, ?string $isbn10 = null)
    {
        if ($this->isIsbnSkipped($isbn, $isbn10)) {
            echo "*** SKIP THAT ISBN ***\n";

            // Vérifier logique return
            return;
        }

        online:
        $this->log->info("sleep 10...\n");
        sleep(10);

        try {
            $this->log->info('BIBLIO NAT FRANCE...');
            // BnF sait pas trouver un vieux livre (10) d'après ISBN-13... FACEPALM !
            $bnfOuvrage = null;
            if ($isbn10) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn10);
                sleep(2);
            }
            if (!$isbn10 || empty($bnfOuvrage) || empty($bnfOuvrage->getParam('titre'))) {
                $bnfOuvrage = OuvrageFactory::BnfFromIsbn($isbn);
            }
            if (isset($bnfOuvrage) and $bnfOuvrage instanceof OuvrageTemplate) {
                $this->completeOuvrage($bnfOuvrage);

                // Wikidata requests from $infos (ISBN/ISNI)
                if (!empty($bnfOuvrage->getInfos())) {
                    $this->log->info('WIKIDATA...');

                    // TODO move to factory
                    $wikidataAdapter = new WikidataAdapter(
                        new Client(['timeout' => 30, 'headers' => ['User-Agent' => getenv('USER_AGENT')]])
                    );
                    $wdComplete = new Wikidata2Ouvrage($wikidataAdapter, clone $bnfOuvrage, $this->page);
                    $this->completeOuvrage($wdComplete->getOuvrage());
                }
            }
        } catch (Throwable $e) {
            echo sprintf(
                "*** ERREUR BnF Isbn Search %s %s %s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }

        if (!isset($bnfOuvrage) || !$this->skipGoogle($bnfOuvrage)) {
            try {
                $this->log->info('GOOGLE...');

                $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
                $this->completeOuvrage($googleOuvrage);
            } catch (Throwable $e) {
                echo "*** ERREUR GOOGLE Isbn Search ***".$e->getMessage()."\n";
                if (strpos($e->getMessage(), 'Could not resolve host: www.googleapis.com') === false) {
                    throw $e;
                }
                unset($e);
            }
        }

        if (!isset($bnfOuvrage) && !isset($googleOuvrage)) {
            try {
                $this->log->info('OpenLibrary...');
                $openLibraryOuvrage = OuvrageFactory::OpenLibraryFromIsbn($isbn);
                if (!empty($openLibraryOuvrage)) {
                    $this->completeOuvrage($openLibraryOuvrage);
                }
            } catch (Throwable $e) {
                echo '**** ERREUR OpenLibrary Isbn Search';
            }
        }
    }

    //    private function onlineQuerySearch(string $query)
    //    {
    //        echo "sleep 40...";
    //        sleep(20);
    //        onlineQuerySearch:
    //
    //        try {
    //            dump('GOOGLE SEARCH...');
    //            //            $googleOuvrage = OuvrageFactory::GoogleFromIsbn($isbn);
    //            $adapter = new GoogleBooksAdapter();
    //            $data = $adapter->search('blabla');
    //            dump($data);
    //            //die;
    //            //            return $import->getOuvrage();
    //            //            $this->completeOuvrage($googleOuvrage);
    //        } catch (Throwable $e) {
    //            echo "*** ERREUR GOOGLE QuerySearch *** ".$e->getMessage()."\n";
    //            echo "sleep 30min";
    //            sleep(60 * 30);
    //            echo "Wake up\n";
    //            goto onlineQuerySearch;
    //        }
    //    }

    private function completeOuvrage(OuvrageTemplate $onlineOuvrage)
    {
        $this->log->info($onlineOuvrage->serialize(true));
        $optimizer = new OuvrageOptimize($onlineOuvrage, $this->page, new Logger());
        $onlineOptimized = ($optimizer)->doTasks()->getOuvrage();

        $completer = new OuvrageComplete($this->ouvrage, $onlineOptimized, new Logger());
        $this->ouvrage = $completer->getResult();

        // todo move that optimizing in OuvrageComplete ?
        $optimizer = new OuvrageOptimize($this->ouvrage, $this->page, new Logger());
        $this->ouvrage = $optimizer->doTasks()->getOuvrage();

        $this->log->info('Summary', $completer->getSummaryLog());

        if ($completer->major) {
            $this->major = true;
        }
        $this->notCosmetic = ($completer->notCosmetic || $this->notCosmetic);
        $this->summaryLog = array_merge($this->summaryLog, $completer->getSummaryLog());
        unset($optimizer);
        unset($completer);
    }

    private function sendCompleted()
    {
        $isbn13 = $this->ouvrage->getParam('isbn') ?? null;

        $finalData = [
            //    'page' =>
            'raw' => $this->raw,
            'opti' => $this->serializeFinalOpti(),
            'optidate' => date("Y-m-d H:i:s"),
            'modifs' => mb_substr(implode(',', $this->summaryLog), 0, 250),
            'notcosmetic' => ($this->notCosmetic) ? 1 : 0,
            'major' => ($this->major) ? 1 : 0,
            'isbn' => substr($isbn13, 0, 20),
            'version' => WikiBotConfig::getGitVersion() ?? null,
        ];
        $this->log->info('finalData', $finalData);
        // Json ?
        $result = $this->queueAdapter->sendCompletedData($finalData);

        $this->log->notice($result ? 'OK DB' : 'erreur sendCompletedData()');
    }

    /**
     * Final serialization of the completed OuvrageTemplate.
     *
     * @return string
     */
    private function serializeFinalOpti(): string
    {
        //        // Améliore style compact : plus espacé
        //        if ('|' === $this->ouvrage->userSeparator) {
        //            $this->ouvrage->userSeparator = ' |';
        //        }
        $finalOpti = $this->ouvrage->serialize(true);
        $finalOpti = Normalizer::normalize($finalOpti);

        return $finalOpti;
    }

    private function skipGoogle($bnfOuvrage): bool
    {
        if ($bnfOuvrage instanceof OuvrageTemplate
            && $bnfOuvrage->hasParamValue('titre')
            && ($this->ouvrage->hasParamValue('lire en ligne')
                || $this->ouvrage->hasParamValue('présentation en ligne'))
        ) {
            return true;
        }

        return false;
    }
}
