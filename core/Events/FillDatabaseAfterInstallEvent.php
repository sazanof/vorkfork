<?php

namespace Vorkfork\Core\Events;

use Symfony\Component\HttpFoundation\Request;
use Vorkfork\Application\ApplicationUtilities;
use Vorkfork\Core\Application;
use Vorkfork\Core\Models\Config;
use Vorkfork\Core\Models\Group;
use Vorkfork\Core\Models\Permissions;
use Vorkfork\Core\Models\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Contracts\EventDispatcher\Event;
use Throwable;
use Vorkfork\Core\Translator\Locale;

class FillDatabaseAfterInstallEvent extends Event
{
    public const NAME = 'install.after';
    private ApplicationUtilities $utilities;
    private ?User $admin;
    private ?Group $group;
    private Request $request;

    /**
     * @throws OptimisticLockException
     * @throws MappingException
     * @throws ORMException
     * @throws Throwable
     */
    public function __construct(User $admin, array $applicationsList, Request $request)
    {
        $this->utilities = ApplicationUtilities::getInstance();
        $this->request = $request;
        $this->admin = $admin;
        $this->utilities->getEntityManager()->wrapInTransaction(function () {
            $this->fillConfig();
            $this->fillCorePermissions();
            $this->createAdminGroup();
            $this->addAdminToAdminGroup();
            $this->setPermissionsToAdminGroup();
            $this->utilities->getEntityManager()->flush();
        });
        return [];

        //dd(__CLASS__,__METHOD__, $applicationsList);
        /*foreach ($applicationsList as $app){

        }*/
    }

    public function getAdmin()
    {
        return $this->admin;
    }

    public function getAdminGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * @throws OptimisticLockException
     * @throws MappingException
     * @throws ORMException
     */
    private function fillConfig()
    {
        Config::insertBulk([
            [
                'app' => Application::$configKey,
                'key' => 'version',
                'value' => $this->utilities->getVersion()
            ],
            [
                'app' => Application::$configKey,
                'key' => 'timezone',
                'value' => Locale::getDefaultTimezone()
            ],
            [
                'app' => Application::$configKey,
                'key' => 'locale',
                'value' => $this->request->get('locale')
            ]
        ]);
    }

    /**
     * @throws MappingException
     */
    private function fillCorePermissions()
    {
        Permissions::repository()->insertDefaultPermissions();
    }

    /**
     * @throws MappingException
     */
    private function createAdminGroup()
    {
        $this->group = Group::create([
            'name' => 'Administrators'
        ]);
    }

    /**
     * @return void
     */
    private function addAdminToAdminGroup(): void
    {
        $a = User::repository()->find($this->admin->getId());
        $a->setGroups([$this->group]);
    }

    private function setPermissionsToAdminGroup()
    {
        $permissions = Permissions::repository()->findAll();
        $this->group->setPermissions($permissions);
    }
}