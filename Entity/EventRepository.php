<?php
/**
 * Copyright 2014 Arnaud Bienvenu
 *
 * This file is part of Kyela.

 * Kyela is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Kyela is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.

 * You should have received a copy of the GNU Affero General Public License
 * along with Kyela.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Abienvenu\KyelaBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * EventRepository
 *
 */
class EventRepository extends EntityRepository
{
	/**
     * Get future events
     *
	 * @param Poll $poll
     * @return \Doctrine\Common\Collections\Collection
	 */
	public function getFutureEvents(Poll $poll)
	{
		$query = $this->getEntityManager()->createQuery(
			'SELECT event
			FROM KyelaBundle:Event event
			WHERE event.poll = :poll
				AND event.date >= :date
			ORDER BY event.date'
		);
		$query->setParameter('poll', $poll->getId());
		$query->setParameter('date', new \DateTime("yesterday"));
		return $query->getResult();
	}
}