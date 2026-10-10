<?php

namespace Tests\Unit;

use App\Models\Client;
use PHPUnit\Framework\TestCase;

class ClientStatusGroupTest extends TestCase
{
    public function test_status_groups_map_expected_statuses(): void
    {
        $this->assertSame(Client::STATUS_GROUP_ACTIVE, Client::statusGroup('Active'));
        $this->assertSame(Client::STATUS_GROUP_INACTIVE, Client::statusGroup('Rejected'));
        $this->assertSame(Client::STATUS_GROUP_INACTIVE, Client::statusGroup('On Hold'));
        $this->assertSame(Client::STATUS_GROUP_INACTIVE, Client::statusGroup('Closed'));
        $this->assertSame(Client::STATUS_GROUP_INACTIVE, Client::statusGroup('Black list'));
        $this->assertSame(Client::STATUS_GROUP_INACTIVE, Client::statusGroup('blacklist'));
        $this->assertSame(Client::STATUS_GROUP_POTENTIAL, Client::statusGroup('Searching'));
        $this->assertSame(Client::STATUS_GROUP_POTENTIAL, Client::statusGroup('Interested'));
        $this->assertSame(Client::STATUS_GROUP_POTENTIAL, Client::statusGroup('Sent'));
        $this->assertSame(Client::STATUS_GROUP_POTENTIAL, Client::statusGroup('Broker'));
        $this->assertSame(Client::STATUS_GROUP_POTENTIAL, Client::statusGroup('No Reply'));
    }

    public function test_status_options_include_closed_and_black_list(): void
    {
        $options = Client::statusOptions();

        $this->assertArrayHasKey('Active', $options);
        $this->assertArrayHasKey('Closed', $options);
        $this->assertArrayHasKey('Black list', $options);
        $this->assertArrayHasKey('Searching', $options);
    }

    public function test_inactive_group_filter_options_exclude_pipeline_statuses(): void
    {
        $inactive = Client::statusOptionsForGroup(Client::STATUS_GROUP_INACTIVE);
        $potential = Client::statusOptionsForGroup(Client::STATUS_GROUP_POTENTIAL);

        $this->assertArrayHasKey('Rejected', $inactive);
        $this->assertArrayNotHasKey('Searching', $inactive);
        $this->assertArrayHasKey('Searching', $potential);
        $this->assertArrayNotHasKey('Rejected', $potential);
    }
}
