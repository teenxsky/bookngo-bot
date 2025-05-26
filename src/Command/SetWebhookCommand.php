<?php

declare(strict_types=1);

namespace App\Command;

use Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelegramBot\Api\BotApi;

/**
 * @psalm-suppress UnusedClass
 */
#[AsCommand(
    name: 'app:set-webhook',
    description: 'Sets webhook for Telegram bot'
)]
class SetWebhookCommand extends Command
{
    #[Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $telegram = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
            $result   = $telegram->setWebhook($_ENV['TELEGRAM_WEBHOOK_URL']);

            if ($result) {
                $io->success('Webhook successfully set');
                return Command::SUCCESS;
            }

            $io->error('Failed to set webhook');
            return Command::FAILURE;
        } catch (Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
