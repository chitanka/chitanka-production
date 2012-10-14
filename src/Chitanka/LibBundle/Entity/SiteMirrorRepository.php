<?php

namespace Chitanka\LibBundle\Entity;

/**
 *
 */
class SiteMirrorRepository extends EntityRepository
{
	public function getUpdatedAfter($date)
	{
		return $this->createQueryBuilder('m')
			->where('m.last_update >= ?1')
			->setParameter(1, $date)
			->getQuery()
			->getResult();
	}
}
