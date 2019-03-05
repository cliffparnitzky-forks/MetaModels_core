<?php

/**
 * This file is part of MetaModels/core.
 *
 * (c) 2012-2019 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2012-2019 The MetaModels team.
 * @license    https://github.com/MetaModels/core/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MetaModels\CoreBundle\EventListener\DcGeneral\Table\DcaSetting;

use Contao\Message;
use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminator;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\BuildWidgetEvent;
use Doctrine\DBAL\Connection;
use MetaModels\IFactory;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This handles disabling of the readonly field
 */
class DisableReadOnlyListener extends AbstractListener
{
    /**
     * The translator.
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * Create a new instance.
     *
     * @param RequestScopeDeterminator $scopeDeterminator The scope determinator.
     * @param IFactory                 $factory           The MetaModel factory.
     * @param Connection               $connection        The database connection.
     * @param TranslatorInterface      $translator        The translator.
     */
    public function __construct(
        RequestScopeDeterminator $scopeDeterminator,
        IFactory $factory,
        Connection $connection,
        TranslatorInterface $translator
    ) {
        parent::__construct($scopeDeterminator, $factory, $connection);
        $this->translator = $translator;
    }

    /**
     * Disable the readonly checkbox field if the selected attribute has force_alias.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    public function handle(BuildWidgetEvent $event)
    {
        $environment = $event->getEnvironment();
        if (($environment->getDataDefinition()->getName() !== 'tl_metamodel_dcasetting')
            || ($event->getProperty()->getName() !== 'readonly')
            || (null === $event->getModel()->getId())) {
            return;
        }

        $model     = $event->getModel();
        $metaModel = $this->getMetaModelFromModel($model);

        if ($metaModel->getAttributeById($model->getProperty('attr_id'))->get('force_alias')) {
            Message::addInfo(
                $this->translator->trans(
                    'tl_metamodel_dcasetting.readonly_for_force_alias',
                    [],
                    'contao_tl_metamodel_dcasetting'
                )
            );

            $extra = $event->getProperty()->getExtra();

            $extra['disabled'] = true;

            $event->getProperty()->setExtra($extra);

            $model->setProperty('readonly', true);
        }
    }
}
