<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Filter\Setting;

use Database\Result;
use MetaModels\IMetaModel;
use MetaModels\IMetaModelsServiceContainer;

/**
 * This is the filter settings factory interface.
 */
class FilterSettingFactory implements IFilterSettingFactory
{
    /**
     * The event dispatcher.
     *
     * @var IMetaModelsServiceContainer
     */
    protected $serviceContainer;

    /**
     * The registered type factories.
     *
     * @var IFilterSettingTypeFactory[]
     */
    protected $typeFactories;

    /**
     * Create a new instance.
     *
     * @param IMetaModelsServiceContainer $serviceContainer The service container to use.
     */
    public function __construct(IMetaModelsServiceContainer $serviceContainer)
    {
        $this->serviceContainer = $serviceContainer;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeFactory($factory)
    {
        $this->typeFactories[$factory->getTypeName()] = $factory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeFactory($type)
    {
        return isset($this->typeFactories[$type]) ? $this->typeFactories[$type] : null;
    }

    /**
     * Create a new setting.
     *
     * @param Result     $objSettings The information from which to initialize the setting from.
     *
     * @param IMetaModel $metaModel   The MetaModel instance.
     *
     * @return ISimple
     */
    protected function createSetting($objSettings, $metaModel)
    {
        $factory = $this->getTypeFactory($objSettings->type);
        if ($factory) {
            $setting = $factory->createInstance($objSettings->row(), $metaModel);

            if (!$setting) {
                return null;
            }

            // Collect next level.
            if ($factory->isNestedType()) {
                /** @var IWithChildren $setting */
                $this->collectRulesFor($setting, $metaModel);
            }

            return $setting;
        }

        return null;
    }

    /**
     * Fetch all child rules for the given setting.
     *
     * @param IWithChildren $parentSetting The information from which to initialize the setting from.
     *
     * @param IMetaModel    $metaModel     The MetaModel instance.
     *
     * @return void
     */
    protected function collectRulesFor($parentSetting, $metaModel)
    {
        $childInformation = $this->serviceContainer->getDatabase()
            ->prepare('SELECT * FROM tl_metamodel_filtersetting WHERE pid=? AND enabled=1 ORDER BY sorting ASC')
            ->execute($parentSetting->get('id'));

        while ($childInformation->next()) {
            $childSetting = $this->createSetting($childInformation, $metaModel);
            if ($childSetting) {
                $parentSetting->addChild($childSetting);
            }
        }
    }

    /**
     * Collect the rules for a filter setting.
     *
     * @param Collection $collection The collection.
     *
     * @param IMetaModel $metaModel  The MetaModel instance.
     *
     * @return void
     */
    public function collectRules($collection, $metaModel)
    {
        $database    = $this->serviceContainer->getDatabase();
        $information = $database
            ->prepare(
                'SELECT * FROM tl_metamodel_filtersetting WHERE fid=? AND pid=0 AND enabled=1 ORDER BY sorting ASC'
            )
            ->execute($collection->get('id'));

        while ($information->next()) {
            $newSetting = $this->createSetting($information, $metaModel);
            if ($newSetting) {
                $collection->addSetting($newSetting);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($settingId)
    {
        $information = $this->serviceContainer->getDatabase()
            ->prepare('SELECT * FROM tl_metamodel_filter WHERE id=?')
            ->execute($settingId)
            ->row();

        if (!empty($information)) {
            $modelFactory = $this->serviceContainer->getFactory();
            $metaModel    = $modelFactory->getMetaModel($modelFactory->translateIdToMetaModelName($information['pid']));
            $collection   = new Collection($information);

            $collection->setMetaModel($metaModel);
            $this->collectRules($collection, $metaModel);

            return $collection;
        }

        return new Collection(array());
    }
}