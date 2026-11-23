<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Por dónde sale un aviso: el WhatsApp del consultorio, la API oficial de WhatsApp o la pantalla del celular. */
enum MessageChannel: string implements HasLabel
{
    case ClickToChat = 'click_to_chat';
    case CloudApi = 'cloud_api';
    case WebPush = 'web_push';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClickToChat => 'WhatsApp del consultorio',
            self::CloudApi => 'API oficial de WhatsApp',
            self::WebPush => 'Aviso en el celular',
        };
    }
}
