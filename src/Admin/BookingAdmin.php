<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Booking;
use App\Entity\House;
use App\Entity\User;
use DateTimeImmutable;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractAdmin<Booking>
 * @psalm-suppress UnusedClass
 */
final class BookingAdmin extends AbstractAdmin
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct();
        $this->translator = $translator;
    }

    #[Override]
    public function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
    }

    #[Override]
    public function configureBatchActions(array $actions): array
    {
        unset($actions['create']);
        return $actions;
    }

    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        $form->with('edit_title', ['label' => 'admin.bookings.edit_title']);

        $form
            ->add('house', EntityType::class, [
                'class'        => House::class,
                'choice_label' => 'address',
                'label'        => 'admin.bookings.house',
                'disabled'     => true,
            ]);

        /** @var Booking|null $subject */
        $subject = $this->getSubject();

        if ($subject->getUser()->getPhoneNumber() !== null) {
            $choiceLabel = 'phoneNumber';
        } else {
            $choiceLabel = 'telegramUsername';
        }

        $form
            ->add('user', EntityType::class, [
                'class'        => User::class,
                'choice_label' => $choiceLabel,
                'label'        => 'admin.bookings.user',
                'disabled'     => true,
                'help'         => $this->translator->trans('admin.bookings.user_readonly_help'),
            ])
            ->add('startDate', Type\DateType::class, [
                'label'       => 'admin.bookings.start_date',
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [
                new Assert\GreaterThan([
                    'value'   => new DateTimeImmutable('now'),
                    'message' => $this->translator->trans(
                        'admin.constraints.start_date.greater_than_now'
                    )
                ])
                ]
            ])
            ->add('endDate', Type\DateType::class, [
                'label'       => 'admin.bookings.end_date',
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [
                    new Assert\GreaterThan([
                        'propertyPath' => 'parent.all[startDate].data',
                        'message'      => $this->translator->trans(
                            'admin.constraints.end_date.greater_than_start'
                        )
                    ])
                ]
            ])
            ->add('comment', Type\TextareaType::class, [
                'label'    => 'admin.bookings.comment',
                'required' => false,
                'attr'     => [
                    'rows' => 4
                ]
            ])
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('house', null, [
                'label'              => 'admin.bookings.house',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'address'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'house']
                ]
            ])
            ->add('user', null, [
                'label'              => 'admin.bookings.user',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'phoneNumber'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'user']
                ]
            ])
            ->add('startDate', 'date', [
                'label'  => 'admin.bookings.start_date',
                'format' => 'd.m.Y'
            ])
            ->add('endDate', 'date', [
                'label'  => 'admin.bookings.end_date',
                'format' => 'd.m.Y'
            ])
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, [
                'actions' => [
                    'show'   => [],
                    'edit'   => [],
                    'delete' => []
                ],
                'label' => 'admin.general.actions'
            ]);
    }

    #[Override]
    public function configureShowFields(ShowMapper $show): void
    {
        $show
            ->with('show_title', ['label' => 'admin.bookings.show_title'])
            ->add('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('house', null, ['label' => 'admin.bookings.house'])
            ->add('user', null, ['label' => 'admin.bookings.user'])
            ->add('startDate', 'date', [
                'label'  => 'admin.bookings.start_date',
                'format' => 'd.m.Y'
            ])
            ->add('endDate', 'date', [
                'label'  => 'admin.bookings.end_date',
                'format' => 'd.m.Y'
            ])
            ->add('comment', Type\TextareaType::class, ['label' => 'admin.bookings.comment'])
            ->end();
    }
}
