<?php

namespace App\Support\Webmail;

use App\Models\WebmailMailbox;

/** Fabrique de passerelles : liée au conteneur pour que les tests y mettent une boîte en mémoire. */
class GatewayFactory
{
    public function make(WebmailMailbox $mailbox): MailGateway
    {
        return new ImapGateway($mailbox);
    }
}
