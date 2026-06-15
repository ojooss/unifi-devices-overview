<?php

namespace App\Entity;

use App\Repository\ClientDeviceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientDeviceRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_client_device_mac_seen', columns: ['mac_address', 'seen_at'])]
#[ORM\Index(columns: ['seen_at'], name: 'idx_seen_at')]
#[ORM\Index(columns: ['network_id'], name: 'idx_network')]
class ClientDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Network::class, inversedBy: 'clientDevices')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Network $network = null;

    #[ORM\Column(length: 17)]
    private string $macAddress;

    #[ORM\Column(length: 15)]
    private string $ipAddress = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hostname = null;

    /** fixed | dynamic */
    #[ORM\Column(length: 10)]
    private string $ipType = 'dynamic';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $leaseExpiresAt = null;

    /** Timestamp aus Support-Dateiname */
    #[ORM\Column]
    private \DateTimeImmutable $seenAt;

    /** Zeitpunkt des letzten Imports */
    #[ORM\Column]
    private \DateTimeImmutable $lastUpdatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

    public function __construct(string $macAddress, \DateTimeImmutable $seenAt)
    {
        $this->macAddress = $macAddress;
        $this->seenAt = $seenAt;
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    public function update(
        ?Network $network,
        string $ipAddress,
        ?string $hostname,
        string $ipType,
        ?\DateTimeImmutable $leaseExpiresAt,
        \DateTimeImmutable $now,
    ): void {
        $this->network = $network;
        $this->ipAddress = $ipAddress;
        $this->hostname = $hostname;
        $this->ipType = $ipType;
        $this->leaseExpiresAt = $leaseExpiresAt;
        $this->lastUpdatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function getMacAddress(): string
    {
        return $this->macAddress;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function getIpType(): string
    {
        return $this->ipType;
    }

    public function getLeaseExpiresAt(): ?\DateTimeImmutable
    {
        return $this->leaseExpiresAt;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function getLastUpdatedAt(): \DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function getCustomName(): ?string
    {
        return $this->customName;
    }

    public function setCustomName(?string $customName): void
    {
        $this->customName = $customName;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }
}
