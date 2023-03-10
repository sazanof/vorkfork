<?php

const INC_MODE = true;

use Vorkfork\Application\ApplicationUtilities;
use Vorkfork\Core\Config\Config;
use Vorkfork\Core\Console\Commands\UpgradeCommand;
use Vorkfork\Core\Events\TablePrefix;
use Doctrine\Common\EventManager;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Tools\Console\Command as DoctrineCommand;
use Symfony\Component\Filesystem\Path;

require_once realpath(dirname(__FILE__,2)) . '/vendor/autoload.php';
try {
    $env = Dotenv::createImmutable(realpath('./'));
    $env->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    dump(__FILE__,$e->getMessage(), $e->getCode(), $e->getLine());
}


//$application = new Application();
//$application->setName('Vorkfork Console');
try {
    $config = new Config('database');
    $connection = DriverManager::getConnection($config->getConfig());
    $configuration = new Configuration();
//TODO add applications migrations too
    $configuration->addMigrationsDirectory('Clb\Migrations', 'database/migrations');
    $configuration->setAllOrNothing(true);
    $configuration->setCheckDatabasePlatform(false);

    $storageConfiguration = new TableMetadataStorageConfiguration();
    $storageConfiguration->setTableName($config->getConfigValue('prefix') . 'migrations');

    $configuration->setMetadataStorageConfiguration($storageConfiguration);
    /**@var \Symfony\Component\Finder\Finder $dirs*/
    $pats = [];
    $paths[] = realpath('./core/Models');
// Get ORM Models from apps
    $dirs = ApplicationUtilities::getApplicationDirectories();
    foreach ($dirs as $dir) {
        $path = Path::normalize($dir->getRealPath() . '/lib/Models');
        if(is_dir($path)){
            $paths[] = $path;
        }
    }

    $isDevMode = env('APP_MODE') === 'development';

    $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
        paths: $paths,
        isDevMode: env('APP_MODE') === 'development',
    );

    $evm = new EventManager;
    $tablePrefix = new TablePrefix($config->getConfigValue('prefix'));
    $evm->addEventListener(Events::loadClassMetadata, $tablePrefix);

    try {
        $entityManager = new EntityManager($connection, $ormConfig, $evm);
    } catch (\Doctrine\ORM\Exception\MissingMappingDriverImplementation $e) {
        dump('console.php - MissingMappingDriverImplementation when creating Entity Manager');
    }

    $dependencyFactory = DependencyFactory::fromEntityManager(
        new ExistingConfiguration($configuration),
        new ExistingEntityManager($entityManager)
    );



    $commands = [
        new UpgradeCommand(),
        new DoctrineCommand\CurrentCommand($dependencyFactory),
        new DoctrineCommand\DiffCommand($dependencyFactory),
        new DoctrineCommand\DumpSchemaCommand($dependencyFactory),
        new DoctrineCommand\ExecuteCommand($dependencyFactory),
        new DoctrineCommand\GenerateCommand($dependencyFactory),
        new DoctrineCommand\LatestCommand($dependencyFactory),
        new DoctrineCommand\ListCommand($dependencyFactory),
        new DoctrineCommand\MigrateCommand($dependencyFactory),
        new DoctrineCommand\RollupCommand($dependencyFactory),
        new DoctrineCommand\StatusCommand($dependencyFactory),
        new DoctrineCommand\SyncMetadataCommand($dependencyFactory),
        new DoctrineCommand\UpToDateCommand($dependencyFactory),
        new DoctrineCommand\VersionCommand($dependencyFactory),
    ];

    try {
        ConsoleRunner::run(
            new SingleManagerProvider($entityManager),
            $commands
        );
    } catch (Exception $e) {
        //log
    }
} catch (\Doctrine\DBAL\Exception $e){
    dump('You application is not installed, or you have problems with you database connection.');
}


