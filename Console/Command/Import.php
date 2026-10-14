<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zero\Blog\Model\Import\Importer;
use Zero\Blog\Model\Import\SourceInterface;

/**
 * bin/magento zero:blog:import <wordpress|magefan|amasty>
 *
 *   --file=export.xml      WordPress: the export file
 *   --old-pattern=PATH     old post address for redirects, {key} = URL key
 *                          (Magefan: blog/post/{key}, Amasty: blog/{key}.html)
 *   --no-images            don't download featured images from other sites
 *   --no-redirects         don't add redirects from the old addresses
 *   --dry-run              show what would be imported, change nothing
 */
class Import extends Command
{
    /**
     * @param array<string, SourceInterface> $sources
     */
    public function __construct(
        private readonly Importer $importer,
        private readonly State $state,
        private readonly array $sources = []
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('zero:blog:import')
            ->setDescription('Imports posts into Zero Blog from WordPress, Magefan Blog or Amasty Blog')
            ->addArgument('source', InputArgument::REQUIRED, implode(', ', array_keys($this->sources)))
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'WordPress export (WXR) file')
            ->addOption('old-pattern', null, InputOption::VALUE_REQUIRED, 'Old post path for redirects, {key} = URL key')
            ->addOption('no-images', null, InputOption::VALUE_NONE, 'Do not download featured images')
            ->addOption('no-redirects', null, InputOption::VALUE_NONE, 'Do not add redirects from old addresses')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('source');
        $source = $this->sources[$name] ?? null;
        if ($source === null) {
            $output->writeln('<error>Unknown source. Use one of: ' . implode(', ', array_keys($this->sources)) . '</error>');

            return Command::FAILURE;
        }
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // already set
        }
        try {
            $options = ['file' => (string) $input->getOption('file')];
            if ($input->getOption('old-pattern') !== null) {
                $options['old_pattern'] = trim((string) $input->getOption('old-pattern'), '/');
            }
            $data = $source->read($options);
            $output->writeln(sprintf(
                'Found %d posts, %d categories, %d tags, %d authors.',
                count($data['posts']),
                count($data['categories']),
                count($data['tags']),
                count($data['authors'])
            ));
            $stats = $this->importer->import($data, [
                'dry_run' => (bool) $input->getOption('dry-run'),
                'images' => !$input->getOption('no-images'),
                'redirects' => !$input->getOption('no-redirects'),
            ], $output->isVerbose() || $input->getOption('dry-run') ? static fn (string $line) => $output->writeln('  ' . $line) : null);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            '<info>%s %d posts (%d already there), %d authors, %d categories, %d tags, %d redirects.</info>',
            $input->getOption('dry-run') ? 'Would import' : 'Imported',
            $stats['posts'],
            $stats['skipped'],
            $stats['authors'],
            $stats['categories'],
            $stats['tags'],
            $stats['redirects']
        ));
        if (!$input->getOption('dry-run') && $stats['posts']) {
            $output->writeln('Refresh the page cache: bin/magento cache:clean full_page');
        }

        return Command::SUCCESS;
    }
}
