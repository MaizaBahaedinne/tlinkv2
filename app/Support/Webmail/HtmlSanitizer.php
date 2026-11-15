<?php

namespace App\Support\Webmail;

/**
 * Assainit le HTML d'un message reçu avant de l'afficher (en plus du cadre isolé, sans script, qui l'accueille) :
 * plus de scripts, cadres, formulaires, gestionnaires d'événements ni liens « javascript: » ; les images distantes
 * (pisteurs) sont retirées, seules les images intégrées au message sont conservées.
 */
final class HtmlSanitizer
{
    private const FORBIDDEN = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'meta', 'link', 'base', 'audio', 'video', 'source', 'svg', 'math', 'style'];

    /** @param bool $remoteImages Autorise les images https (signature écrite par l'entité, pas un message reçu). */
    public static function clean(string $html, bool $remoteImages = false): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="webmail-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        foreach (self::FORBIDDEN as $tag) {
            foreach (iterator_to_array($xpath->query('//'.$tag) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach ($xpath->query('//*') ?: [] as $element) {
            /** @var \DOMElement $element */
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($attribute->nodeValue ?? '')) ?? '');

                if (str_starts_with($name, 'on') || $name === 'srcdoc' || $name === 'formaction') {
                    $element->removeAttribute($attribute->nodeName);

                    continue;
                }

                if (in_array($name, ['href', 'src', 'background', 'action', 'xlink:href', 'poster'], true)) {
                    $allowed = $name === 'href'
                        ? preg_match('/^(https?:|mailto:|tel:|#)/i', $value) === 1
                        : preg_match('/^(cid:|data:image\/(png|jpe?g|gif|webp);base64,)/i', $value) === 1
                            || ($remoteImages && $name === 'src' && preg_match('/^https:\/\//i', $value) === 1);

                    if (! $allowed) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                }

                if ($name === 'style' && preg_match('/expression|javascript:|url\s*\(|@import|behavior|-moz-binding/i', (string) $attribute->nodeValue)) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }

            if (strtolower($element->nodeName) === 'a') {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }

        $root = $document->getElementById('webmail-root');
        $out = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }
}
