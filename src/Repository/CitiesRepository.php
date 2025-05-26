<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

class CitiesRepository extends ServiceEntityRepository
{
    private const CITY_FIELDS = [
        'id',
        'name',
        'country_id',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return self::CITY_FIELDS;
    }

    /**
     * @return City[]
     */
    public function findAllCities(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $countryId
     * @return City[]
     */
    public function findCitiesByCountryId(int $countryId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.country', 'country')
            ->addSelect('country')
            ->where('country.id = :countryId')
            ->setParameter('countryId', $countryId)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     * @return object|null
     */
    public function findCityById(int $id): ?City
    {
        return $this->find($id);
    }

    /**
     * @param City $city
     * @return void
     */
    public function addCity(City $city): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($city);
        $entityManager->flush();
    }

    /**
     * @param City $updatedCity
     * @return void
     */
    public function updateCity(City $updatedCity): void
    {
        $entityManager = $this->getEntityManager();

        /** @var City|null $city */
        $city = $this->find($updatedCity->getId());
        if ($city) {
            $city->setName($updatedCity->getName());
            $city->setCountry($updatedCity->getCountry());
            $entityManager->flush();
        }
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteCityById(int $id): void
    {
        $entityManager = $this->getEntityManager();
        $city          = $this->find($id);

        if ($city) {
            $entityManager->remove($city);
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
        if (!$handle) {
            throw new RuntimeException("Unable to open the CSV file: $filePath");
        }

        // Skip the first line (header row)
        fgetcsv($handle, 0, ',', '"', '\\');

        while (true) {
            $data = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$data) {
                break;
            }

            $row = array_combine(
                keys: self::CITY_FIELDS,
                values: $data
            );

            $country = $this->getEntityManager()
                ->getRepository(Country::class)
                ->find((int) $row['country_id']);
            if (!$country) {
                continue;
            }

            $city = (new City())
                ->setName((string) $row['name'])
                ->setCountry($country);

            $this->addCity($city);
        }

        fclose($handle);
    }
}
