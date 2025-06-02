<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\UsersService;
use App\Validator\EntityValidator;
use Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @psalm-suppress UnusedClass
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates a new admin user'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly EntityValidator $entityValidator
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $phoneNumber = $io->ask('Enter phone number');
            if (!$phoneNumber) {
                $io->error('Phone number is required');
                return Command::FAILURE;
            }

            $password = $io->askHidden('Enter password');
            if (!$password) {
                $io->error('Password is required');
                return Command::FAILURE;
            }

            $user = (new User())
                ->setPhoneNumber($phoneNumber)
                ->setPassword($password);

            $validationError = $this->entityValidator->validate($user);
            if ($validationError) {
                $io->error(
                    $validationError['field'] . ': ' . ($validationError['message'] ?? '')
                );
                return Command::FAILURE;
            }

            $registrationError = $this->usersService->registerApiUser(
                phoneNumber: $user->getPhoneNumber(),
                password: $user->getPassword(),
                isAdmin: true
            );
            if ($registrationError) {
                $io->error($registrationError);
                return Command::FAILURE;
            }

            $io->success('Admin user created successfully!');
            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Error creating admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
