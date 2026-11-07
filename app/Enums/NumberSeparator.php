<?php

namespace App\Enums;

/**
 * The character between the parts of a package number. Stored by name, because
 * an empty string ("none") would turn into null on the way in from a form.
 */
enum NumberSeparator: string
{
    case Hyphen = 'hyphen';
    case Dot = 'dot';
    case Slash = 'slash';
    case Underscore = 'underscore';
    case None = 'none';

    public function character(): string
    {
        return match ($this) {
            self::Hyphen => '-',
            self::Dot => '.',
            self::Slash => '/',
            self::Underscore => '_',
            self::None => '',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hyphen => __('Hyphen ( - )'),
            self::Dot => __('Dot ( . )'),
            self::Slash => __('Slash ( / )'),
            self::Underscore => __('Underscore ( _ )'),
            self::None => __('None'),
        };
    }

    /**
     * A sub-package number always needs a visible separator, or P100-0007 and
     * its first sub-package P100-000701 would be impossible to tell apart.
     *
     * @return list<self>
     */
    public static function forSubNumbers(): array
    {
        return [self::Hyphen, self::Dot, self::Slash, self::Underscore];
    }

    /**
     * @param  list<self>  $separators
     * @return list<array{value: string, label: string, character: string}>
     */
    public static function options(array $separators): array
    {
        return array_map(
            fn (self $separator) => ['value' => $separator->value, 'label' => $separator->label(), 'character' => $separator->character()],
            $separators,
        );
    }
}
