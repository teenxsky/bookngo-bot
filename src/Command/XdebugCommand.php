<?php

declare(strict_types=1);

namespace App\Command;

use Exception;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @psalm-suppress UnusedClass
 */
#[AsCommand(
    name: 'app:xdebug',
    description: 'Xdebug management: enable, disable and check status',
)]
class XdebugCommand extends Command
{
    private const SOURCE_PATH    = __DIR__ . '/../../.docker/php/xdebug.ini';
    private const TARGET_PATH    = '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini';
    private const VALID_COMMANDS = ['enable', 'disable', 'status'];

    #[Override]
    public function configure(): void
    {
        $this
            ->addArgument(
                'command_name',
                InputArgument::REQUIRED,
                'Command (enable, disable, status)'
            );
    }

    #[Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $command = $input->getArgument('command_name');

        if (!in_array($command, self::VALID_COMMANDS, true)) {
            $io->error(sprintf(
                'Unknown command. Available commands: %s',
                implode(', ', self::VALID_COMMANDS)
            ));
            return Command::FAILURE;
        }

        try {
            match ($command) {
                'enable'  => $this->enableXdebug($io),
                'disable' => $this->disableXdebug($io),
                'status'  => $this->showStatus($io),
            };

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function enableXdebug(SymfonyStyle $io): void
    {
        if (!copy(self::SOURCE_PATH, self::TARGET_PATH)) {
            throw new RuntimeException('Failed to enable Xdebug');
        }
        $io->success('Xdebug successfully enabled');
    }

    private function disableXdebug(SymfonyStyle $io): void
    {
        if (file_exists(self::TARGET_PATH) && !unlink(self::TARGET_PATH)) {
            throw new RuntimeException('Failed to disable Xdebug');
        }
        $io->success('Xdebug successfully disabled');
    }

    private function showStatus(SymfonyStyle $io): void
    {
        $enabled = file_exists(self::TARGET_PATH);
        $status  = $enabled ? 'enabled' : 'disabled';
        $io->info(sprintf('Xdebug is %s', $status));
    }
}
