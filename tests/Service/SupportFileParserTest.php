<?php

namespace App\Tests\Service;

use App\Repository\ClientDeviceRepository;
use App\Repository\NetworkRepository;
use App\Service\DhcpConfigParser;
use App\Service\DnsmasqLeaseParser;
use App\Service\SupportFileParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SupportFileParserTest extends TestCase
{
    private function makeParser(): SupportFileParser
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $networkRepo = $this->createStub(NetworkRepository::class);
        $leaseRepo = $this->createStub(ClientDeviceRepository::class);

        $networkRepo->method('findOneBy')->willReturn(null);
        $leaseRepo->method('findOneBy')->willReturn(null);

        return new SupportFileParser($em, $networkRepo, $leaseRepo, new DhcpConfigParser(), new DnsmasqLeaseParser());
    }

    /** @return array{UploadedFile, string} */
    private function makeUploadedFile(string $fixtureName): array
    {
        $fixturePath = __DIR__ . '/../Fixtures/' . $fixtureName;
        $tmpPath = tempnam(sys_get_temp_dir(), 'php');
        copy($fixturePath, $tmpPath);

        return [new UploadedFile($tmpPath, $fixtureName, test: true), $tmpPath];
    }

    public function testParseReturnsLeaseCount(): void
    {
        [$file, $tmpPath] = $this->makeUploadedFile('support-test-1000000000000.tgz');

        try {
            $count = $this->makeParser()->parse($file);
            $this->assertSame(2, $count);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testParsePersiststNetworkAndLeases(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $networkRepo = $this->createStub(NetworkRepository::class);
        $leaseRepo = $this->createStub(ClientDeviceRepository::class);

        $networkRepo->method('findOneBy')->willReturn(null);
        $leaseRepo->method('findOneBy')->willReturn(null);

        // 1 Network + 2 ClientDevices = 3 persists, 2 flushes
        $em->expects($this->exactly(3))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $parser = new SupportFileParser(
            $em,
            $networkRepo,
            $leaseRepo,
            new DhcpConfigParser(),
            new DnsmasqLeaseParser()
        );

        [$file, $tmpPath] = $this->makeUploadedFile('support-test-1000000000000.tgz');

        try {
            $parser->parse($file);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testMissingLeaseFileThrowsRuntimeException(): void
    {
        [$file, $tmpPath] = $this->makeUploadedFile('support-nolease-2000000000000.tgz');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No dnsmasq.lease file found');

        try {
            $this->makeParser()->parse($file);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testTempFileWithoutExtensionIsParsedCorrectly(): void
    {
        // Simulates a real PHP upload: temp file has no .tgz extension
        $fixturePath = __DIR__ . '/../Fixtures/support-test-1000000000000.tgz';
        $tmpPath = tempnam(sys_get_temp_dir(), 'php');  // e.g. /tmp/phpXXXXXX
        copy($fixturePath, $tmpPath);

        // Verify the temp path really has no recognisable extension
        $this->assertStringNotContainsString('.tgz', basename($tmpPath));

        $file = new UploadedFile($tmpPath, 'support-test-1000000000000.tgz', test: true);

        try {
            $count = $this->makeParser()->parse($file);
            $this->assertSame(2, $count);
        } finally {
            @unlink($tmpPath);
        }
    }
}
