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

namespace Tests\App\ActivityLogging\Domain\Event;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Event\LogActivityEventHandler;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryId;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;
use App\ResourceConfiguration\Domain\Event\ServiceCategoryCreated;
use PHPUnit\Framework\TestCase;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogRepository;

final class LogActivityEventHandlerTest extends TestCase
{
    public function testCreateServiceCategory(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository);
        $aggregate = new ServiceCategory(
            id: new ServiceCategoryId(1),
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
        );

        $handler(new ServiceCategoryCreated(
            aggregate: $aggregate,
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(2, $activityLog->actor->id->value);
        self::assertSame($aggregate->id()->value, $activityLog->target->id->value);
        self::assertSame($aggregate->name->value, $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::ServiceCategory, $activityLog->target->type);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }
}
