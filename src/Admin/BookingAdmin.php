<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Booking;
use App\Entity\House;
use App\Entity\User;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type;

/**
 * @extends AbstractAdmin<Booking>
 * @psalm-suppress UnusedClass
 */
final class BookingAdmin extends AbstractAdmin
{
    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        if (str_contains($this->getRequest()->getUri(), 'edit')) {
            $form->with('edit_title', ['label' => 'admin.bookings.edit_title']);
        } else {
            $form->with('create_title', ['label' => 'admin.bookings.create_title']);
        }

        $form
            ->add('house', EntityType::class, [
                'class'        => House::class,
                'choice_label' => 'address',
                'label'        => 'admin.bookings.house'
            ])
            ->add('user', EntityType::class, [
                'class'        => User::class,
                'choice_label' => 'phoneNumber',
                'label'        => 'admin.bookings.user'
            ])
            ->add('startDate', Type\DateType::class, [
                'label'  => 'admin.bookings.start_date',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable'
            ])
            ->add('endDate', Type\DateType::class, [
                'label'  => 'admin.bookings.end_date',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable'
            ])
            ->add('comment', Type\TextareaType::class, [
                'label'    => 'admin.bookings.comment',
                'required' => false
            ])
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('house', null, ['label' => 'admin.bookings.house'])
            ->add('user', null, ['label' => 'admin.bookings.user'])
            ->add('startDate', 'date', ['label' => 'admin.bookings.start_date'])
            ->add('endDate', 'date', ['label' => 'admin.bookings.end_date'])
            ->add(ListMapper::NAME_ACTIONS, ListMapper::TYPE_ACTIONS, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
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
            ->add('startDate', 'date', ['label' => 'admin.bookings.start_date'])
            ->add('endDate', 'date', ['label' => 'admin.bookings.end_date'])
            ->add('comment', Type\TextareaType::class, ['label' => 'admin.bookings.comment'])
            ->end();
    }
}
