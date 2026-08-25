<?php

namespace App\Enums;

enum CommunicationStatus: string
{
    case Prepared = 'prepared';
    case Opened = 'opened';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';
    case Linked = 'linked';
}
