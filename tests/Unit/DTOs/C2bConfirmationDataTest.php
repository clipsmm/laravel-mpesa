<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\DTOs;

use LaravelMpesa\DTOs\Callbacks\C2bConfirmationData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class C2bConfirmationDataTest extends TestCase
{
    #[Test]
    public function test_parsesAConfirmationPayload(): void
    {
        $confirmation = C2bConfirmationData::fromPayload([
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7108',
            'TransTime' => '20260810123407',
            'TransAmount' => '10.00',
            'BusinessShortCode' => '600638',
            'BillRefNumber' => 'invoice008',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '49197.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ]);

        $this->assertSame('Pay Bill', $confirmation->transactionType);
        $this->assertSame('RKTQDM7108', $confirmation->transId);
        $this->assertSame(10.00, $confirmation->transAmount);
        $this->assertSame('invoice008', $confirmation->billRefNumber);
        $this->assertSame(49197.00, $confirmation->orgAccountBalance);
        $this->assertSame('254712345678', $confirmation->msisdn);
        $this->assertSame('John', $confirmation->firstName);
        $this->assertSame('Doe', $confirmation->lastName);
    }

    #[Test]
    public function test_treatsEmptyOrgAccountBalanceAsNull(): void
    {
        $confirmation = C2bConfirmationData::fromPayload([
            'TransID' => 'RKTQDM7108',
            'OrgAccountBalance' => '',
        ]);

        $this->assertNull($confirmation->orgAccountBalance);
    }

    #[Test]
    public function test_toleratesMalformedPayload(): void
    {
        $confirmation = C2bConfirmationData::fromPayload([]);

        $this->assertSame('', $confirmation->transId);
        $this->assertSame(0.0, $confirmation->transAmount);
        $this->assertNull($confirmation->orgAccountBalance);
        $this->assertSame([], $confirmation->payload);
    }
}
