<?php

declare(strict_types=1);

namespace App\DTO;

abstract class BaseDTO
{
    /**
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * @param object $entity
     * @return self
     */
    abstract public static function createFromEntity(object $entity): self;

    /**
     * @param object[] $entities
     * @return self[]
     */
    abstract public static function createFromEntities(array $entities): array;
}
