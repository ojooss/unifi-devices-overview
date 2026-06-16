<?php

namespace App\Entity;

use App\Repository\NetworkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NetworkRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_network_name', columns: ['name'])]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dhcpRangeStart = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dhcpRangeEnd = null;

    /** @var Collection<int, ClientDevice> */
    #[ORM\OneToMany(targetEntity: ClientDevice::class, mappedBy: 'network')]
    private Collection $clientDevices;

    public function __construct(#[ORM\Column(length: 100)]
    private string $name, #[ORM\Column(length: 50)]
    private string $subnet)
    {
        $this->clientDevices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubnet(): string
    {
        return $this->subnet;
    }

    public function setSubnet(string $subnet): void
    {
        $this->subnet = $subnet;
    }

    public function getDhcpRangeStart(): ?string
    {
        return $this->dhcpRangeStart;
    }

    public function getDhcpRangeEnd(): ?string
    {
        return $this->dhcpRangeEnd;
    }

    public function setDhcpRange(?string $start, ?string $end): void
    {
        $this->dhcpRangeStart = $start;
        $this->dhcpRangeEnd = $end;
    }

    /** @return Collection<int, ClientDevice> */
    public function getClientDevices(): Collection
    {
        return $this->clientDevices;
    }
}
