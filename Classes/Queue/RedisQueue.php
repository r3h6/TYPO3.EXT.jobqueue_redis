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
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
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
    ];

    /**
     * Constructor
     *
     * @param string $name
     * @param array $options
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
     * Publish a message to the queue
     *
     * @param Message $message
     * @return void
     */
    public function publish(Message $message)
    {
        if ($message->getIdentifier() !== null) {
            $added = $this->client->sadd("queue:{$this->name}:ids", $message->getIdentifier());
            if (!$added) {
                return;
            }
        }
        $encodedMessage = $this->encodeMessage($message);
        $this->client->lpush("queue:{$this->name}:messages", $encodedMessage);
        $message->setState(Message::STATE_PUBLISHED);
    }

    /**
     * Wait for a message in the queue and return the message for processing
     * (without safety queue)
     *
     * @param int $timeout
     * @return \TYPO3\Jobqueue\Common\Message The received message or null if a timeout occured
     */
    public function waitAndTake($timeout = null)
    {
        $timeout !== null ? $timeout : $this->options['timeout'];
        $keyAndValue = $this->client->brpop("queue:{$this->name}:messages", $timeout);
        $value = $keyAndValue[1];
        if (is_string($value)) {
            $message = $this->decodeMessage($value);

            if ($message->getIdentifier() !== null) {
                $this->client->srem("queue:{$this->name}:ids", $message->getIdentifier());
            }

                // The message is marked as done
            $message->setState(Message::STATE_DONE);

            return $message;
        } else {
            return null;
        }
    }

    /**
     * Wait for a message in the queue and save the message to a safety queue
     *
     * TODO: Idea for implementing a TTR (time to run) with monitoring of safety queue. E.g.
     * use different queue names with encoded times? With brpoplpush we cannot modify the
     * queued item on transfer to the safety queue and we cannot update a timestamp to mark
     * the run start time in the message, so separate keys should be used for this.
     *
     * @param int $timeout
     * @return Message
     */
    public function waitAndReserve($timeout = null)
    {
        $timeout !== null ? $timeout : $this->options['timeout'];
        $value = $this->client->brpoplpush("queue:{$this->name}:messages", "queue:{$this->name}:processing", $timeout);
        if (is_string($value)) {
            $message = $this->decodeMessage($value);
            if ($message->getIdentifier() !== null) {
                $this->client->srem("queue:{$this->name}:ids", $message->getIdentifier());
            }
            return $message;
        } else {
            return null;
        }
    }

    /**
     * Mark a message as finished
     *
     * @param Message $message
     * @return boolean true if the message could be removed
     */
    public function finish(Message $message)
    {
        // $originalValue = $message->getOriginalValue();
        $success = $this->client->lrem("queue:{$this->name}:processing", 0, $originalValue) > 0;
        if ($success) {
            $message->setState(Message::STATE_DONE);
        }
        return $success;
    }

    /**
     * Peek for messages
     *
     * @param integer $limit
     * @return array Messages or empty array if no messages were present
     */
    public function peek($limit = 1)
    {
        $result = $this->client->lrange("queue:{$this->name}:messages", -($limit), -1);
        if (is_array($result) && count($result) > 0) {
            $messages = array();
            foreach ($result as $value) {
                $message = $this->decodeMessage($value);
                // The message is still published and should not be processed!
                $message->setState(Message::STATE_PUBLISHED);
                $messages[] = $message;
            }
            return $messages;
        }
        return array();
    }

    /**
     * Count messages in the queue
     *
     * @return integer
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
        $value = json_encode($message->toArray());
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
     *
     * @param string $identifier
     * @return Message
     */
    public function getMessage($identifier)
    {
        throw new \RuntimeException('Method not implemented', 1463341087);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Pheanstalk\Pheanstalk
     */
    public function getClient()
    {
        return $this->client;
    }
}
