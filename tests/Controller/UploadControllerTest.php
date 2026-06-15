<?php

namespace App\Tests\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadControllerTest extends AbstractControllerTest
{
    private function makeUploadedFile(string $fixtureName): UploadedFile
    {
        $src = __DIR__ . '/../Fixtures/' . $fixtureName;
        $tmp = tempnam(sys_get_temp_dir(), 'phptest') . '.tgz';
        copy($src, $tmp);

        return new UploadedFile($tmp, $fixtureName, 'application/gzip', null, true);
    }

    public function testSuccessfulUploadRedirectsToOverview(): void
    {
        $client = static::createClient();
        $client->request('POST', '/upload', ['upload' => ['submit' => '']], [
            'upload' => ['file' => $this->makeUploadedFile('support-test-1000000000000.tgz')],
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testSuccessfulUploadImportsLeases(): void
    {
        $client = static::createClient();
        $client->request('POST', '/upload', ['upload' => ['submit' => '']], [
            'upload' => ['file' => $this->makeUploadedFile('support-test-1000000000000.tgz')],
        ]);

        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('imported successfully', $client->getResponse()->getContent());
    }

    public function testUploadWithMissingLeaseStaysOnFormWithError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/upload', ['upload' => ['submit' => '']], [
            'upload' => ['file' => $this->makeUploadedFile('support-nolease-2000000000000.tgz')],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Import error', $client->getResponse()->getContent());
    }

    public function testUploadWithoutFileShowsForm(): void
    {
        $client = static::createClient();
        $client->request('POST', '/upload', ['upload' => ['submit' => '']]);

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('imported successfully', $client->getResponse()->getContent());
    }
}
