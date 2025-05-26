<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\HousesRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    name: 'app:load-storage-data',
    description: 'Load data from CSV files into the database',
)]
class LoadStorageDataCommand extends Command
{
    public function __construct(
        private readonly CountriesRepository $countriesRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly HousesRepository $housesRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $countriesFilePath,
        private readonly string $citiesFilePath,
        private readonly string $housesFilePath,
    ) {
        parent::__construct();
    }

    #[Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading storage data into database');

        try {
            $this->entityManager->beginTransaction();

            $io->section('Loading countries...');
            $this->countriesRepository->loadFromCsv($this->countriesFilePath);
            $io->success('Countries loaded successfully!');

            $io->section('Loading cities...');
            $this->citiesRepository->loadFromCsv($this->citiesFilePath);
            $io->success('Cities loaded successfully!');

            $io->section('Loading houses...');
            $this->housesRepository->loadFromCsv($this->housesFilePath);
            $io->success('Houses loaded successfully!');

            $this->entityManager->commit();

            return Command::SUCCESS;
        } catch (Exception $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            $io->error('Error loading data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
