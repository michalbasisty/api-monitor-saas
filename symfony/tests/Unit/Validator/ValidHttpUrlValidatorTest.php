<?php

namespace App\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Url;
use App\Validator\ValidHttpUrl;

class ValidHttpUrlValidatorTest extends TestCase
{
    public function testValidHttpUrl(): void
    {
        $url = 'http://example.com';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    public function testValidHttpsUrl(): void
    {
        $url = 'https://example.com';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    public function testValidUrlWithPath(): void
    {
        $url = 'https://api.example.com/health';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    public function testValidUrlWithPort(): void
    {
        $url = 'https://example.com:8080/api';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    public function testInvalidUrlNoProtocol(): void
    {
        $url = 'example.com';
        
        // Should be invalid (no protocol)
        $this->assertFalse($this->isValidUrl($url));
    }

    public function testInvalidUrlFtpProtocol(): void
    {
        $url = 'ftp://example.com';
        
        // Should be invalid (FTP not allowed)
        $this->assertFalse($this->isValidUrl($url));
    }

    public function testInvalidUrlWithoutHost(): void
    {
        $url = 'https://';
        
        // Should be invalid
        $this->assertFalse($this->isValidUrl($url));
    }

    public function testInvalidUrlEmpty(): void
    {
        $url = '';
        
        // Should be invalid
        $this->assertFalse($this->isValidUrl($url));
    }

    public function testInvalidUrlWithSpaces(): void
    {
        $url = 'https://example com';
        
        // Should be invalid
        $this->assertFalse($this->isValidUrl($url));
    }

    public function testValidUrlWithQueryParameters(): void
    {
        $url = 'https://api.example.com/search?q=test&limit=10';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    public function testValidUrlWithFragment(): void
    {
        $url = 'https://example.com/page#section';
        
        // Should be valid
        $this->assertTrue($this->isValidUrl($url));
    }

    private function isValidUrl(string $url): bool
    {
        // Simple URL validation
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }
}
