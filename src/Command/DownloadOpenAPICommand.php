<?php

namespace App\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

class DownloadOpenAPICommand extends Command
{
    public static string $tempFolder = __DIR__ . '/../../var/temp/ebay';
    protected static $defaultName = 'ebay:download-openapi';
    protected static $defaultDescription = 'Download OpenAPI file from eBay\'s website';

    private array $openAPIFiles = [
        'sell-account-v1' => 'https://developer.ebay.com/api-docs/master/sell/account/openapi/3/sell_account_v1_oas3.json',
        'sell-account-v2' => 'https://developer.ebay.com/api-docs/master/sell/account/v2/openapi/3/sell_account_v2_oas3.json',
        'sell-analytics' => 'https://developer.ebay.com/api-docs/master/sell/analytics/openapi/3/sell_analytics_v1_oas3.json',
        'sell-compliance' => 'https://developer.ebay.com/api-docs/master/sell/compliance/openapi/3/sell_compliance_v1_oas3.json',
        'sell-feed' => 'https://developer.ebay.com/api-docs/master/sell/feed/openapi/3/sell_feed_v1_oas3.json',
        'sell-finances' => 'https://developer.ebay.com/api-docs/master/sell/finances/openapi/3/sell_finances_v1_oas3.json',
        'sell-fulfillment' => 'https://developer.ebay.com/api-docs/master/sell/fulfillment/openapi/3/sell_fulfillment_v1_oas3.json',
        'sell-inventory' => 'https://developer.ebay.com/api-docs/master/sell/inventory/openapi/3/sell_inventory_v1_oas3.json',
        'sell-logistics' => 'https://developer.ebay.com/api-docs/master/sell/logistics/openapi/3/sell_logistics_v1_oas3.json',
        'sell-marketing' => 'https://developer.ebay.com/api-docs/master/sell/marketing/openapi/3/sell_marketing_v1_oas3.json',
        'sell-metadata' => 'https://developer.ebay.com/api-docs/master/sell/metadata/openapi/3/sell_metadata_v1_oas3.json',
        'sell-negotiation' => 'https://developer.ebay.com/api-docs/master/sell/negotiation/openapi/3/sell_negotiation_v1_oas3.json',
        'sell-recommendation' => 'https://developer.ebay.com/api-docs/master/sell/recommendation/openapi/3/sell_recommendation_v1_oas3.json',
        'buy-browse' => 'https://developer.ebay.com/api-docs/master/buy/browse/openapi/3/buy_browse_v1_oas3.json',
        'buy-deal' => 'https://developer.ebay.com/api-docs/master/buy/deal/openapi/3/buy_deal_v1_oas3.json',
        'buy-feed' => 'https://developer.ebay.com/api-docs/master/buy/feed/v1/openapi/3/buy_feed_v1_oas3.json',
        'buy-marketing' => 'https://developer.ebay.com/api-docs/master/buy/marketing/openapi/3/buy_marketing_v1_beta_oas3.json',
        'buy-marketplace-insights' => 'https://developer.ebay.com/api-docs/master/buy/marketplace-insights/openapi/3/buy_marketplace_insights_v1_beta_oas3.json',
        'buy-offer' => 'https://developer.ebay.com/api-docs/master/buy/offer/openapi/3/buy_offer_v1_beta_oas3.json',
        'buy-order' => 'https://developer.ebay.com/api-docs/master/buy/order/openapi/3/buy_order_v2_oas3.json',
        'commerce-catalog' => 'https://developer.ebay.com/api-docs/master/commerce/catalog/openapi/3/commerce_catalog_v1_beta_oas3.json',
        'commerce-charity' => 'https://developer.ebay.com/api-docs/master/commerce/charity/openapi/3/commerce_charity_v1_oas3.json',
        'commerce-identity' => 'https://developer.ebay.com/api-docs/master/commerce/identity/openapi/3/commerce_identity_v1_oas3.json',
        // commerce-media is not stable yet, buggy swagger file
//        'commerce-media' => 'https://developer.ebay.com/api-docs/master/commerce/media/openapi/2/commerce_media_v1_beta_oas2.json',
        'commerce-notification' => 'https://developer.ebay.com/api-docs/master/commerce/notification/openapi/3/commerce_notification_v1_oas3.json',
        'commerce-taxonomy' => 'https://developer.ebay.com/api-docs/master/commerce/taxonomy/openapi/3/commerce_taxonomy_v1_oas3.json',
        'commerce-translation' => 'https://developer.ebay.com/api-docs/master/commerce/translation/openapi/3/commerce_translation_v1_beta_oas3.json',
        'developer-anlaytics' => 'https://developer.ebay.com/api-docs/master/developer/analytics/openapi/3/developer_analytics_v1_beta_oas3.json',
    ];

    protected function configure()
    {
        $this->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Specify a particular module to download')
            ->addUsage(sprintf('Download OpenAPI files from https://developer.ebay.com/develop/apis. Available modules are: %s .',
                implode(' , ', array_keys($this->openAPIFiles))));
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        if (!$fs->exists(static::$tempFolder)) {
            $fs->mkdir(static::$tempFolder);
        }

        $io->note(sprintf('Downloading to %s', static::$tempFolder));
        if ($input->getOption('module')) {
            if (!array_key_exists($input->getOption('module'), $this->openAPIFiles)) {
                throw new InvalidArgumentException(sprintf('Unknown module %s, available modules are %s',
                    $input->getOption('module'),
                    implode(' , ', array_keys($this->openAPIFiles))));
            }
            $files = [$input->getOption('module') => $this->openAPIFiles[$input->getOption('module')]];
        } else {
            $files = $this->openAPIFiles;
        }
        foreach ($files as $module => $uri) {
            if (!$this->download($io, $module, $uri)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function download(StyleInterface $io, $module, $uri): bool
    {
        $fs = new Filesystem();

        $io->note(sprintf('%s => %s', $module, $uri));

        $client   = HttpClient::create();
        $progress = null;
        $response = $client->request('GET', $uri, [
            'on_progress' => function (int $dlNow, int $dlSize) use ($io, &$progress): void {
                if ($dlNow > 0 && is_null($progress)) {
                    $progress = $io->createProgressBar($dlSize);
                    $progress->start();
                }

                if (!is_null($progress)) {
                    if ($dlNow === $dlSize) {
                        $progress->finish();

                        return;
                    }
                    $progress->setProgress($dlNow);
                }
            }
        ]);

        if (200 != $response->getStatusCode()) {
            $io->warning('Download failed');

            return false;
        }
        $fs->dumpFile(static::$tempFolder . '/' . $module . '.json', $response->getContent());
        $io->success('Download finished!');

        return true;
    }

}
