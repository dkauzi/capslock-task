<?php

declare(strict_types=1);

namespace Tests\Helper;

use Codeception\Module;
use Codeception\Exception\ModuleException;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

/**
 * Validates the last REST response against a JSON Schema file stored under
 * tests/schemas/. Wraps justinrainbow/json-schema so test methods stay
 * one-liners.
 */
class SchemaValidator extends Module
{
    private const SCHEMA_DIR = __DIR__ . '/../../schemas/';

    public function seeResponseMatchesJsonSchema(string $schemaFile): void
    {
        $rest = $this->getModule('REST');
        $responseJson = $rest->grabResponse();
        $data = json_decode($responseJson);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ModuleException($this, 'Response is not valid JSON: ' . json_last_error_msg());
        }

        $schemaPath = realpath(self::SCHEMA_DIR . $schemaFile);
        if ($schemaPath === false) {
            throw new ModuleException($this, "Schema file not found: {$schemaFile}");
        }

        $schema = (object) ['$ref' => 'file://' . $schemaPath];

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);

        if (!$validator->isValid()) {
            $errors = array_map(
                static fn($e) => "[{$e['property']}] {$e['message']}",
                $validator->getErrors()
            );
            throw new ModuleException(
                $this,
                "Response does not match schema {$schemaFile}:\n" . implode("\n", $errors)
            );
        }
    }
}
