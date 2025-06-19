<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\ActivityLogging\Infrastructure\Doctrine;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\ActivityLogId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DoctrineActivityLogRepository extends DoctrineRepository implements ActivityLogRepository
{
    private const TABLE_NAME = 'log_action';
    private const TARGET_TYPE_VALUE_MAP = [
        TargetTypeEnum::ServiceCategory->value => 'servicecategories',
    ];
    private const ACTION_VALUE_MAP = [
        ActionEnum::Add->value => 'a',
    ];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $connection,
    ) {
    }

    public function add(ActivityLog $activityLog): void
    {
        $qb = $this->connection->createQueryBuilder();

        $targetType = self::TARGET_TYPE_VALUE_MAP[$activityLog->target->type->value];
        $action = self::ACTION_VALUE_MAP[$activityLog->action->value];

        $qb->insert(self::TABLE_NAME)
            ->values([
                'action_log_date' => ':performedAt',
                'object_id' => ':targetId',
                'object_name' => ':targetName',
                'object_type' => ':targetType',
                'action_type' => ':action',
                'log_contact_id' => ':actorId',
            ])
            ->setParameter('performedAt', $activityLog->performedAt->getTimestamp())
            ->setParameter('targetId', $activityLog->target->id->value)
            ->setParameter('targetName', $activityLog->target->name->value)
            ->setParameter('targetType', $targetType)
            ->setParameter('action', $action)
            ->setParameter('actorId', $activityLog->actor->id->value)
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

        $this->setId($activityLog, new ActivityLogId($id));
    }

    public function count(): int
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('count(1) as count')
            ->from(self::TABLE_NAME)
            ->setMaxResults(1);

        /**
         * @var array{count: int} $row
         */
        $row = $qb->fetchAssociative();

        return $row['count'];
    }
}
