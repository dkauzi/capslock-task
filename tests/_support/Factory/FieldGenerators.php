<?php

declare(strict_types=1);

namespace Tests\Factory;

use Faker\Factory as Faker;
use Faker\Generator;

/**
 * Field-level generators. Centralised so a contract change (e.g. mbId becomes
 * alphanumeric) is a one-line edit, not a grep across every test.
 */
final class FieldGenerators
{
    private static ?Generator $faker = null;

    private static function faker(): Generator
    {
        return self::$faker ??= Faker::create();
    }

    public static function mbId(): string
    {
        return (string) self::faker()->numberBetween(1000, 999999);
    }

    public static function initials(): string
    {
        return strtoupper(self::faker()->lexify('??'));
    }

    public static function name(): string
    {
        // Length 2..30 inclusive per contract
        $name = self::faker()->name();
        return substr($name, 0, 30);
    }

    public static function email(): string
    {
        return self::faker()->safeEmail();
    }

    public static function slackUserId(): string
    {
        // Slack workspace user IDs look like U05AZ3DQBBKK
        return 'U' . strtoupper(self::faker()->bothify('##??#??####'));
    }
}
