<?php

namespace App;

enum CharacterStatus: string
{
    case Pending = 'pending';
    case GeneratingImage = 'generating_image';
    case CreatingAvatar = 'creating_avatar';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isProcessing(): bool
    {
        return in_array($this, [self::Pending, self::GeneratingImage, self::CreatingAvatar]);
    }
}
