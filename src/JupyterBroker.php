<?php

/*
 * This file is part of Jupyter-PHP.
 *
 * (c) 2015-2016 Litipk
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Litipk\JupyterPHP;


use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\ZMQ\SocketWrapper;


final class JupyterBroker
{
    /** @var string */
    private $key;

    /** @var string */
    private $signatureScheme;

    /** @var string */
    private $hashAlgorithm;

    /** @var UuidInterface */
    private $sesssionId;

    /** @var null|LoggerInterface */
    private $logger;

    /**
     * JupyterBroker constructor.
     * @param string $key
     * @param string $signatureScheme
     * @param UuidInterface $sessionId
     * @param null|LoggerInterface $logger
     */
    public function __construct($key, $signatureScheme, UuidInterface $sessionId, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->signatureScheme = $signatureScheme;
        $this->hashAlgorithm = preg_split('/-/', $signatureScheme)[1];
        $this->sesssionId = $sessionId;
        $this->logger = $logger;
    }

    /**
     * @param SocketWrapper $stream
     * @param string $msgType
     * @param array $content
     * @param array $parentHeader
     * @param array $metadata
     */
    public function send(
        SocketWrapper $stream, $msgType, array $content = [], array $parentHeader = [], array $metadata = []
    )
    {
        $header = $this->createHeader($msgType);

        $msgDef = [
            json_encode(empty($header) ? new \stdClass : $header),
            json_encode(empty($parentHeader) ? new \stdClass : $parentHeader),
            json_encode(empty($metadata) ? new \stdClass : $metadata),
            json_encode(empty($content) ? new \stdClass : $content),
        ];

        $finalMsg = array_merge(
            ['<IDS|MSG>', $this->sign($msgDef)],
            $msgDef
        );

        if (null !== $this->logger) {
            $this->logger->debug('Sent message', ['processId' => posix_getpid(), 'message' => $finalMsg]);
        }

        $stream->send($finalMsg);
    }

    /**
     * @param string $msgType
     * @return array
     */
    private function createHeader($msgType)
    {
        return [
            'date'     => (new \DateTime('NOW'))->format('c'),
            'msg_id'   => Uuid::uuid4()->toString(),
            'username' => "kernel",
            'session'  => $this->sesssionId->toString(),
            'msg_type' => $msgType,
        ];
    }

    private function sign(array $message_list) {
        $hm = hash_init(
            $this->hashAlgorithm,
            HASH_HMAC,
            $this->key
        );

        foreach ($message_list as $item) {
            hash_update($hm, $item);
        }

        return hash_final($hm);
    }
}
