<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\House;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

class BookingsRepository extends ServiceEntityRepository
{
    private const BOOKING_FIELDS = [
        'id',
        'phone_number',
        'house_id',
        'comment',
        'start_date',
        'end_date',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_username',
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
     * @return Booking[]
     */
    public function findBookingsByCriteria(array $criteria, ?bool $isActual = null): array
    {
        $qb = $this->createQueryBuilder('b');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("b.$field = :$field")
                ->setParameter($field, $value);
        }

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
                ->setPhoneNumber($updatedBooking->getPhoneNumber())
                ->setComment($updatedBooking->getComment())
                ->setHouse($updatedBooking->getHouse())
                ->setStartDate($updatedBooking->getStartDate())
                ->setEndDate($updatedBooking->getEndDate())
                ->setTelegramChatId($updatedBooking->getTelegramChatId())
                ->setTelegramUserId($updatedBooking->getTelegramUserId())
                ->setTelegramUsername($updatedBooking->getTelegramUsername());

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

            if (!$house || !$row['start_date'] || !$row['end_date']) {
                continue;
            }

            $booking = (new Booking())
                ->setId((int) $row['id'])
                ->setPhoneNumber((string) $row['phone_number'])
                ->setHouse($house)
                ->setComment((string) $row['comment'])
                ->setStartDate(new DateTimeImmutable($row['start_date']))
                ->setEndDate(new DateTimeImmutable($row['end_date']))
                ->setTelegramChatId((int) $row['telegram_chat_id'])
                ->setTelegramUserId((int) $row['telegram_user_id'])
                ->setTelegramUsername((string) $row['telegram_username']);

            $this->addBooking($booking);
        }

        fclose($handle);
    }
}
