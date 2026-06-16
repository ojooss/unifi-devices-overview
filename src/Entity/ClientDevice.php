<?php

namespace App\Entity;

use App\Repository\ClientDeviceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientDeviceRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_client_device_mac_seen', columns: ['mac_address', 'seen_at'])]
#[ORM\Index(name: 'idx_seen_at', columns: ['seen_at'])]
#[ORM\Index(name: 'idx_network', columns: ['network_id'])]
class ClientDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Network::class, inversedBy: 'clientDevices')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Network $network = null;

    #[ORM\Column(length: 15)]
    private string $ipAddress = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hostname = null;

    /** fixed | dynamic */
    #[ORM\Column(length: 10)]
    private string $ipType = 'dynamic';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $leaseExpiresAt = null;

    /** Zeitpunkt des letzten Imports */
    #[ORM\Column]
    private \DateTimeImmutable $lastUpdatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $unifiAlias = null;

    public function __construct(
        #[ORM\Column(length: 17)]
        private string $macAddress, /** Timestamp aus Support-Dateiname */
        #[ORM\Column]
        private \DateTimeImmutable $seenAt
    ) {
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    public function update(
        ?Network $network,
        string $ipAddress,
        ?string $hostname,
        string $ipType,
        ?\DateTimeImmutable $leaseExpiresAt,
        \DateTimeImmutable $now,
        ?string $unifiAlias = null,
    ): void {
        $this->network = $network;
        $this->ipAddress = $ipAddress;
        $this->hostname = $hostname;
        $this->ipType = $ipType;
        $this->leaseExpiresAt = $leaseExpiresAt;
        $this->lastUpdatedAt = $now;
        $this->unifiAlias = $unifiAlias;
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

    public function getUnifiAlias(): ?string
    {
        return $this->unifiAlias;
    }
}
