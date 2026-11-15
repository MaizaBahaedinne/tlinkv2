<?php

namespace Tests\Support;

use App\Models\WebmailMailbox;
use App\Support\Webmail\GatewayFactory;
use App\Support\Webmail\MailGateway;

class FakeGatewayFactory extends GatewayFactory
{
    public function __construct(public FakeMailGateway $gateway)
    {
    }

    public function make(WebmailMailbox $mailbox): MailGateway
    {
        return $this->gateway;
    }
}
