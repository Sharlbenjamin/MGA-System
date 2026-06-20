<?php

namespace Tests\Unit;

use App\Support\ClientEmailRecipients;
use PHPUnit\Framework\TestCase;

class ClientEmailRecipientsTest extends TestCase
{
    public function test_parse_comma_separated_normalizes_and_deduplicates(): void
    {
        $emails = ClientEmailRecipients::parseCommaSeparated(' a@x.com , b@y.com , a@x.com ');

        $this->assertSame(['a@x.com', 'b@y.com'], $emails);
    }

    public function test_validate_list_excludes_to_address(): void
    {
        $result = ClientEmailRecipients::validateList(
            ['finance@client.com', 'finance@client.com', 'ops@client.com'],
            'finance@client.com',
        );

        $this->assertSame(['ops@client.com'], $result['valid']);
        $this->assertSame([], $result['invalid']);
    }

    public function test_validate_list_flags_invalid_emails(): void
    {
        $result = ClientEmailRecipients::validateList(['not-an-email', 'valid@client.com']);

        $this->assertSame(['valid@client.com'], $result['valid']);
        $this->assertSame(['not-an-email'], $result['invalid']);
    }
}
