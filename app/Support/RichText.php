<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Texto enriquecido (HTML del editor TipTap).
 *
 * Solo se permite formato básico: párrafos, saltos, negritas, cursivas,
 * listas y subtítulos, SIN atributos (ni estilos, ni links, ni scripts).
 * Se aplica al guardar, porque el HTML que escribe un proveedor lo ven
 * después los usuarios internos.
 */
class RichText
{
    private const ALLOWED_ELEMENTS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote'];

    private static ?HtmlSanitizer $sanitizer = null;

    private static function sanitizer(): HtmlSanitizer
    {
        if (!self::$sanitizer) {
            $config = new HtmlSanitizerConfig();
            foreach (self::ALLOWED_ELEMENTS as $el) {
                $config = $config->allowElement($el); // sin atributos
            }
            self::$sanitizer = new HtmlSanitizer($config->withMaxInputLength(100000));
        }
        return self::$sanitizer;
    }

    /** ¿El texto trae HTML del editor? (los textos anteriores son texto plano) */
    public static function isHtml(?string $value): bool
    {
        return $value !== null && preg_match('/<(p|ul|ol|li|strong|em|br|h3|h4|b|i|u|s|blockquote)\b/i', $value) === 1;
    }

    /** Limpia el HTML antes de guardarlo. El texto plano se guarda tal cual. */
    public static function sanitize(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        if ($value === '') return '';
        return self::isHtml($value) ? self::sanitizer()->sanitize($value) : $value;
    }

    /** Texto visible (sin etiquetas), para validar que no venga vacío */
    public static function plainText(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }

    /** Texto plano conservando saltos de línea y viñetas (para Excel) */
    public static function toPlain(?string $value): string
    {
        if ($value === null || $value === '') return '';
        if (!self::isHtml($value)) return $value;

        $text = preg_replace('/<li[^>]*>/i', '• ', $value);
        $text = preg_replace('/<\/(p|li|h3|h4|blockquote)>|<br\s*\/?>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** HTML seguro para mostrar (correos, PDF): el texto plano se escapa y conserva sus saltos de línea */
    public static function toHtml(?string $value): string
    {
        if ($value === null || $value === '') return '';
        return self::isHtml($value)
            ? self::sanitizer()->sanitize($value)
            : nl2br(e($value));
    }
}