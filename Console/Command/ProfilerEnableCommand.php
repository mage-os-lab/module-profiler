<?php
/**
 * Copyright © Mage-OS. All rights reserved.
 * See LICENSE.txt / LICENSE_AFL.txt for license details.
 */
declare(strict_types=1);

namespace MageOS\Profiler\Console\Command;

use Magento\Developer\Console\Command\ProfilerEnableCommand as CoreProfilerEnableCommand;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Io\File;
use MageOS\Profiler\Model\Profiler\Output\Tabular;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Teaches core's `dev:profiler:enable` about the output types this module registers.
 *
 * Core writes var/profiler.flag for any type it is given, but warns that anything outside
 * html/csvfile is "not one of the built-in output types" - which is misleading once this module is
 * installed - and prints no hints for them. Both configure() and execute() are protected, so a
 * plugin cannot reach either; the module replaces the `dev_profiler_enable` item in
 * Magento\Framework\Console\CommandList instead (see etc/di.xml), keeping the command name.
 *
 * Anything this module does not answer to falls straight through to the parent implementation.
 */
class ProfilerEnableCommand extends CoreProfilerEnableCommand
{
    /**
     * Output types and drivers registered by this module's bootstrap.php.
     */
    public const MODULE_TYPES = ['tabular', 'json', 'timeline'];

    /**
     * @var File
     */
    private $file;

    /**
     * @param File $file
     */
    public function __construct(File $file)
    {
        parent::__construct($file);
        $this->file = $file;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = (string)($input->getArgument('type') ?: '');

        if (!$this->isModuleType($type)) {
            return parent::execute($input, $output);
        }

        $flagFile = BP . '/' . self::PROFILER_FLAG_FILE;
        $this->file->write($flagFile, $type);

        if (!$this->file->fileExists($flagFile)) {
            $output->writeln('<error>Something went wrong while enabling the profiler.</error>');

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf('<info>' . self::SUCCESS_MESSAGE . '</info>', $type));

        if (in_array('tabular', $this->splitTypes($type), true)) {
            $output->writeln(sprintf('<info>Log file: %s%s</info>', BP, Tabular::DEFAULT_FILEPATH));
        }

        $output->writeln('');
        $output->writeln('<comment>Per-request activation without the flag file:</comment>');
        $output->writeln('  CLI : MAGE_PROFILER=' . $type . ' bin/magento <command>');
        $output->writeln('  API : send a "MAGE_PROFILER=' . $type . '" cookie');
        $output->writeln('        (non-developer mode also needs MAGE_PROFILER=' . $type . ':$MAGE_PROFILER_SECRET)');
        $output->writeln('');
        $output->writeln('<comment>Optional env overrides:</comment>');
        $output->writeln('  MAGE_PROFILER_LOG, MAGE_PROFILER_MIN_MS, MAGE_PROFILER_FILTER');
        $output->writeln('  MAGE_PROFILER_CLI_STDERR');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Whether every type in the value is one this module registers.
     *
     * bootstrap.php accepts them combined - `tabular,json` - and hands the whole value back to stock
     * Magento the moment one name is unknown, so the same all-or-nothing rule applies here.
     *
     * @param string $type
     * @return bool
     */
    private function isModuleType(string $type): bool
    {
        $names = $this->splitTypes($type);
        if (!$names) {
            return false;
        }

        return !array_diff($names, self::MODULE_TYPES);
    }

    /**
     * @param string $type
     * @return string[]
     */
    private function splitTypes(string $type): array
    {
        return array_values(array_filter(array_map(
            static fn (string $name): string => strtolower(trim($name)),
            explode(',', $type)
        )));
    }
}
