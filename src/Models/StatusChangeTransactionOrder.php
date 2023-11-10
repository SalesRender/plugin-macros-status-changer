<?php

namespace SalesRender\Plugin\Instance\Excel\Models;

use SalesRender\Plugin\Components\Db\Model;
use SalesRender\Plugin\Components\Db\PluginModelInterface;

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