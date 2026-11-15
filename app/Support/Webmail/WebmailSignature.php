<?php

namespace App\Support\Webmail;

/**
 * Signature d'une boîte : texte simple ou HTML (long texte accepté). Le HTML est assaini à l'enregistrement et à l'envoi ;
 * les images distantes y sont permises (logo de l'entité), contrairement aux messages reçus.
 */
final class WebmailSignature
{
    public const MAX_LENGTH = 100000;

    public static function isHtml(?string $signature): bool
    {
        return preg_match('/<\s*\/?\s*[a-z][a-z0-9]*(\s[^>]*)?>/i', (string) $signature) === 1;
    }

    /** Valeur à enregistrer : HTML assaini, ou texte simple tel quel ; null si vide. */
    public static function normalize(?string $signature): ?string
    {
        $signature = trim((string) $signature);

        if ($signature === '') {
            return null;
        }

        $signature = self::isHtml($signature) ? trim(HtmlSanitizer::clean($signature, true)) : $signature;

        return strip_tags($signature) === '' && ! preg_match('/<img\b/i', $signature) ? null : $signature;
    }

    /** Version HTML, prête à être ajoutée à un message ou affichée. */
    public static function html(?string $signature): string
    {
        $signature = trim((string) $signature);

        if ($signature === '') {
            return '';
        }

        return self::isHtml($signature) ? HtmlSanitizer::clean($signature, true) : nl2br(e($signature), false);
    }

    /** Version texte brut, pour la partie texte d'un message. */
    public static function text(?string $signature): string
    {
        $signature = trim((string) $signature);

        if (! self::isHtml($signature)) {
            return $signature;
        }

        $text = preg_replace(['/<\s*br\s*\/?>/i', '/<\/(p|div|tr|li|h[1-6])>/i'], "\n", $signature) ?? $signature;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]+\n|\n{3,}/", "\n\n", $text));
    }
}
