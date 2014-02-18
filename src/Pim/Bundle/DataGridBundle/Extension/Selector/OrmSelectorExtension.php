<?php

namespace Pim\Bundle\DataGridBundle\Extension\Selector;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\From;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Builder;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Pim\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource as PimOrmDatasource;
use Pim\Bundle\DataGridBundle\Extension\Selector\SelectorInterface;

/**
 * Orm selector extension
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class OrmSelectorExtension extends AbstractExtension
{
    /**
     * @var SelectorInterface[]
     */
    protected $selectors;

    /**
     * {@inheritdoc}
     */
    public function isApplicable(DatagridConfiguration $config)
    {
        return $this->matchDatasource($config);
    }

    /**
     * Add selector to array of available selectors
     *
     * @param string            $name
     * @param SelectorInterface $selector
     *
     * @return $this
     */
    public function addSelector($name, SelectorInterface $selector)
    {
        $this->selectors[$name] = $selector;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function visitDatasource(DatagridConfiguration $config, DatasourceInterface $datasource)
    {
        $entityIds = $this->getEntityIds($datasource);
        $rootAlias = $datasource->getQueryBuilder()->getRootAlias();
        $rootField = $rootAlias.'.id';

        if (count($entityIds) > 0) {
            $datasource->getQueryBuilder()
                ->andWhere($rootField.' IN (:entityIds)')->setParameter('entityIds', $entityIds);

            $datasource->getQueryBuilder()->setFirstResult(null)->setMaxResults(null);
        }

        $selectors = $this->getSelectorsToApply($config);
        foreach ($selectors as $selector) {
            $selector->apply($datasource, $config);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return -400;
    }

    /**
     * @param DatagridConfiguration $config
     *
     * @return boolean
     */
    protected function matchDatasource(DatagridConfiguration $config)
    {
        return $config->offsetGetByPath(Builder::DATASOURCE_TYPE_PATH) == PimOrmDatasource::TYPE;
    }

    /**
     * Retrieve entity ids, filters, sorters and limits are already in the datasource query builder
     *
     * @param DatasourceInterface $datasource
     *
     * @return array
     */
    protected function getEntityIds(DatasourceInterface $datasource)
    {
        $getIdsQb   = clone $datasource->getQueryBuilder();
        $rootEntity = current($getIdsQb->getRootEntities());
        $rootAlias  = $getIdsQb->getRootAlias();
        $rootField  = $rootAlias.'.id';
        $getIdsQb->add('from', new From($rootEntity, $rootAlias, $rootField), false);
        $results = $getIdsQb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_keys($results);
    }

    /**
     * Prepare selectors array
     *
     * @param DatagridConfiguration $config
     *
     * @return SelectorInterface[]
     */
    protected function getSelectorsToApply(DatagridConfiguration $config)
    {
        /*
        $filters       = [];
        $filtersConfig = $config->offsetGetByPath(Configuration::COLUMNS_PATH);

        foreach ($filtersConfig as $column => $filter) {
            // if label not set, try to suggest it from column with the same name
            if (!isset($filter['label'])) {
                $filter['label'] = $config->offsetGetByPath(
                    sprintf('[%s][%s][label]', FormatterConfiguration::COLUMNS_KEY, $column)
                );
            }
            $filters[] = $this->getFilterObject($column, $filter);
        }*/

        // TODO : get selectors from configuration
        $isFlexible = $config->offsetGetByPath('[source][is_flexible]');
        if ($isFlexible) {
            return $this->selectors;
        }


        return [];
    }
}