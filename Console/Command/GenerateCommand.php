<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Console\Command;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Model\FeedGenerator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI: bin/magento dlabsit:google-feed:generate [--store=<id|code>] [--all]
 */
class GenerateCommand extends Command
{
    private const OPT_STORE = 'store';
    private const OPT_ALL = 'all';

    public function __construct(
        private readonly FeedGenerator $generator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly FileDriver $fileDriver,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dlabsit:google-feed:generate')
            ->setDescription('Generate the Google Merchant (Google Shopping) product feed')
            ->addOption(self::OPT_STORE, null, InputOption::VALUE_OPTIONAL, 'Store id or code (default: all enabled stores)')
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Generate for every store where the feed is enabled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Exception $e) {
            // Area already set — fine.
        }

        $storeOpt = $input->getOption(self::OPT_STORE);
        try {
            $stores = $this->resolveStores($storeOpt);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        if ($stores === []) {
            $output->writeln('<comment>No matching stores. Enable the feed under Dlabsit → Google Feed.</comment>');
            return Command::SUCCESS;
        }

        $failures = 0;
        foreach ($stores as $storeId) {
            $forced = $storeOpt !== null;
            if (!$forced && !$this->config->isEnabled($storeId)) {
                continue;
            }
            $failures += $this->runOne($storeId, $output) ? 0 : 1;
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return int[] store ids
     */
    private function resolveStores(?string $storeOpt): array
    {
        if ($storeOpt === null) {
            $ids = [];
            foreach ($this->storeManager->getStores() as $store) {
                $ids[] = (int) $store->getId();
            }
            return $ids;
        }

        // Accept either a numeric id or a store code.
        foreach ($this->storeManager->getStores() as $store) {
            if ((string) $store->getId() === $storeOpt || $store->getCode() === $storeOpt) {
                return [(int) $store->getId()];
            }
        }
        throw new \InvalidArgumentException("No store found for '{$storeOpt}'.");
    }

    private function runOne(int $storeId, OutputInterface $output): bool
    {
        $code = $this->storeManager->getStore($storeId)->getCode();
        $output->writeln("<info>Generating Google feed for store '{$code}' (id {$storeId})…</info>");
        $start = microtime(true);
        try {
            $result = $this->generator->generate($storeId);
            $elapsed = round(microtime(true) - $start, 2);
            $size = $this->formatBytes($this->fileSize($result->absolutePath));
            $output->writeln("  <info>✓</info> {$result->itemCount} items, {$size}, {$elapsed}s");
            $output->writeln("    file: {$result->absolutePath}");
            $output->writeln("    url:  {$result->publicUrl}");
            return true;
        } catch (\Exception $e) {
            $output->writeln("  <error>✗ {$e->getMessage()}</error>");
            return false;
        }
    }

    private function fileSize(string $path): int
    {
        try {
            $stat = $this->fileDriver->stat($path);
            return (int) ($stat['size'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
