<?php

namespace Leadvertex\Plugin\Instance\Excel\Models;

use Leadvertex\Plugin\Components\Db\Exceptions\DatabaseException;
use Leadvertex\Plugin\Components\Db\Model;
use Leadvertex\Plugin\Components\Db\PluginModelInterface;

class StatusChangeTransactionOrder extends Model implements PluginModelInterface
{

    protected int $createdAt;
    protected string $processId;
    protected int $statusId;

    public function __construct(int $orderId, string $processId, int $statusId)
    {
        $this->id = $orderId;
        $this->createdAt = time();
        $this->processId = $processId;
        $this->statusId = $statusId;
    }

    /**
     * @return int
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * @return int
     */
    public function getProcessId(): int
    {
        return $this->processId;
    }

    /**
     * @return int
     */
    public function getStatusId(): int
    {
        return $this->statusId;
    }

    public static function findSingleByProcessId(string $id): ?self
    {
        $models = static::findByCondition([
            'processId' => $id,
            'LIMIT' => 1,
        ]);

        if (empty($models)) {
            return null;
        }
        /** @var self $model */
        $model = array_shift($models);

        if ($model->processId != $id) {
            return null;
        }
        return $model;
    }

    /**
     * @param string $id
     * @return array<StatusChangeTransactionOrder>|null
     * @throws DatabaseException
     * @throws \ReflectionException
     */
    public static function findAllByProcessId(string $id): ?array
    {
        $models = static::findByCondition([
            'processId' => $id,
        ]);

        if (empty($models)) {
            return null;
        }

        return $models;
    }

    /**
     * @inheritDoc
     */
    public static function schema(): array
    {
        return [
            'createdAt' => ['INT', 'NOT NULL'],
            'processId' => ['VARCHAR(50)', 'NOT NULL'],
            'statusId' => ['INT', 'NOT NULL'],
        ];
    }
}