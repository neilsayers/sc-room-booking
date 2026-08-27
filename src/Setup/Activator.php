<?php

namespace SCRoomBookings\Setup;

final class Activator
{
    public const REDIRECT_TRANSIENT = 'scrb_activation_redirect';

    public static function activate(): void
    {
        \set_transient(self::REDIRECT_TRANSIENT, true, 30);
    }

    public static function deactivate(): void
    {
        \flush_rewrite_rules();
    }
}
