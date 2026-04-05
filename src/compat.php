<?php

declare(strict_types=1);

/**
 * Backward-compatibility aliases for moved enums.
 *
 * Prior to the Enums/ directory reorganization, these lived directly
 * in the IPTools namespace. This file ensures existing code using
 * `use IPTools\IPType` or `use IPTools\OverflowMode` continues to work.
 *
 * Loaded automatically via composer.json "files" autoload.
 */
class_alias(IPTools\Enums\IPType::class, IPTools\IPType::class);
class_alias(IPTools\Enums\OverflowMode::class, IPTools\OverflowMode::class);
