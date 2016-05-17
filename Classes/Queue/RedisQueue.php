<?php

namespace R3H6\JobqueueRedis\Queue;

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

use R3H6\Jobqueue\Queue\Message;
use R3H6\Jobqueue\Queue\QueueInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * A queue implementation using Redis as the queue backend
 *
 * Depends on Predis as the PHP Redis client.
 */
class RedisQueue implements QueueInterface
{
    /**
     * @var \Predis\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $options = [
        'timeout' => 1,
        'parameters' => 'tcp://127.0.0.1:6379?database=15',
        'options' => null,
        'expire' => 300,
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct($name, array $options = array())
    {
        $this->name = $name;
        ArrayUtility::mergeRecursiveWithOverrule($this->options, $options, true, false);
        if (isset($this->options['client'])) {
            throw new \InvalidArgumentException('Please use key "parameters" instead of "client" for redis queue options', 1463340591);
        }
        $this->client = new \Predis\Client($this->options['parameters'], $this->options['options']);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Message $message)
    {
        if ($message->getIdentifier()) {
            $this->finish($message);
        }
        $message->setIdentifier(\TYPO3\CMS\Core\Utility\StringUtility::getUniqueId('Redis'));
        $encodedMessage = $this->encodeMessage($message);

        if ($message->isDelayed()) {
            $this->client->zadd("queue:{$this->name}:delayed", time() + $message->getDelay(), $encodedMessage);
        } else {
            $this->client->rpush("queue:{$this->name}:messages", $encodedMessage);
        }
        $message->setState(Message::STATE_PUBLISHED);
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndTake($timeout = null)
    {
        $this->migrateJobs("queue:{$this->name}:delayed");
        $this->migrateJobs("queue:{$this->name}:reserved");
        $timeout = $this->normalizeTimeout($timeout);
        $keyAndValue = $this->client->blpop("queue:{$this->name}:messages", $timeout);
        $value = $keyAndValue[1];
        if (is_string($value)) {
            $message = $this->decodeMessage($value);
            $message->setState(Message::STATE_DONE);
            return $message;
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndReserve($timeout = null)
    {
        $this->migrateJobs("queue:{$this->name}:delayed");
        $this->migrateJobs("queue:{$this->name}:reserved");
        $timeout = $this->normalizeTimeout($timeout);
        $keyAndValue = $this->client->blpop("queue:{$this->name}:messages", $timeout);
        $value = $keyAndValue[1];
        if (is_string($value)) {
            $this->client->zadd("queue:{$this->name}:reserved", time() + $this->options['expire'], $value);
            $message = $this->decodeMessage($value);
            $message->setState(Message::STATE_RESERVED);
            return $message;
        }
        return null;
    }

    /**
     * Migrate jobs from one queue to other.
     *
     * @param  string $from
     * @return void
     * @see https://laravel.com/api/5.1/Illuminate/Queue/RedisQueue.html
     */
    protected function migrateJobs($from)
    {
        $time = time();
        $to = "queue:{$this->name}:messages";
        $jobs = $this->client->zrangebyscore($from, '-inf', $time);
        if (count($jobs) > 0) {
            $this->client->zremrangebyscore($from, '-inf', $time);
            call_user_func_array([$this->client, 'rpush'], array_merge([$to], $jobs));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finish(Message $message)
    {
        $state = $message->getState();
        $encodedMessage = $this->encodeMessage($message);
        $success = $this->client->lrem("queue:{$this->name}:messages", 0, $encodedMessage) > 0;
        if (!$success) {
            $success = $this->client->zrem("queue:{$this->name}:reserved", $encodedMessage) > 0;
        }
        if ($success) {
            $state = Message::STATE_DONE;
        }
        $message->setState($state);
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function peek($limit = 1)
    {
        $messages = array();
        $result = $this->client->lrange("queue:{$this->name}:messages", 0, $limit - 1);
        if (is_array($result) && count($result) > 0) {
            foreach ($result as $value) {
                $message = $this->decodeMessage($value);
                // The message is still published and should not be processed!
                $message->setState(Message::STATE_PUBLISHED);
                $messages[] = $message;
            }
        }
        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $count = $this->client->llen("queue:{$this->name}:messages");
        return (int)$count;
    }

    /**
     * Encode a message
     *
     * Updates the original value property of the message to resemble the
     * encoded representation.
     *
     * @param Message $message
     * @return string
     */
    protected function encodeMessage(Message $message)
    {
        $value = json_encode(array_merge($message->toArray(), ['state' => null]));
        return $value;
    }

    /**
     * Decode a message from a string representation
     *
     * @param string $value
     * @return Message
     */
    protected function decodeMessage($value)
    {
        $decodedMessage = json_decode($value, true);
        $message = new Message(
            $decodedMessage['payload'],
            $decodedMessage['identifier']
        );

        $message->setState($decodedMessage['state']);
        $message->setAttemps($decodedMessage['attemps']);

        return $message;
    }

    /**
     * Normalize timeout to make behavior of redis compatible with other implemenations.
     *
     * @param  int $timeout
     * @return int
     */
    protected function normalizeTimeout($timeout)
    {
        $timeout !== null ? $timeout : $this->options['timeout'];
        if ($timeout === 0) {
            $timeout = 1;
        } else if ($timeout === null) {
            $timeout = 0;
        }
        return $timeout;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage($identifier)
    {
        throw new \RuntimeException('Method not implemented', 1463341087);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Predis\Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
