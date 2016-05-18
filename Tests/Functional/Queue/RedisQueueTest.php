<?php
namespace R3H6\JobqueueRedis\Tests\Functional\Queue;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 3 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

use R3H6\JobqueueRedis\Queue\RedisQueue;
use R3H6\Jobqueue\Queue\Message;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Functional test for RedisQueue
 */
class RedisQueueTest extends \TYPO3\CMS\Core\Tests\FunctionalTestCase
{
    use \R3H6\Jobqueue\Tests\Functional\Queue\QueueTestTrait;
    use \R3H6\Jobqueue\Tests\Functional\Queue\QueueDelayTestTrait;

    const QUEUE_NAME = 'TestQueue';

    protected $coreExtensionsToLoad = array('extbase');
    protected $testExtensionsToLoad = array('typo3conf/ext/jobqueue', 'typo3conf/ext/jobqueue_redis');

    /**
     * @var TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @var R3H6\JobqueueRedis\Queue\RedisQueue
     */
    protected $queue;

    /**
     * Set up dependencies
     */
    public function setUp()
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $this->queue = $this->objectManager->get(RedisQueue::class, self::QUEUE_NAME, []);

        $client = $this->queue->getClient();
        $client->flushdb();
    }
}
