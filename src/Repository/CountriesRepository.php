<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

class CountriesRepository extends ServiceEntityRepository
{
    private const COUNTRY_FIELDS = [
        'id',
        'name'
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return self::COUNTRY_FIELDS;
    }

    /**
     * @return Country[]
     */
    public function findAllCountries(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     * @return ?Country
     */
    public function findCountryById(int $id): ?Country
    {
        return $this->find($id);
    }

    /**
     * @param Country $country
     * @return void
     */
    public function addCountry(Country $country): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($country);
        $entityManager->flush();
    }

    /**
     * @param Country $updatedCountry
     * @return void
     */
    public function updateCountry(Country $updatedCountry): void
    {
        $entityManager = $this->getEntityManager();

        /** @var Country|null $country */
        $country = $this->find($updatedCountry->getId());
        if ($country) {
            $country->setName($updatedCountry->getName());
            $entityManager->flush();
        }
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteCountryById(int $id): void
    {
        $entityManager = $this->getEntityManager();
        $country       = $this->find($id);

        if ($country) {
            $entityManager->remove($country);
            $entityManager->flush();
        }
    }

    /**
     * @param string $filePath
     * @throws RuntimeException
     * @return void
     */
    public function loadFromCsv(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open the CSV file: $filePath");
        }

        fgetcsv($handle, 0, ',', '"', '\\');

        while (true) {
            $data = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$data) {
                break;
            }

            $row = array_combine(
                keys: self::COUNTRY_FIELDS,
                values: $data
            );

            $country = new Country();
            $country->setName($row['name']);

            $this->addCountry($country);
        }

        fclose($handle);
    }
}
