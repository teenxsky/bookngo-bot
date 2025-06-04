<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\City;
use App\Entity\House;
use Override;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractAdmin<House>
 * @psalm-suppress UnusedClass
 */
final class HouseAdmin extends AbstractAdmin
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
                'template' => 'admin/house/delete_confirmation.html.twig',
            ];
        }

        return $actions;
    }

    #[Override]
    public function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('basic_info', ['label' => 'admin.houses.basic_info'])
            ->with('general', ['class' => 'col-md-6', 'label' => 'admin.houses.general'])
            ->add('address', Type\TextType::class, [
                        'label'       => 'admin.houses.address',
                        'constraints' => [
                            new Assert\Length(
                                max: 255,
                                maxMessage: $this->translator->trans(
                                    'admin.constraints.house_address.max_length'
                                )
                            )
                        ],
                    ])
            ->add('city', EntityType::class, [
                        'class'        => City::class,
                        'choice_label' => 'name',
                        'label'        => 'admin.houses.city'
                    ])
            ->add('pricePerNight', Type\MoneyType::class, [
                        'label'       => 'admin.houses.price_per_night',
                        'constraints' => [
                            new Assert\Range(min: 100, max: 100000),
                        ],
                        'attr' => [
                            'min' => 100,
                            'max' => 100000,
                        ],
                    ])
            ->add('bedroomsCount', Type\IntegerType::class, [
                        'label'       => 'admin.houses.bedrooms_count',
                        'constraints' => [
                            new Assert\Range(min: 1, max: 20),
                        ],
                        'attr' => [
                            'min' => 1,
                            'max' => 20,
                        ],
                    ])
            ->end()
            ->with('media', ['class' => 'col-md-6', 'label' => 'admin.houses.media'])
            ->add('imageUrl', Type\UrlType::class, [
                        'label'    => 'admin.houses.image_url',
                        'required' => false
                    ])
            ->end()
            ->end()
            ->tab('amenities', ['label' => 'admin.houses.amenities'])
            ->with('features', ['class' => 'col-md-12', 'label' => 'admin.houses.features'])
            ->add('hasAirConditioning', Type\CheckboxType::class, [
                        'label'    => 'admin.houses.has_air_conditioning',
                        'required' => false
                    ])
            ->add('hasWifi', Type\CheckboxType::class, [
                        'label'    => 'admin.houses.has_wifi',
                        'required' => false
                    ])
            ->add('hasKitchen', Type\CheckboxType::class, [
                        'label'    => 'admin.houses.has_kitchen',
                        'required' => false
                    ])
            ->add('hasParking', Type\CheckboxType::class, [
                        'label'    => 'admin.houses.has_parking',
                        'required' => false
                    ])
            ->add('hasSeaView', Type\CheckboxType::class, [
                        'label'    => 'admin.houses.has_sea_view',
                        'required' => false
                    ])
            ->end()
            ->end();
    }

    #[Override]
    public function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', Type\IntegerType::class, [
                'label' => 'admin.general.id'
            ])
            ->add('address', Type\TextType::class, [
                'label' => 'admin.houses.address'
            ])
            ->add('pricePerNight', Type\MoneyType::class, [
                'label' => 'admin.houses.price_per_night'
            ])
            ->add('bedroomsCount', Type\IntegerType::class, [
                'label' => 'admin.houses.bedrooms_count'
            ])
            ->add('bookings.count', Type\IntegerType::class, [
                'label'              => 'admin.houses.bookings_count',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'id'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'bookings']
                ]
            ])
            ->add('city', null, [
                'label'              => 'admin.houses.city',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'name'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'city']
                ]
            ])
            ->add('city.country', null, [
                'label'              => 'admin.houses.country',
                'sortable'           => true,
                'sort_field_mapping' => [
                    'fieldName' => 'name'
                ],
                'sort_parent_association_mappings' => [
                    ['fieldName' => 'city'],
                    ['fieldName' => 'country']
                ]
            ])
            ->add('hasAirConditioning', null, [
                'label'    => 'admin.houses.has_air_conditioning',
                'sortable' => false,
            ])
            ->add('hasWifi', null, [
                'label'    => 'admin.houses.has_wifi',
                'sortable' => false,
            ])
            ->add('hasKitchen', null, [
                'label'    => 'admin.houses.has_kitchen',
                'sortable' => false,
            ])
            ->add('hasParking', null, [
                'label'    => 'admin.houses.has_parking',
                'sortable' => false,
            ])
            ->add('hasSeaView', null, [
                'label'    => 'admin.houses.has_sea_view',
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
            ->tab('basic_info', options: ['label' => 'admin.houses.basic_info'])
            ->with('general', ['class' => 'col-md-6', 'label' => 'admin.houses.general'])
            ->add('id', Type\IntegerType::class, ['label' => 'admin.general.id'])
            ->add('address', Type\TextType::class, ['label' => 'admin.houses.address'])
            ->add('bedroomsCount', Type\IntegerType::class, [
                        'label' => 'admin.houses.bedrooms_count'
                    ])
            ->add('pricePerNight', Type\MoneyType::class, [
                        'label' => 'admin.houses.price_per_night'
                    ])
            ->add('city', null, ['label' => 'admin.houses.city'])
            ->add('city.country', null, ['label' => 'admin.houses.country'])
            ->end()
            ->with('media', ['class' => 'col-md-6', 'label' => 'admin.houses.media'])
            ->add('imageUrl', Type\UrlType::class, [
                        'template' => 'admin/house/show_image.html.twig',
                        'label'    => 'admin.houses.image_url'
                    ])
            ->end()
            ->with('bookings', ['class' => 'col-md-6', 'label' => 'admin.houses.bookings'])
            ->add('bookings', null, [
                        'template' => 'admin/house/show_bookings.html.twig',
                        'label'    => 'admin.houses.bookings'
                    ])
            ->end()
            ->end()
            ->tab('amenities', options: ['label' => 'admin.houses.amenities'])
            ->with('amenities', ['class' => 'col-md-12', 'label' => 'admin.houses.amenities'])
            ->add('hasAirConditioning', null, ['label' => 'admin.houses.has_air_conditioning'])
            ->add('hasWifi', null, ['label' => 'admin.houses.has_wifi'])
            ->add('hasKitchen', null, ['label' => 'admin.houses.has_kitchen'])
            ->add('hasParking', null, ['label' => 'admin.houses.has_parking'])
            ->add('hasSeaView', null, ['label' => 'admin.houses.has_sea_view'])
            ->end()
            ->end();
    }
}
