<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\House;
use App\Entity\User;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

class BookingsRepository extends ServiceEntityRepository
{
    private const BOOKING_FIELDS = [
        'id',
        'user_id',
        'house_id',
        'comment',
        'start_date',
        'end_date',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return self::BOOKING_FIELDS;
    }

    /**
     * @return Booking[]
     */
    public function findAllBookings(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBookingById(int $id): ?Booking
    {
        return $this->find($id);
    }

    /**
     * @param int $userId
     * @param mixed $isActual
     * @return array
     */
    public function findBookingsByUserId(int $userId, ?bool $isActual = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId);

        if ($isActual !== null) {
            $now = new DateTime();
            if ($isActual) {
                $qb->andWhere('b.startDate >= :now');
            } else {
                $qb->andWhere('b.startDate < :now');
            }
            $qb->setParameter('now', $now);
        }

        return $qb->getQuery()->getResult();
    }

    public function addBooking(Booking $booking): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($booking);
        $entityManager->flush();
    }

    public function updateBooking(Booking $updatedBooking): void
    {
        $entityManager = $this->getEntityManager();

        /** @var Booking|null $booking */
        $booking = $this->find($updatedBooking->getId());
        if ($booking) {
            ($booking)
                ->setUser($updatedBooking->getUser())
                ->setComment($updatedBooking->getComment())
                ->setHouse($updatedBooking->getHouse())
                ->setStartDate($updatedBooking->getStartDate())
                ->setEndDate($updatedBooking->getEndDate());

            $entityManager->flush();
        }
    }

    public function deleteBookingById(int $id): void
    {
        $entityManager = $this->getEntityManager();
        $booking       = $this->find($id);

        if ($booking) {
            $entityManager->remove($booking);
            $entityManager->flush();
        }
    }

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
                keys: self::BOOKING_FIELDS,
                values: $data
            );

            $house = $this->getEntityManager()
                ->getRepository(House::class)
                ->find((int) $row['house_id']);

            $user = $this->getEntityManager()
                ->getRepository(User::class)
                ->find((int) $row['user_id']);

            if (!$house || !$row['start_date'] || !$row['end_date']) {
                continue;
            }

            $booking = (new Booking())
                ->setId((int) $row['id'])
                ->setUser($user)
                ->setHouse($house)
                ->setComment((string) $row['comment'])
                ->setStartDate(new DateTimeImmutable($row['start_date']))
                ->setEndDate(new DateTimeImmutable($row['end_date']));

            $this->addBooking($booking);
        }

        fclose($handle);
    }
}
