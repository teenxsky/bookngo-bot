<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UsersRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public const USERS_FIELDS = [
        'id',
        'phone_number',
        'password',
        'roles',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_username'
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function addUser(User $user): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($user);
        $entityManager->flush();
    }

    /**
     * @param User $updatedUser
     * @return void
     */
    public function updateUser(User $updatedUser): void
    {
        $entityManager = $this->getEntityManager();

        /** @var User|null $user */
        $user = $this->find($updatedUser->getId());
        if ($user) {
            ($user)
                ->setPhoneNumber($updatedUser->getPhoneNumber())
                ->setPassword($updatedUser->getPassword())
                ->setTokenVersion($updatedUser->getTokenVersion())
                ->setRoles($updatedUser->getRoles());

            $entityManager->flush();
        }
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
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
                keys: self::USERS_FIELDS,
                values: $data
            );

            $user = (new User())
                ->setId(
                    (int) $row['id']
                )
                ->setPhoneNumber(
                    (string) $row['phone_number']
                )
                ->setPassword(
                    (string) $row['password']
                )
                ->setRoles(
                    explode(',', $row['roles'])
                )
                ->setTelegramChatId(
                    (int) $row['telegram_chat_id']
                )
                ->setTelegramUserId(
                    (int) $row['telegram_user_id']
                )
                ->setTelegramUsername(
                    (string) $row['telegram_username']
                );

            $this->addUser($user);
        }

        fclose($handle);
    }
}
