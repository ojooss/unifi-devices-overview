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

    /**
     * @param array<string, string> $filters
     * @return ClientDevice[]
     */
    public function findFiltered(array $filters): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.network', 'n')
            ->addSelect('n')
            ->orderBy('d.seenAt', 'DESC')
            ->addOrderBy('d.hostname', 'ASC');

        if (!empty($filters['network'])) {
            $qb->andWhere('n.name = :network')
                ->setParameter('network', $filters['network']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('d.hostname LIKE :search OR d.macAddress LIKE :search OR d.customName LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.ipType = :type')
                ->setParameter('type', $filters['type']);
        }

        return $qb->getQuery()->getResult();
    }
}
