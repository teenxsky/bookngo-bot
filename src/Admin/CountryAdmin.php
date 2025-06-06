<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Country;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractAdmin<Country>
 * @psalm-suppress UnusedClass
 */
final class CountryAdmin extends AbstractAdmin
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct();
        $this->translator = $translator;
    }

    #[Override]
    public function configureBatchActions(array $actions): array
    {
        if (isset($actions['delete'])) {
            $actions['delete'] = [
                'template' => 'admin/country/delete_confirmation.html.twig',
            ];
        }

        return $actions;
    }

    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        if (str_contains($this->getRequest()->getUri(), 'edit')) {
            $form->with('edit_title', ['label' => 'admin.countries.edit_title']);
        } else {
            $form->with('create_title', ['label' => 'admin.countries.create_title']);
        }

        $form
            ->add('name', Type\TextType::class, [
                'label'       => 'admin.countries.name',
                'constraints' => [
                    new Assert\Length(
                        max: 100,
                        maxMessage: $this->translator->trans(
                            'admin.constraints.country_name.max_length'
                        )
                    )
                ]
            ])
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('name', Type\TextType::class, ['label' => 'admin.countries.name'])
            ->add('cities', null, ['label' => 'admin.cities.title'])
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
            ->with('show_title', ['label' => 'admin.countries.show_title'])
            ->add('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('name', Type\TextType::class, ['label' => 'admin.countries.name'])
            ->add('cities', null, ['label' => 'admin.cities.title'])
            ->end();
    }
}
