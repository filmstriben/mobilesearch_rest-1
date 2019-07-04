<?php

namespace AppBundle\Repositories;

use AppBundle\Document\Content;
use AppBundle\Document\Lists;
use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * Class ListsRepository.
 */
class ListsRepository extends DocumentRepository
{
    /**
     * Filter items in the list that are not of certain type.
     *
     * @param Lists $list
     *   The list object.
     * @param string $itemType
     *   Item type to keep.
     * @return Lists
     *   Altered list object.
     */
    public function filterAttachedItems(Lists $list, $itemType)
    {
        $nids = $list->getNids();
        $qb = $this->getDocumentManager()->createQueryBuilder(Content::class);

        $qb->distinct('nid');
        $qb->field('nid')->in($nids);
        $qb->field('type')->equals($itemType);

        $contentItems = $qb->getQuery()->execute();

        $list->setNids(array_values(array_intersect($nids, $contentItems->toArray())));
        return $list;
    }
}
