<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\House;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

class HousesRepository extends ServiceEntityRepository
{
    private const HOUSE_FIELDS = [
        'id',
        'city_id',
        'address',
        'bedrooms_count',
        'price_per_night',
        'has_air_conditioning',
        'has_wifi',
        'has_kitchen',
        'has_parking',
        'has_sea_view',
        'image_url'
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, House::class);
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return self::HOUSE_FIELDS;
    }

    /**
     * @return House[]
     */
    public function findAllHouses(): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     * @return ?House
     */
    public function findHouseById(int $id): ?House
    {
        return $this->find($id);
    }

    /**
     * @param House $house
     * @return void
     */
    public function addHouse(House $house): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($house);
        $entityManager->flush();
    }

    /**
     * @param House $updatedHouse
     * @return void
     */
    public function updateHouse(House $updatedHouse): void
    {
        $entityManager = $this->getEntityManager();

        /** @var House|null $house */
        $house = $this->find($updatedHouse->getId());
        if ($house) {
            ($house)
                ->setBedroomsCount($updatedHouse->getBedroomsCount())
                ->setPricePerNight($updatedHouse->getPricePerNight())
                ->setHasAirConditioning($updatedHouse->hasAirConditioning())
                ->setHasWifi($updatedHouse->hasWifi())
                ->setHasKitchen($updatedHouse->hasKitchen())
                ->setHasParking($updatedHouse->hasParking())
                ->setHasSeaView($updatedHouse->hasSeaView())
                ->setAddress($updatedHouse->getAddress())
                ->setCity($updatedHouse->getCity())
                ->setImageUrl($updatedHouse->getImageUrl());

            $entityManager->flush();
        }
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteHouseById(int $id): void
    {
        $entityManager = $this->getEntityManager();
        $house         = $this->find($id);

        if ($house) {
            $entityManager->remove($house);
            $entityManager->flush();
        }
    }

    /**
     * @param mixed $cityId
     * @param DateTimeImmutable $startDate
     * @param DateTimeImmutable $endDate
     * @return House[]
     */
    public function findAvailableHouses(
        ?int $cityId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        $qb = $this->createQueryBuilder('h')
            ->leftJoin('h.bookings', 'b')
            ->andWhere('(b.id IS NULL OR b.startDate > :endDate OR b.endDate < :startDate)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($cityId) {
            $qb->andWhere('h.city = :cityId')
                ->setParameter('cityId', $cityId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param House $house
     * @return bool
     */
    public function checkHouseAvailability(House $house): bool
    {
        $now = new DateTimeImmutable();

        $qb = $this->createQueryBuilder('h')
            ->select('COUNT(b.id)')
            ->leftJoin('h.bookings', 'b')
            ->where('h.id = :houseId')
            ->andWhere('b.startDate <= :currentDate')
            ->andWhere('b.endDate >= :currentDate')
            ->setParameter('houseId', $house->getId())
            ->setParameter('currentDate', $now);

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
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

        fgetcsv($handle, 0, ',', '"', '\\');

        while (true) {
            $data = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$data) {
                break;
            }

            $row = array_combine(
                keys: self::HOUSE_FIELDS,
                values: $data
            );

            $city = $this->getEntityManager()
                ->getRepository(City::class)
                ->find((int) $row['city_id']);

            if (!$city) {
                continue;
            }

            $house = (new House())
                ->setId((int) $row['id'])
                ->setAddress((string) $row['address'])
                ->setBedroomsCount((int) $row['bedrooms_count'])
                ->setPricePerNight((int) $row['price_per_night'])
                ->setHasAirConditioning((bool) $row['has_air_conditioning'])
                ->setHasWifi((bool) $row['has_wifi'])
                ->setHasKitchen((bool) $row['has_kitchen'])
                ->setHasParking((bool) $row['has_parking'])
                ->setHasSeaView((bool) $row['has_sea_view'])
                ->setImageUrl((string) $row['image_url'])
                ->setCity($city);

            $this->addHouse($house);
        }

        fclose($handle);
    }
}
