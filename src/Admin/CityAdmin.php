<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\City;
use App\Entity\Country;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type;

/**
 * @extends AbstractAdmin<City>
 * @psalm-suppress UnusedClass
 */
final class CityAdmin extends AbstractAdmin
{
    #[Override]
    public function configureBatchActions(array $actions): array
    {
        if (isset($actions['delete'])) {
            $actions['delete'] = [
                'template' => 'admin/city/delete_confirmation.html.twig',
            ];
        }

        return $actions;
    }

    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        if (str_contains($this->getRequest()->getUri(), 'edit')) {
            $form->with('edit_title', ['label' => 'admin.cities.edit_title']);
        } else {
            $form->with('create_title', ['label' => 'admin.cities.create_title']);
        }

        $form
            ->add('name', Type\TextType::class, [
                'label' => 'admin.cities.name'
            ])
            ->add('country', EntityType::class, [
                'class'        => Country::class,
                'choice_label' => 'name',
                'label'        => 'admin.cities.country'
            ])
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('name', Type\TextType::class, ['label' => 'admin.cities.name'])
            ->add('country', null, [
                'label'              => 'admin.cities.country',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'name'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'country']
                ]
            ])
            ->add('houses', null, [
                'label'    => 'admin.houses.title',
                'sortable' => false,
            ])
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
            ->with('show_title', ['label' => 'admin.cities.show_title'])
            ->add('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('name', Type\TextType::class, ['label' => 'admin.cities.name'])
            ->add('country', null, ['label' => 'admin.cities.country'])
            ->end();
    }
}
