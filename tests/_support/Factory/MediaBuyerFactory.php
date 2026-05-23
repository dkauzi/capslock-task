<?php

declare(strict_types=1);

namespace Tests\Factory;

/**
 * Fluent builder for media-buyer request payloads.
 *
 *   MediaBuyerFactory::valid()->build();
 *   MediaBuyerFactory::valid()->without('email')->build();
 *   MediaBuyerFactory::valid()->with(['initials' => 'TOO LONG'])->build();
 *
 * Negative tests express WHAT is wrong, not WHAT JSON looks like — when the
 * schema evolves, only this class and the FieldGenerators change.
 */
final class MediaBuyerFactory
{
    /** @var array<string,mixed> */
    private array $attributes;

    private function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public static function valid(): self
    {
        return new self([
            'mbId'        => FieldGenerators::mbId(),
            'initials'    => FieldGenerators::initials(),
            'name'        => FieldGenerators::name(),
            'email'       => FieldGenerators::email(),
            'slackUserId' => FieldGenerators::slackUserId(),
            'active'      => true,
        ]);
    }

    public function with(array $overrides): self
    {
        return new self(array_merge($this->attributes, $overrides));
    }

    public function without(string ...$fields): self
    {
        $copy = $this->attributes;
        foreach ($fields as $f) {
            unset($copy[$f]);
        }
        return new self($copy);
    }

    public function build(): array
    {
        return $this->attributes;
    }
}
