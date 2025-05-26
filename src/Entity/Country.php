<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CountriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @psalm-suppress all
 */
#[ORM\Entity(repositoryClass: CountriesRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotNull]
    #[Assert\Type('string')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Country name cannot be longer than {{ limit }} characters'
    )]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: City::class)]
    private Collection $cities;

    public function __construct()
    {
        $this->cities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Collection<int, City>
     */
    public function getCities(): Collection
    {
        return $this->cities;
    }

    public function addCity(City $city): static
    {
        if (!$this->cities->contains($city)) {
            $this->cities->add($city);
            $city->setCountry($this);
        }
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
