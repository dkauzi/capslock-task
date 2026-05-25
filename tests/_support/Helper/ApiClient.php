<?php

declare(strict_types=1);

namespace Tests\Helper;

use Codeception\Module;

/**
 * ApiClient is a thin wrapper over the REST module that:
 *   - sets Content-Type / Accept headers required by the contract
 *   - exposes resource-shaped methods (createMediaBuyer, listMediaBuyers)
 *
 * When the endpoint moves, auth is added, or headers change, this is the
 * single file that needs editing. Tests never call sendGet/sendPost directly.
 */
class ApiClient extends Module
{
    private const MEDIA_BUYERS = '/mediabuyers';

    public function _before(\Codeception\TestInterface $test): void
    {
        $rest = $this->getModule('REST');
        $rest->haveHttpHeader('Content-Type', 'application/json');
        $rest->haveHttpHeader('Accept', 'application/json');
    }

    /**
     * Skip a test that requires a real backend (stateful behaviour,
     * server-echoed error strings, request-mirroring responses) when
     * the suite is running against the stateless contract mock.
     *
     * Triggered by env BACKEND=mock. Real environments leave it unset
     * (or BACKEND=real) and the test runs normally.
     */
    public function skipIfBackendIsMock(string $reason): void
    {
        $backend = getenv('BACKEND') ?: 'mock';
        if ($backend === 'mock') {
            $this->getModule('REST');
            \PHPUnit\Framework\Assert::markTestSkipped(
                "Skipped against contract mock: {$reason}"
            );
        }
    }

    public function listMediaBuyers(): void
    {
        $this->getModule('REST')->sendGet(self::MEDIA_BUYERS);
    }

    public function createMediaBuyer(array $payload): void
    {
        $this->getModule('REST')->sendPost(self::MEDIA_BUYERS, $payload);
    }

    public function grabCreatedId(): int
    {
        return (int) $this->getModule('REST')->grabDataFromResponseByJsonPath('$.data.id')[0];
    }
}
