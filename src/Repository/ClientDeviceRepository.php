<?php

namespace App\Repository;

use App\Entity\ClientDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientDevice>
 */
class ClientDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientDevice::class);
    }

    private const array SORTABLE_COLUMNS = [
        'name' => 'd.hostname',
        'mac' => 'd.macAddress',
        'ip' => 'd.ipAddress',
        'type' => 'd.ipType',
        'network' => 'n.name',
        'updated' => 'd.lastUpdatedAt',
        'seen' => 'd.seenAt',
    ];

    /**
     * @param array<string, string> $filters
     * @return ClientDevice[]
     */
    public function findFiltered(array $filters): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.network', 'n')
            ->addSelect('n');

        $sortField = self::SORTABLE_COLUMNS[$filters['sort'] ?? ''] ?? null;
        $sortDir = strtoupper($filters['dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        if ($sortField !== null) {
            $qb->orderBy($sortField, $sortDir)
                ->addOrderBy('d.hostname', 'ASC');
        } else {
            $qb->orderBy('d.seenAt', 'DESC')
                ->addOrderBy('d.hostname', 'ASC');
        }

        if (!empty($filters['network'])) {
            $qb->andWhere('n.name = :network')
                ->setParameter('network', $filters['network']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere(
                'd.hostname LIKE :search OR d.macAddress LIKE :search' .
                ' OR d.customName LIKE :search OR d.remark LIKE :search'
            )
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.ipType = :type')
                ->setParameter('type', $filters['type']);
        }

        return $qb->getQuery()->getResult();
    }
}
