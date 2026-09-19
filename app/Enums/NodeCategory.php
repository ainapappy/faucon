<?php

namespace App\Enums;

enum NodeCategory: string
{
    case Trigger = 'trigger';
    case Data = 'data';
    case Logic = 'logic';
    case Ai = 'ai';
    case Action = 'action';
}
