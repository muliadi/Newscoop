<?php
/**
 * @package Newscoop
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Services\Plugins;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Doctrine\ORM\EntityManager;
use Newscoop\EventDispatcher\EventDispatcher;
use Newscoop\EventDispatcher\Events\PluginHooksEvent;
use Newscoop\EventDispatcher\Events\CollectObjectsDataEvent;
use Newscoop\EventDispatcher\Events\PluginPermissionsEvent;

/**
 * Plugins Service
 *
 * Service for plugins stuff
 */
class PluginsService
{
    /**
     * @var Newscoop\EventDispatcher\EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * Avaiable plugins
     * @var Collection
     */
    protected $avaiablePlugins;

    /**
     * @param Newscoop\EventDispatcher\EventDispatcher $dispatcher
     * @param Doctrine\ORM\EntityManager               $em
     */
    public function __construct($dispatcher, EntityManager $em)
    {
        $this->dispatcher = $dispatcher;
        $this->em = $em;
    }

    public function getAllAvailablePlugins()
    {
        if ($this->avaiablePlugins) {
            return $this->avaiablePlugins;
        }

        return $this->avaiablePlugins = $this->em->getRepository('Newscoop\Entity\Plugin')->findAll();
    }

    public function getEnabledPlugins()
    {
        $eb = new ExpressionBuilder();
        $expr = $eb->eq('enabled', true);
        $criteria = new Criteria($expr);

        $avaiablePlugins = new ArrayCollection($this->getAllAvailablePlugins());

        return $avaiablePlugins->matching($criteria);
    }

    public function getPluginByCriteria($criteria, $id)
    {
        $eb = new ExpressionBuilder();
        $expr = $eb->eq($criteria, intval($id));
        $criteria = new Criteria($expr);

        $avaiablePlugins = new ArrayCollection($this->getAllAvailablePlugins());

        return $avaiablePlugins->matching($criteria);
    }

    /**
     * Dispatch hook event and render collected Response objects
     *
     * @param string $eventName
     * @param mixed  $subject
     * @param array  $options
     *
     * @return string
     */
    public function renderPluginHooks($eventName, $subject = null, $options = null)
    {
        $content = '';
        $pluginHooksEvent = $this->dispatcher->dispatch($eventName, new PluginHooksEvent($subject, $options));

        foreach ($pluginHooksEvent->getHooksResponses() as $key => $response) {
            $content .= $response->getContent();
        }

        return $content;
    }

    /**
     * Dispatch event for list objects registration
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return string
     */
    public function collectListObjects($subject = null, $options = array())
    {
        $collectedData = array('listObjects' => array(), 'objectTypes' => array());
        $listObjectsRegistration = $this->dispatcher->dispatch('newscoop.listobjects.register', new CollectObjectsDataEvent($subject, $options));

        foreach ($listObjectsRegistration->getListObjects() as $key => $object) {
            $collectedData['listObjects'][$key] = $object;
        }

        foreach ($listObjectsRegistration->getObjectTypes() as $key => $object) {
            $collectedData['objectTypes'][$key] = $object;
        }

        return $collectedData;
    }

    public function isEnabled($pluginName)
    {
        $plugin = $this->getPluginByCriteria('name', $pluginName)->first();
        if ($plugin) {
            if ($plugin->getEnabled()) {
                return true;
            }
        }

        return false;
    }

    public function isInstalled($pluginName)
    {
        $plugin = $this->getPluginByCriteria('name', $pluginName)->first();
        if ($plugin) {
            return true;
        }

        return false;
    }

    public function getPluginsDir()
    {
        $pluginsDir = __DIR__ . '/../../../../plugins';

        if (file_exists($pluginsDir)) {
            return realpath($pluginsDir);
        }

        return $pluginsDir;
    }

    /**
     * Dispatch event for plugins permissions
     *
     * @param mixed $subject
     * @param array $options
     *
     * @return array
     */
    public function collectPermissions($subject = null, $options = array())
    {
        $collectedPermissionsData = array();
        $collectedPermissions = $this->dispatcher->dispatch('newscoop.plugins.permissions.register', new PluginPermissionsEvent($subject, $options));

        foreach ($collectedPermissions->getPermissions() as $key => $object) {
            $collectedPermissionsData[$key] = $object;
        }

        return $collectedPermissionsData;
    }

    /**
     * Add dynamic right
     *
     * @param array $right
     * @return void
     *
     * @deprecated will be deprecated since version 4.5
     */
    public function addRight(array $right)
    {
        $connection = $this->em->getConnection();
        // get next id
        $query = $connection->query('SELECT MAX(right_id) as max FROM liveuser_rights')->fetch();
        $lastId = (int) $query['max'];
        $nextId = $lastId + 1;
        $areaId = (int) $right['area_id'];

        $connection->executeUpdate('INSERT IGNORE INTO liveuser_rights (right_id, area_id, right_define_name) VALUES (:next, :area, :name)', array(
            'next' => $nextId,
            'area' => $areaId,
            'name' => $right['right_define_name']
        ));
    }

    /**
     * Save plugin permissions in Newscoop ACL
     *
     * @param  array  $pluginPermissions Plugin permissions
     *
     * @return void
     */
    public function savePluginPermissions(array $pluginPermissions)
    {
        try {
            foreach ($pluginPermissions as $permissionArray) {
                foreach ($permissionArray as $permission => $permissionLabel) {
                    $this->addRight(array(
                        'area_id' => 0,
                        'right_define_name' => $permission,
                        'has_implied' => 1
                    ));

                    $permissionsArray = explode('_', $permission);
                    $values = array(
                        'role' => 1, //give access for admin group by default
                        'type' => 'allow',
                        'resource' => $permissionsArray[0] . '-' . $permissionsArray[1],
                        'action' => $permissionsArray[2]
                    );

                    $this->em->getRepository('Newscoop\Entity\Acl\Rule')->save($values);
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Error setting up plugin permissions", 1);
        }
    }
}
