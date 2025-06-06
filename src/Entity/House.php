<?php

declare (strict_types=1);

namespace App\Entity;

use App\Repository\HousesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HousesRepository::class)]
#[ORM\Table(name: 'houses')]
class House
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20)]
    private ?int $bedroomsCount = null;

    #[ORM\Column(length: 100000)]
    private ?int $pricePerNight = null;

    #[ORM\Column]
    private ?bool $hasAirConditioning = null;

    #[ORM\Column]
    private ?bool $hasWifi = null;

    #[ORM\Column]
    private ?bool $hasKitchen = null;

    #[ORM\Column]
    private ?bool $hasParking = null;

    #[ORM\Column]
    private ?bool $hasSeaView = null;

    #[ORM\ManyToOne(inversedBy: 'houses', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private City $city;

    #[ORM\OneToMany(
        mappedBy: 'house',
        targetEntity: Booking::class,
        orphanRemoval: true,
        cascade: ['remove']
    )]
    private Collection $bookings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getBedroomsCount(): ?int
    {
        return $this->bedroomsCount;
    }

    public function setBedroomsCount(int $bedroomsCount): static
    {
        $this->bedroomsCount = $bedroomsCount;

        return $this;
    }

    public function getPricePerNight(): ?int
    {
        return $this->pricePerNight;
    }

    public function setPricePerNight(int $pricePerNight): static
    {
        $this->pricePerNight = $pricePerNight;

        return $this;
    }

    public function hasAirConditioning(): ?bool
    {
        return $this->hasAirConditioning;
    }

    public function setHasAirConditioning(?bool $hasAirConditioning): static
    {
        $this->hasAirConditioning = $hasAirConditioning;

        return $this;
    }

    public function hasWifi(): ?bool
    {
        return $this->hasWifi;
    }

    public function setHasWifi(?bool $hasWifi): static
    {
        $this->hasWifi = $hasWifi;

        return $this;
    }

    public function hasKitchen(): ?bool
    {
        return $this->hasKitchen;
    }

    public function setHasKitchen(?bool $hasKitchen): static
    {
        $this->hasKitchen = $hasKitchen;

        return $this;
    }

    public function hasParking(): ?bool
    {
        return $this->hasParking;
    }

    public function setHasParking(?bool $hasParking): static
    {
        $this->hasParking = $hasParking;

        return $this;
    }

    public function hasSeaView(): ?bool
    {
        return $this->hasSeaView;
    }

    public function setHasSeaView(?bool $hasSeaView): static
    {
        $this->hasSeaView = $hasSeaView;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setHouse($this);
        }

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function __toString(): string
    {
        return $this->address ?? 'Unknown';
    }
}
