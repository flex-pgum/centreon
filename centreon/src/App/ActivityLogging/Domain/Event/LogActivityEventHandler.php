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

namespace App\ActivityLogging\Domain\Event;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\Shared\Domain\Event\AggregateCreated;
use App\Shared\Domain\Event\AsEventHandler;

#[AsEventHandler]
final readonly class LogActivityEventHandler
{
    public function __construct(
        private ActivityLogRepository $repository,
    ) {
    }

    /**
     * @param AggregateCreated<object> $command
     */
    public function __invoke(AggregateCreated $command): void
    {
        $actor = new Actor(
            id: new ActorId($command->creatorId),
        );

        $target = new Target(
            id: new TargetId($this->getAggregateId($command->aggregate)),
            name: new TargetName($this->getAggregateName($command->aggregate)),
            type: $this->getAggregateType($command->aggregate),
        );

        $activityLog = new ActivityLog(
            id: null,
            action: ActionEnum::Add,
            actor: $actor,
            target: $target,
            performedAt: $command->firedAt(),
        );

        $this->repository->add($activityLog);
    }

    private function getAggregateId(object $aggregate): int
    {
        if ($aggregate instanceof ServiceCategory) {
            return $aggregate->id()->value;
        }

        throw new \LogicException(sprintf('"%s" is not handled yet.', $aggregate::class));
    }

    private function getAggregateName(object $aggregate): string
    {
        if ($aggregate instanceof ServiceCategory) {
            return $aggregate->name->value;
        }

        throw new \LogicException(sprintf('"%s" is not handled yet.', $aggregate::class));
    }

    private function getAggregateType(object $aggregate): TargetTypeEnum
    {
        if ($aggregate instanceof ServiceCategory) {
            return TargetTypeEnum::ServiceCategory;
        }

        throw new \LogicException(sprintf('"%s" is not handled yet.', $aggregate::class));
    }
}
