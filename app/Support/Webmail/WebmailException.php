<?php

namespace App\Support\Webmail;

/** Erreur du webmail (API OVH, connexion IMAP/SMTP) : le message est destiné à l'administrateur, sans aucun secret. */
class WebmailException extends \RuntimeException
{
}
