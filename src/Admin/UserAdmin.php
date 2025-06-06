<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\User;
use App\Service\UsersService;
use Exception;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractAdmin<User>
 * @psalm-suppress UnusedClass
 */
final class UserAdmin extends AbstractAdmin
{
    private TranslatorInterface $translator;
    private UserPasswordHasherInterface $passwordHasher;
    private Security $security;
    private UsersService $usersService;

    public function __construct(
        TranslatorInterface $translator,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        UsersService $usersService
    ) {
        parent::__construct();
        $this->translator     = $translator;
        $this->passwordHasher = $passwordHasher;
        $this->security       = $security;
        $this->usersService   = $usersService;
    }

    #[Override]
    public function configureBatchActions(array $actions): array
    {
        unset($actions['delete']);
        return $actions;
    }

    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        if (str_contains($this->getRequest()->getUri(), 'edit')) {
            $form->with('edit_title', ['label' => 'admin.users.edit_title']);
        } else {
            $form->with('create_title', ['label' => 'admin.users.create_title']);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        /** @var User|null $subject */
        $subject = $this->getSubject();

        $isAdmin       = $this->usersService->isAdmin($subject);
        $isAnotherUser = $currentUser->getId() !== $subject->getId();
        $isSameUser    = $currentUser->getId() === $subject->getId();

        $disabled = $isAdmin && $isAnotherUser;

        $form
            ->add('phoneNumber', Type\TextType::class, [
                'label'       => 'admin.users.phone_number',
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^\+(?:[0-9]{1,3})(?:[0-9]{7,14})$/',
                        message: $this->translator->trans(
                            'admin.constraints.phone_number.format'
                        )
                    ),
                    new Assert\Length(
                        min: 7,
                        max: 15,
                        minMessage: $this->translator->trans(
                            'admin.constraints.phone_number.min_length'
                        ),
                        maxMessage: $this->translator->trans(
                            'admin.constraints.phone_number.max_length'
                        )
                    ),
                ],
                'disabled' => $disabled
            ]);

        if ($isSameUser) {
            $form->add('roles', Type\ChoiceType::class, [
                'label'   => 'admin.users.roles',
                'choices' => [
                    'admin.users.role.user'  => 'ROLE_USER',
                    'admin.users.role.admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'disabled' => true,
                'help'     => $this->translator->trans('admin.users.cannot_change_own_roles'),
            ]);
        } else {
            $form->add('roles', Type\ChoiceType::class, [
                'label'   => 'admin.users.roles',
                'choices' => [
                    'admin.users.role.user'  => 'ROLE_USER',
                    'admin.users.role.admin' => 'ROLE_ADMIN',
                ],
                'multiple'    => true,
                'expanded'    => true,
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: $this->translator->trans(
                            'admin.constraints.roles.min_count'
                        ),
                    ),
                ],
                'disabled' => $disabled
            ]);
        }

        $form
            ->add('password', Type\PasswordType::class, [
                'label'    => 'admin.users.password',
                'required' => false,
                'mapped'   => false,
                'disabled' => $disabled
            ])
            ->add('telegramUsername', Type\TextType::class, [
                'label'       => 'admin.users.telegram_username',
                'required'    => false,
                'constraints' => [
                    new Assert\Length(
                        max: 255,
                        maxMessage: $this->translator->trans(
                            'admin.constraints.telegram_username.max_length'
                        ),
                    ),
                ],
                'disabled' => $disabled
            ])
            ->add('telegramUserId', Type\IntegerType::class, [
                'label'    => 'admin.users.telegram_user_id',
                'required' => false,
                'attr'     => [
                    'min' => 1,
                ],
                'disabled' => $disabled
            ])
            ->add('telegramChatId', Type\IntegerType::class, [
                'label'    => 'admin.users.telegram_chat_id',
                'required' => false,
                'attr'     => [
                    'min' => 1,
                ],
                'disabled' => $disabled
            ])
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('roles', null, [
                'template' => 'admin/user/list_roles.html.twig',
                'label'    => 'admin.users.roles',
                'sortable' => false,
            ])
            ->add('phoneNumber', Type\TextType::class, [
                'label'    => 'admin.users.phone_number',
                'sortable' => false,
            ])
            ->add('telegramUsername', Type\TextType::class, [
                'label'    => 'admin.users.telegram_username',
                'sortable' => false,
            ])
            ->add('bookings', null, [
                'label'    => 'admin.users.user_bookings',
                'template' => 'admin/user/list_bookings.html.twig'
            ])
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, [
                'actions' => [
                    'show' => [],
                    'edit' => [
                        'template' => 'admin/user/list_action_edit.html.twig'
                    ],
                    'delete' => [
                        'template' => 'admin/user/list_action_delete.html.twig'
                    ]
                ],
                'label' => 'admin.general.actions'
            ]);
    }

    #[Override]
    public function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('show_title', ['label' => 'admin.users.show_title'])
            ->add('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('roles', null, [
                        'label'    => 'admin.users.roles',
                        'template' => 'admin/user/show_roles.html.twig',
                    ])
            ->add('phoneNumber', Type\TextType::class, ['label' => 'admin.users.phone_number'])
            ->add('telegramUsername', Type\TextType::class, ['label' => 'admin.users.telegram_username'])
            ->add('telegramChatId', Type\IntegerType::class, ['label' => 'admin.users.telegram_chat_id'])
            ->add('telegramUserId', Type\IntegerType::class, ['label' => 'admin.users.telegram_user_id'])
            ->add('bookings', null, [
                        'label'               => 'admin.users.user_bookings',
                        'template'            => 'admin/user/show_bookings.html.twig',
                        'associated_property' => 'id'
                    ])
            ->add('tokenVersion', Type\IntegerType::class, ['label' => 'admin.users.token_version'])
            ->end();
    }

    #[Override]
    public function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {
        $buttonList = parent::configureActionButtons($buttonList, $action, $object);

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        /** @var User|null $subject */
        try {
            $subject = $this->getSubject();
        } catch (Exception) {
            $subject = null;
        }

        if ($currentUser === null || $subject === null) {
            return $buttonList;
        }

        if ($this->usersService->isAdmin($object) && !($currentUser->getId() === $subject->getId())) {
            unset($buttonList['edit']);
        }

        return $buttonList;
    }

    #[Override]
    public function prePersist(object $object): void
    {
        if (!$object instanceof User) {
            return;
        }

        $this->updatePassword($object);
    }

    #[Override]
    public function preUpdate(object $object): void
    {
        if (!$object instanceof User) {
            return;
        }

        $this->updatePassword($object);
        $this->preventRoleChangeForCurrentUser($object);
    }

    #[Override]
    public function preRemove(object $object): void
    {
        if (!$object instanceof User) {
            return;
        }

        $this->preventAdminDeletion($object);
    }

    private function updatePassword(User $user): void
    {
        $form = $this->getForm();

        if (!$form->has('password')) {
            return;
        }

        $passwordField = $form->get('password');
        $plainPassword = $passwordField->getData();

        if ($plainPassword !== null && $plainPassword !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }
    }

    private function preventAdminDeletion(object $object): void
    {
        if ($object instanceof User && $this->usersService->isAdmin($object)) {
            throw new AccessDeniedException($this->translator->trans('admin.users.cannot_delete_admin'));
        }
    }

    private function preventRoleChangeForCurrentUser(User $user): void
    {
        $currentUser = $this->security->getUser();

        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $originalUser = $this->getModelManager()->find(User::class, $user->getId());
            if ($originalUser instanceof User) {
                $user->setRoles($originalUser->getRoles());
            }
        }
    }
}
