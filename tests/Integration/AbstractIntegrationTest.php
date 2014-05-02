<?php

namespace Tests\MyCLabs\ACL\Integration;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use MyCLabs\ACL\ACL;
use MyCLabs\ACL\Doctrine\ACLSetup;

abstract class AbstractIntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ACL
     */
    protected $acl;

    public function setUp()
    {
        // Create the entity manager
        $paths = [
            __DIR__ . '/../../src/Model',
            __DIR__ . '/Model',
        ];
        $dbParams = $this->getDBParams();
        $config = Setup::createAnnotationMetadataConfiguration($paths, true, null, new ArrayCache(), false);
        $this->em = EntityManager::create($dbParams, $config);

        // Create the ACL object
        $this->acl = new ACL($this->em);
        $setup = $this->configureACL();
        $setup->setUpEntityManager($this->em, function () {
            return $this->acl;
        });

        // Create the DB
        $this->buildSchema();

        // Necessary so that SQLite supports CASCADE DELETE
        if ($dbParams['driver'] == 'pdo_sqlite') {
            $this->em->getConnection()->executeQuery('PRAGMA foreign_keys = ON');
        }
    }

    private function configureACL()
    {
        $setup = new ACLSetup();
        $setup->setSecurityIdentityClass('Tests\MyCLabs\ACL\Integration\Model\User');

        $setup->registerRoleClass('Tests\MyCLabs\ACL\Integration\Model\ArticleEditorRole', 'articleEditor');
        $setup->registerRoleClass('Tests\MyCLabs\ACL\Integration\Model\AllArticlesEditorRole', 'allArticlesEditor');
        $setup->registerRoleClass('Tests\MyCLabs\ACL\Integration\Model\ArticlePublisherRole', 'articlePublisher');
        $setup->registerRoleClass('Tests\MyCLabs\ACL\Integration\Model\CategoryManagerRole', 'categoryManager');

        $setup->setActionsClass('Tests\MyCLabs\ACL\Integration\Model\Actions');

        return $setup;
    }

    /**
     * Look into environment variables (defined in phpunit.xml configuration files).
     * @return array
     */
    private function getDBParams()
    {
        $dbParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        if (isset($GLOBALS['db_type'])) {
            $dbParams['driver'] = $GLOBALS['db_type'];
        }
        if (isset($GLOBALS['db_username'])) {
            $dbParams['user'] = $GLOBALS['db_username'];
        }
        if (isset($GLOBALS['db_password'])) {
            $dbParams['password'] = $GLOBALS['db_password'];
        }
        if (isset($GLOBALS['db_name'])) {
            $dbParams['dbname'] = $GLOBALS['db_name'];
        }

        return $dbParams;
    }

    private function buildSchema()
    {
        $connection = $this->em->getConnection();

        // Drop and recreate the database
        if ($connection->getDatabasePlatform()->supportsCreateDropDatabase()) {
            $dbname = $connection->getDatabase();
            $connection->close();

            $connection->getSchemaManager()->dropAndCreateDatabase($dbname);

            $connection->connect();
        } else {
            $sm = $connection->getSchemaManager();

            /* @var $schema Schema */
            $schema = $sm->createSchema();
            $stmts = $schema->toDropSql($connection->getDatabasePlatform());

            foreach ($stmts as $stmt) {
                $connection->exec($stmt);
            }
        }

        // Create the tables and all
        $tool = new SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }
}
