<?php

declare(strict_types=1);

namespace App\DTO;

interface BaseDTO
{
    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @param object $entity
     * @return self
     */
    public static function createFromEntity(object $entity): self;

    /**
     * @param object[] $entities
     * @return self[]
     */
    public static function createFromEntities(array $entities): array;
}
